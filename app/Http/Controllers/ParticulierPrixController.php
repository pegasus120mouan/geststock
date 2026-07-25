<?php

namespace App\Http\Controllers;

use App\Models\ParticulierAgent;
use App\Models\ParticulierAgentPrix;
use App\Models\ParticulierGroupe;
use App\Models\Usine;
use App\Services\MesAgentsService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

class ParticulierPrixController extends Controller
{
    public function __construct(
        private MesAgentsService $mesAgentsService,
    ) {}

    private function fetchUsines(): array
    {
        $mesUsinesUrl = (string) config('services.external_auth.mes_usines_url');
        $timeout = (int) config('services.external_auth.timeout', 10);

        $usines = [];

        // Usines de l'API (sans produit_id)
        try {
            $response = Http::acceptJson()
                ->withoutVerifying()
                ->timeout($timeout)
                ->get($mesUsinesUrl);

            if ($response->successful()) {
                $usines = $response->json('usines') ?? [];
            }
        } catch (\Throwable $e) {
        }

        // Usines locales avec produit_id
        if (Schema::hasColumn('usines', 'produit_id')) {
            $usinesLocales = Usine::whereNotNull('produit_id')
                ->with('produit')
                ->get();

            foreach ($usinesLocales as $usine) {
                $usines[] = [
                    'id_usine' => $usine->id_usine ?? $usine->id,
                    'nom_usine' => $usine->nom_usine,
                    'produit_id' => $usine->produit_id,
                    'source' => 'local',
                ];
            }
        }

        return $usines;
    }

    private function fetchAgentsApi(): array
    {
        return $this->mesAgentsService->fetchAllAgents();
    }

    private function syncAgentsApi(array $agentsApi): void
    {
        foreach ($agentsApi as $a) {
            $idAgent = (int) ($a['id_agent'] ?? 0);
            if ($idAgent <= 0) {
                continue;
            }
            $exists = ParticulierAgent::where('id_agent', $idAgent)->exists();
            if (!$exists) {
                $nomComplet = trim((string) ($a['nom_complet'] ?? ''));
                $parts = preg_split('/\s+/', $nomComplet, 2);
                ParticulierAgent::create([
                    'particulier_groupe_id' => null,
                    'id_agent'    => $idAgent,
                    'numero_agent' => $a['numero_agent'] ?? ('AGT-' . $idAgent),
                    'nom'         => $parts[0] ?? $nomComplet,
                    'prenoms'     => $parts[1] ?? '',
                    'contact'     => $a['contact'] ?? null,
                ]);
            }
        }
    }

    public function index(Request $request)
    {
        $query = ParticulierAgent::with(['groupe'])
            ->withCount('prix')
            ->whereNull('id_agent')
            ->orderBy('nom')
            ->orderBy('prenoms');

        if ($request->filled('particulier_groupe_id')) {
            $query->where('particulier_groupe_id', (int) $request->input('particulier_groupe_id'));
        }

        if ($request->filled('q')) {
            $q = $request->string('q')->toString();
            $query->where(function ($sub) use ($q) {
                $sub->where('numero_agent', 'like', "%{$q}%")
                    ->orWhere('nom', 'like', "%{$q}%")
                    ->orWhere('prenoms', 'like', "%{$q}%")
                    ->orWhere('contact', 'like', "%{$q}%")
                    ->orWhereHas('groupe', function ($groupeQuery) use ($q) {
                        $groupeQuery->where('nom_groupe', 'like', "%{$q}%");
                    });
            });
        }

        return view('particuliers.prix.index', [
            'agents' => $query->paginate(20)->withQueryString(),
            'groupes' => ParticulierGroupe::orderBy('nom_groupe')->get(),
            'search' => trim((string) $request->query('q', '')),
            'agentNoms' => ParticulierAgent::query()
                ->orderBy('nom')
                ->orderBy('prenoms')
                ->get()
                ->map(fn ($a) => $a->nom_complet)
                ->unique()
                ->values(),
        ]);
    }

