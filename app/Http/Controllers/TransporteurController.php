<?php

namespace App\Http\Controllers;

use App\Models\Groupe;
use App\Models\GroupeVehicule;
use App\Models\Transporteur;
use App\Models\TransporteurVehicule;
use App\Services\TicketTransporteurFicheService;
use App\Services\TransporteurCodeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class TransporteurController extends Controller
{
    public function __construct(
        private TransporteurCodeService $transporteurCodeService,
    ) {}

    public function index(Request $request)
    {
        $query = Transporteur::query()
            ->withCount('vehicules')
            ->orderBy('nom')
            ->orderBy('prenoms');

        if ($request->filled('q')) {
            $needle = mb_strtolower(trim((string) $request->query('q')));
            $query->where(function ($sub) use ($needle) {
                $sub->whereRaw('LOWER(code) LIKE ?', ["%{$needle}%"])
                    ->orWhereRaw('LOWER(nom) LIKE ?', ["%{$needle}%"])
                    ->orWhereRaw('LOWER(prenoms) LIKE ?', ["%{$needle}%"]);
            });
        }

        $transporteurs = $query->paginate(20)->withQueryString();

        return view('transporteurs.index', [
            'transporteurs' => $transporteurs,
            'search' => trim((string) $request->query('q', '')),
            'prochainCode' => $this->transporteurCodeService->prochain(),
        ]);
    }

    public function show(Request $request, Transporteur $transporteur)
    {
        $query = $transporteur->vehicules()->orderBy('matricule_vehicule');

        if ($request->filled('q')) {
            $needle = mb_strtolower(trim((string) $request->query('q')));
            $query->whereRaw('LOWER(matricule_vehicule) LIKE ?', ["%{$needle}%"]);
        }

        $camions = $query->get();

        return view('transporteurs.show', [
            'transporteur' => $transporteur,
            'camions' => $camions,
            'search' => trim((string) $request->query('q', '')),
        ]);
    }

    public function ajouterCamions(Transporteur $transporteur)
    {
        $transporteur->load('vehicules');
        $vehiculesApi = $this->fetchVehiculesApi();
        $pgfLookup = $this->vehiculesPgfLookup();

        $attribues = TransporteurVehicule::query()
            ->with('transporteur:id,code')
            ->get()
            ->keyBy('vehicule_id');

        $idsTransporteur = $transporteur->vehicules
            ->pluck('vehicule_id')
            ->map(static fn ($id) => (int) $id)
            ->flip()
            ->all();

        $lignes = [];
        $totalDisponibles = 0;

        foreach ($vehiculesApi as $v) {
            $vehiculeId = (int) ($v['vehicules_id'] ?? 0);
            $matricule = trim((string) ($v['matricule_vehicule'] ?? ''));
            if ($vehiculeId <= 0 || $matricule === '') {
                continue;
            }

            $estPgf = $this->vehiculeEstPgf($vehiculeId, $matricule, $pgfLookup);
            $dejaAssocie = isset($idsTransporteur[$vehiculeId]);
            $attribution = $attribues->get($vehiculeId);
            $autreTransporteur = $attribution && (int) $attribution->transporteur_id !== (int) $transporteur->id
                ? $attribution->transporteur
                : null;

            $selectable = !$estPgf && !$dejaAssocie && $autreTransporteur === null;
            if ($selectable) {
                $totalDisponibles++;
            }

            $lignes[] = [
                'vehicule_id' => $vehiculeId,
                'matricule_vehicule' => $matricule,
                'type_vehicule' => (string) ($v['type_vehicule'] ?? ''),
                'est_pgf' => $estPgf,
                'deja_associe' => $dejaAssocie,
                'autre_transporteur' => $autreTransporteur,
                'selectable' => $selectable,
            ];
        }

        usort($lignes, static fn ($a, $b) => strcasecmp($a['matricule_vehicule'], $b['matricule_vehicule']));

        return view('transporteurs.ajouter_camions', [
            'transporteur' => $transporteur,
            'lignes' => $lignes,
            'total_disponibles' => $totalDisponibles,
        ]);
    }

    public function assignerCamions(Request $request, Transporteur $transporteur)
    {
        $validated = $request->validate([
            'vehicule_ids' => ['required', 'array', 'min:1'],
            'vehicule_ids.*' => ['integer'],
            'matricules' => ['required', 'array'],
        ], [
            'vehicule_ids.required' => 'Sélectionnez au moins un camion.',
            'vehicule_ids.min' => 'Sélectionnez au moins un camion.',
        ]);

        $pgfLookup = $this->vehiculesPgfLookup();
        $count = 0;

        foreach ($validated['vehicule_ids'] as $vehiculeId) {
            $vehiculeId = (int) $vehiculeId;
            $matricule = trim((string) ($validated['matricules'][$vehiculeId] ?? ''));
            if ($vehiculeId <= 0 || $matricule === '') {
                continue;
            }

            if ($this->vehiculeEstPgf($vehiculeId, $matricule, $pgfLookup)) {
                return redirect()->route('transporteurs.camions.ajouter', $transporteur)
                    ->with('error', 'Le camion « ' . $matricule . ' » appartient au groupe PGF et ne peut pas être attribué à un transporteur.');
            }

            $existant = TransporteurVehicule::query()
                ->with('transporteur:id,code')
                ->where('vehicule_id', $vehiculeId)
                ->first();

            if ($existant && (int) $existant->transporteur_id !== (int) $transporteur->id) {
                return redirect()->route('transporteurs.camions.ajouter', $transporteur)
                    ->with('error', 'Le camion « ' . $matricule . ' » est déjà attribué au transporteur '
                        . ($existant->transporteur->code ?? '?') . '.');
            }

            TransporteurVehicule::updateOrCreate(
                ['vehicule_id' => $vehiculeId],
                [
                    'transporteur_id' => $transporteur->id,
                    'matricule_vehicule' => $matricule,
                ]
            );
            $count++;
        }

        if ($count === 0) {
            return redirect()->route('transporteurs.camions.ajouter', $transporteur)
                ->with('error', 'Aucun camion valide n\'a pu être ajouté.');
        }

        app(TicketTransporteurFicheService::class)->reconcilierFichesPourTransporteur($transporteur);

        $message = $count === 1
            ? '1 camion ajouté au transporteur.'
            : "{$count} camions ajoutés au transporteur.";

        return redirect()->route('transporteurs.show', $transporteur)->with('success', $message);
    }

    public function retirerCamion(Transporteur $transporteur, int $vehicule_id)
    {
        TransporteurVehicule::query()
            ->where('transporteur_id', $transporteur->id)
            ->where('vehicule_id', $vehicule_id)
            ->delete();

        return redirect()->route('transporteurs.show', $transporteur)
            ->with('success', 'Camion retiré du transporteur.');
    }

    public function store(Request $request)
    {
        $validated = $this->validateTransporteur($request);
        $validated['code'] = $this->transporteurCodeService->generer();

        Transporteur::create($validated);

        return redirect()->route('transporteurs.index')
            ->with('success', 'Transporteur ajouté avec succès.');
    }

    public function update(Request $request, Transporteur $transporteur)
    {
        $validated = $this->validateTransporteur($request, $transporteur);
        $transporteur->update($validated);

        return redirect()->route('transporteurs.index')
            ->with('success', 'Transporteur modifié avec succès.');
    }

    public function destroy(Transporteur $transporteur)
    {
        $transporteur->delete();

        return redirect()->route('transporteurs.index')
            ->with('success', 'Transporteur supprimé avec succès.');
    }

    /**
     * @return array{ids: array<int, true>, matricules: array<string, true>}
     */
    private function vehiculesPgfLookup(): array
    {
        $groupeIds = Groupe::query()
            ->where('nom_groupe', 'like', '%PGF%')
            ->pluck('id');

        $rows = GroupeVehicule::query()
            ->whereIn('groupe_id', $groupeIds)
            ->get(['vehicule_id', 'matricule_vehicule']);

        $ids = [];
        $matricules = [];
        foreach ($rows as $row) {
            $id = (int) $row->vehicule_id;
            if ($id > 0) {
                $ids[$id] = true;
            }
            $matricule = strtoupper(trim((string) $row->matricule_vehicule));
            if ($matricule !== '') {
                $matricules[$matricule] = true;
            }
        }

        return ['ids' => $ids, 'matricules' => $matricules];
    }

    /**
     * @param  array{ids: array<int, true>, matricules: array<string, true>}  $lookup
     */
    private function vehiculeEstPgf(int $vehiculeId, string $matricule, array $lookup): bool
    {
        if ($vehiculeId > 0 && isset($lookup['ids'][$vehiculeId])) {
            return true;
        }

        $matricule = strtoupper(trim($matricule));

        return $matricule !== '' && isset($lookup['matricules'][$matricule]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchVehiculesApi(): array
    {
        try {
            $response = Http::acceptJson()
                ->withoutVerifying()
                ->timeout(10)
                ->get('https://api.objetombrepegasus.online/api/camions/mes_camions.php');
            if ($response->successful()) {
                return $response->json('vehicules') ?? [];
            }
        } catch (\Throwable $e) {
        }

        return [];
    }

    /**
     * @return array<string, string>
     */
    private function validateTransporteur(Request $request, ?Transporteur $transporteur = null): array
    {
        return $request->validate([
            'nom' => ['required', 'string', 'max:100'],
            'prenoms' => ['required', 'string', 'max:150'],
        ]);
    }
}
