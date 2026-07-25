<?php

namespace App\Http\Controllers;

use App\Models\Chauffeur;
use App\Models\ChauffeurGroupe;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\Rule;

class ChauffeurController extends Controller
{
    public function index(Request $request)
    {
        $query = Chauffeur::query()
            ->with('groupe')
            ->orderBy('nom')
            ->orderBy('prenoms');

        if ($request->filled('chauffeur_groupe_id')) {
            $query->where('chauffeur_groupe_id', (int) $request->input('chauffeur_groupe_id'));
        }

        if ($request->filled('q')) {
            $needle = mb_strtolower(trim((string) $request->query('q')));
            $query->where(function ($sub) use ($needle) {
                $sub->whereRaw('LOWER(nom) LIKE ?', ["%{$needle}%"])
                    ->orWhereRaw('LOWER(prenoms) LIKE ?', ["%{$needle}%"])
                    ->orWhereRaw('LOWER(contact) LIKE ?', ["%{$needle}%"])
                    ->orWhereRaw('LOWER(matricule_vehicule) LIKE ?', ["%{$needle}%"]);
            });
        }

        $chauffeurs = $query->paginate(20)->withQueryString();
        $vehicules = $this->fetchVehiculesFromApi();
        $groupes = ChauffeurGroupe::withCount('chauffeurs')->orderBy('id')->get();
        $totalChauffeurs = (int) Chauffeur::count();
        $defaultGroupeId = $groupes->firstWhere('nom_groupe', 'Chauffeurs PGF')?->id
            ?? $groupes->first()?->id;

        return view('chauffeurs.index', [
            'chauffeurs' => $chauffeurs,
            'vehicules' => $vehicules,
            'groupes' => $groupes,
            'totalChauffeurs' => $totalChauffeurs,
            'defaultGroupeId' => $defaultGroupeId,
            'search' => trim((string) $request->query('q', '')),
            'groupeFilter' => $request->input('chauffeur_groupe_id'),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validateChauffeur($request);

        Chauffeur::create($validated);

        return redirect()->route('chauffeurs.index')->with('success', 'Chauffeur créé avec succès.');
    }

    public function update(Request $request, Chauffeur $chauffeur)
    {
        $validated = $this->validateChauffeur($request, $chauffeur);

        $chauffeur->update($validated);

        return redirect()->route('chauffeurs.index')->with('success', 'Chauffeur modifié avec succès.');
    }

    public function destroy(Chauffeur $chauffeur)
    {
        $chauffeur->delete();

        return redirect()->route('chauffeurs.index')->with('success', 'Chauffeur supprimé avec succès.');
    }

    /**
     * @return array<int, array{matricule: string, id: int|string}>
     */
    private function fetchVehiculesFromApi(): array
    {
        $timeout = (int) config('services.external_auth.timeout', 10);
        $vehicules = [];

        try {
            $response = Http::acceptJson()
                ->withoutVerifying()
                ->timeout($timeout)
                ->get('https://api.objetombrepegasus.online/api/camions/mes_camions.php');

            if ($response->successful()) {
                foreach ($response->json('vehicules') ?? [] as $v) {
                    $matricule = trim((string) ($v['matricule_vehicule'] ?? ''));
                    if ($matricule === '') {
                        continue;
                    }
                    $vehicules[] = [
                        'matricule' => $matricule,
                        'id' => $v['vehicules_id'] ?? $v['vehicule_id'] ?? '',
                    ];
                }
            }
        } catch (\Throwable $e) {
        }

        usort($vehicules, fn ($a, $b) => strcasecmp($a['matricule'], $b['matricule']));

        return $vehicules;
    }

    /**
     * @return array<string, mixed>
     */
    private function validateChauffeur(Request $request, ?Chauffeur $chauffeur = null): array
    {
        $matricule = trim((string) $request->input('matricule_vehicule', ''));
        $request->merge([
            'matricule_vehicule' => $matricule !== '' ? $matricule : null,
            'vehicule_id' => $request->filled('vehicule_id') ? (int) $request->input('vehicule_id') : null,
        ]);

        $validated = $request->validate([
            'nom' => ['required', 'string', 'max:100'],
            'prenoms' => ['required', 'string', 'max:150'],
            'chauffeur_groupe_id' => ['required', 'integer', 'exists:chauffeur_groupes,id'],
            'contact' => ['nullable', 'string', 'max:50'],
            'matricule_vehicule' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('chauffeurs', 'matricule_vehicule')->ignore($chauffeur?->id),
            ],
            'vehicule_id' => ['nullable', 'integer', 'min:1'],
            'salaire' => ['nullable', 'numeric', 'min:0'],
        ], [
            'matricule_vehicule.unique' => 'Ce camion est déjà associé à un autre chauffeur.',
        ]);

        $validated['salaire'] = $validated['salaire'] ?? 0;

        return $validated;
    }
}