    public function show(ParticulierAgent $agent)
    {
        $agent->load([
            'groupe',
            'prix.produit',
        ]);

        $produits = \App\Models\Produit::orderBy('nom')->get();
        $codesTransporteurs = collect([
            (object) ['nom' => 'Autre Camion'],
            (object) ['nom' => 'Camion PGF'],
        ]);

        $prixParProduit = $agent->prix->groupBy('produit_id')->map(function ($prixGroup) {
            return $prixGroup->groupBy('type_transporteur');
        });

        return view('particuliers.prix.show', [
            'agent' => $agent,
            'usines' => $this->fetchUsines(),
            'produits' => $produits,
            'codesTransporteurs' => $codesTransporteurs,
            'prixParProduit' => $prixParProduit,
        ]);
    }

    private function periodesSeChevauchent(?string $dateDebutA, ?string $dateFinA, ?string $dateDebutB, ?string $dateFinB): bool
    {
        $debutA = $dateDebutA ? Carbon::parse($dateDebutA)->startOfDay() : Carbon::create(1900, 1, 1);
        $finA = $dateFinA ? Carbon::parse($dateFinA)->endOfDay() : Carbon::create(2100, 12, 31)->endOfDay();
        $debutB = $dateDebutB ? Carbon::parse($dateDebutB)->startOfDay() : Carbon::create(1900, 1, 1);
        $finB = $dateFinB ? Carbon::parse($dateFinB)->endOfDay() : Carbon::create(2100, 12, 31)->endOfDay();

        return $debutA->lte($finB) && $debutB->lte($finA);
    }

    private function prixUsineEnConflit(
        ParticulierAgent $agent,
        int $idUsine,
        ?string $dateDebut,
        ?string $dateFin,
        ?int $excludePrixId = null,
        ?string $typeTransporteur = null,
        ?int $produitId = null
    ): ?ParticulierAgentPrix {
        $query = ParticulierAgentPrix::where('particulier_agent_id', $agent->id)
            ->where('id_usine', $idUsine)
            ->where('type_transporteur', $typeTransporteur) // Même type uniquement
            ->where('produit_id', $produitId); // Même produit uniquement

        if ($excludePrixId) {
            $query->where('id', '!=', $excludePrixId);
        }

        foreach ($query->get() as $existant) {
            if ($this->periodesSeChevauchent(
                $dateDebut,
                $dateFin,
                $existant->date_debut?->format('Y-m-d'),
                $existant->date_fin?->format('Y-m-d')
            )) {
                return $existant;
            }
        }

        return null;
    }

    private function formaterPeriode(?ParticulierAgentPrix $prix): string
    {
        if (!$prix) {
            return '';
        }

        $debut = $prix->date_debut ? $prix->date_debut->format('d/m/Y') : '…';
        $fin = $prix->date_fin ? $prix->date_fin->format('d/m/Y') : '…';

        return "{$debut} au {$fin}";
    }

