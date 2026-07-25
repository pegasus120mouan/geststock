<?php

namespace App\Http\Controllers;

use App\Models\CodeTransporteur;
use App\Models\CodeTransporteurVehicule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class CodeTransporteurController extends Controller
{
    public function index(Request $request)
    {
        $codes = CodeTransporteur::prisEnCompte()->orderBy('nom')->get();
        $vehicules = $this->chargerVehiculesPourRecherche();

        $groupeTrouve = null;
        $matriculeRecherche = null;

        if ($request->filled('vehicule_id')) {
            $vehiculeId = (int) $request->input('vehicule_id');
            $attribution = CodeTransporteurVehicule::with('codeTransporteur')
                ->where('vehicule_id', $vehiculeId)
                ->first();

            $vehiculeSelectionne = collect($vehicules)->firstWhere('vehicule_id', $vehiculeId);
            $matriculeRecherche = data_get($vehiculeSelectionne, 'matricule_vehicule')
                ?? $attribution?->matricule_vehicule
                ?? '';

            $groupeTrouve = $attribution?->codeTransporteur;
            if ($groupeTrouve && ! $groupeTrouve->estPrisEnCompte()) {
                $groupeTrouve = CodeTransporteur::prisEnCompte()
                    ->where('nom', 'like', '%Autre Camion%')
                    ->first()
                    ?? CodeTransporteur::prisEnCompte()->first();
            }
        }

        return view('code_transporteurs.index', [
            'codes' => $codes,
            'vehicules' => $vehicules,
            'groupeTrouve' => $groupeTrouve,
            'matriculeRecherche' => $matriculeRecherche,
        ]);
    }

    private function chargerVehiculesPourRecherche(): array
    {
        $timeout = (int) config('services.external_auth.timeout', 10);
        $mesCamionsUrl = (string) config('services.external_auth.mes_camions_url');
        $phpsessid = session('external_auth.phpsessid', '');
        $parId = [];

        try {
            $response = Http::acceptJson()
                ->withoutVerifying()
                ->timeout($timeout)
                ->withHeaders(['Cookie' => 'PHPSESSID=' . $phpsessid])
                ->get($mesCamionsUrl);

            if ($response->successful()) {
                foreach ($response->json('vehicules') ?? [] as $v) {
                    $id = (int) ($v['vehicules_id'] ?? $v['id_vehicule'] ?? 0);
                    $matricule = trim((string) ($v['matricule_vehicule'] ?? ''));
                    if ($id > 0 && $matricule !== '') {
                        $parId[$id] = [
                            'vehicule_id' => $id,
                            'matricule_vehicule' => $matricule,
                        ];
                    }
                }
            }
        } catch (\Throwable $e) {
        }

        foreach (CodeTransporteurVehicule::query()->get(['vehicule_id', 'matricule_vehicule']) as $row) {
            $id = (int) $row->vehicule_id;
            $matricule = trim((string) $row->matricule_vehicule);
            if ($id > 0 && $matricule !== '' && !isset($parId[$id])) {
                $parId[$id] = [
                    'vehicule_id' => $id,
                    'matricule_vehicule' => $matricule,
                ];
            }
        }

        $vehicules = array_values($parId);
        usort($vehicules, fn (array $a, array $b) => strcasecmp($a['matricule_vehicule'], $b['matricule_vehicule']));

        return $vehicules;
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nom' => ['required', 'string', 'max:255'],
        ]);

        CodeTransporteur::create($validated);

        return redirect()->route('code_transporteurs.index')
            ->with('success', 'Code transporteur ajouté avec succès.');
    }

    public function update(Request $request, int $id)
    {
        $validated = $request->validate([
            'nom' => ['required', 'string', 'max:255'],
        ]);

        $code = CodeTransporteur::findOrFail($id);
        $code->update($validated);

        return redirect()->route('code_transporteurs.index')
            ->with('success', 'Code transporteur modifié avec succès.');
    }

    public function destroy(int $id)
    {
        $code = CodeTransporteur::findOrFail($id);
        $code->delete();

        return redirect()->route('code_transporteurs.index')
            ->with('success', 'Code transporteur supprimé avec succès.');
    }

    public function show(Request $request, int $id)
    {
        $code = CodeTransporteur::with('vehicules')->findOrFail($id);

        if (! $code->estPrisEnCompte()) {
            return redirect()->route('code_transporteurs.index')
                ->with('error', 'Le code « camion Pisteur » n’est plus pris en compte. Utilisez Autre Camion ou Camion PGF.');
        }
        
        // Récupérer les véhicules depuis l'API mes_camions
        $timeout = (int) config('services.external_auth.timeout', 10);
        $vehiculesApi = [];
        
        try {
            $response = Http::acceptJson()
                ->timeout($timeout)
                ->withoutVerifying()
                ->post('https://api.objetombrepegasus.online/api/camions/mes_camions.php');
            \Log::info('API Response', ['status' => $response->status(), 'body' => substr($response->body(), 0, 500)]);
            if ($response->successful()) {
                $vehiculesApi = $response->json('vehicules') ?? [];
            }
        } catch (\Throwable $e) {
            \Log::error('API Error', ['message' => $e->getMessage()]);
        }

        // Filtrer les véhicules déjà attribués à N'IMPORTE QUEL groupe
        $tousVehiculesAttribues = CodeTransporteurVehicule::pluck('vehicule_id')->toArray();
        $vehiculesDisponibles = array_filter($vehiculesApi, function($v) use ($tousVehiculesAttribues) {
            return !in_array($v['vehicules_id'] ?? 0, $tousVehiculesAttribues);
        });

        return view('code_transporteurs.show', [
            'code' => $code,
            'vehiculesDisponibles' => array_values($vehiculesDisponibles),
        ]);
    }

    public function addVehicule(Request $request, int $id)
    {
        $code = CodeTransporteur::findOrFail($id);
        if (! $code->estPrisEnCompte()) {
            return redirect()->route('code_transporteurs.index')
                ->with('error', 'Le code « camion Pisteur » n’est plus pris en compte.');
        }

        $validated = $request->validate([
            'vehicule_id' => ['required', 'integer'],
            'matricule_vehicule' => ['required', 'string'],
        ]);

        $dejaAttribue = CodeTransporteurVehicule::where('vehicule_id', $validated['vehicule_id'])->first();
        if ($dejaAttribue) {
            $autreGroupe = CodeTransporteur::find($dejaAttribue->code_transporteur_id);
            return redirect()->route('code_transporteurs.show', $id)
                ->with('error', 'Ce véhicule appartient déjà au groupe « ' . ($autreGroupe->nom ?? '?') . ' ».');
        }

        CodeTransporteurVehicule::create([
            'code_transporteur_id' => $id,
            'vehicule_id' => $validated['vehicule_id'],
            'matricule_vehicule' => $validated['matricule_vehicule'],
        ]);

        return redirect()->route('code_transporteurs.show', $id)
            ->with('success', 'Véhicule attribué avec succès.');
    }

    public function removeVehicule(int $id, int $vehicule_id)
    {
        CodeTransporteurVehicule::where('code_transporteur_id', $id)
            ->where('id', $vehicule_id)
            ->delete();

        return redirect()->route('code_transporteurs.show', $id)
            ->with('success', 'Véhicule retiré avec succès.');
    }
}