    public function storePrix(Request $request, ParticulierAgent $agent)
    {
        $validated = $request->validate([
            'id_usine' => ['required'],
            'nom_usine' => ['required', 'string', 'max:255'],
            'type_transporteur' => ['nullable', 'string', 'max:255'],
            'type_transporteur_hidden' => ['nullable', 'string', 'max:255'],
            'produit_id' => ['nullable', 'integer'],
            'prix' => ['required', 'numeric', 'min:0'],
            'date_debut' => ['nullable', 'date'],
            'date_fin' => ['nullable', 'date', 'after_or_equal:date_debut'],
            'toutes_usines' => ['nullable', 'in:0,1'],
        ]);

        // Utiliser type_transporteur_hidden si type_transporteur est vide (cas du champ disabled)
        if (empty($validated['type_transporteur']) && !empty($validated['type_transporteur_hidden'])) {
            $validated['type_transporteur'] = $validated['type_transporteur_hidden'];
        }

        $dateDebut = $validated['date_debut'] ?? null;
        $dateFin = $validated['date_fin'] ?? null;

        if ($request->input('toutes_usines') === '1' || $validated['id_usine'] === 'all') {
            $usines = $this->fetchUsines();
            $count = 0;
            $ignored = 0;

            foreach ($usines as $usine) {
                $idUsine = (int) ($usine['id_usine'] ?? 0);
                if ($idUsine <= 0) {
                    continue;
                }

                if ($this->prixUsineEnConflit($agent, $idUsine, $dateDebut, $dateFin, null, $validated['type_transporteur'] ?? null, $validated['produit_id'] ?? null)) {
                    $ignored++;
                    continue;
                }

                ParticulierAgentPrix::create([
                    'particulier_agent_id' => $agent->id,
                    'id_usine' => $idUsine,
                    'nom_usine' => $usine['nom_usine'] ?? '',
                    'type_transporteur' => $validated['type_transporteur'] ?? null,
                    'produit_id' => $validated['produit_id'] ?? null,
                    'prix' => $validated['prix'],
                    'date_debut' => $dateDebut,
                    'date_fin' => $dateFin,
                ]);
                $count++;
            }

            $message = "Prix ajouté pour {$count} usine(s).";
            if ($ignored > 0) {
                $message .= " {$ignored} usine(s) ignorée(s) (période déjà couverte).";
            }

            return redirect()->route('particuliers.prix.show', $agent)
                ->with('success', $message);
        }

        $idUsine = (int) $validated['id_usine'];
        $conflit = $this->prixUsineEnConflit($agent, $idUsine, $dateDebut, $dateFin, null, $validated['type_transporteur'] ?? null, $validated['produit_id'] ?? null);

        if ($conflit) {
            return back()->withInput()->withErrors([
                'id_usine' => 'Cette période chevauche un prix existant pour cette usine ('
                    . $this->formaterPeriode($conflit) . ').',
            ]);
        }

        ParticulierAgentPrix::create([
            'particulier_agent_id' => $agent->id,
            'id_usine' => $idUsine,
            'nom_usine' => $validated['nom_usine'],
            'type_transporteur' => $validated['type_transporteur'] ?? null,
            'produit_id' => $validated['produit_id'] ?? null,
            'prix' => $validated['prix'],
            'date_debut' => $dateDebut,
            'date_fin' => $dateFin,
        ]);

        return redirect()->route('particuliers.prix.show', $agent)
            ->with('success', 'Prix unitaire ajouté avec succès.');
    }

    public function updatePrix(Request $request, ParticulierAgent $agent, ParticulierAgentPrix $prix)
    {
        abort_unless($prix->particulier_agent_id === $agent->id, 404);

        $validated = $request->validate([
            'type_transporteur' => ['nullable', 'string', 'max:255'],
            'produit_id' => ['nullable', 'integer'],
            'prix' => ['required', 'numeric', 'min:0'],
            'date_debut' => ['nullable', 'date'],
            'date_fin' => ['nullable', 'date', 'after_or_equal:date_debut'],
        ]);

        $dateDebut = $validated['date_debut'] ?? null;
        $dateFin = $validated['date_fin'] ?? null;
        $conflit = $this->prixUsineEnConflit(
            $agent,
            (int) $prix->id_usine,
            $dateDebut,
            $dateFin,
            $prix->id,
            $validated['type_transporteur'] ?? null,
            $validated['produit_id'] ?? null
        );

        if ($conflit) {
            return back()->withInput()->withErrors([
                'date_debut' => 'Cette période chevauche un autre prix pour cette usine ('
                    . $this->formaterPeriode($conflit) . ').',
            ]);
        }

        $prix->update($validated);

        return redirect()->route('particuliers.prix.show', $agent)
            ->with('success', 'Prix unitaire modifié avec succès.');
    }

    public function deletePrix(ParticulierAgent $agent, ParticulierAgentPrix $prix)
    {
        abort_unless($prix->particulier_agent_id === $agent->id, 404);

        $prix->delete();

        return redirect()->route('particuliers.prix.show', $agent)
            ->with('success', 'Prix unitaire supprimé avec succès.');
    }
}
