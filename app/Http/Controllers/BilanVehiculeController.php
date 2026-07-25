<?php

namespace App\Http\Controllers;

use App\Models\FicheSortie;
use App\Models\Groupe;
use App\Models\GroupeVehicule;
use App\Models\TransporteurVehicule;

class BilanVehiculeController extends Controller
{
    public function index()
    {
        $groupePgf = Groupe::query()->where('nom_groupe', 'PGF')->first();
        $vehiculesPgf = $groupePgf
            ? GroupeVehicule::query()
                ->where('groupe_id', $groupePgf->id)
                ->orderBy('matricule_vehicule')
                ->get()
            : collect();

        $idsPgf = $vehiculesPgf->pluck('vehicule_id')
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->all();

        $vehiculesTransporteurs = TransporteurVehicule::query()
            ->with('transporteur')
            ->when($idsPgf !== [], fn ($query) => $query->whereNotIn('vehicule_id', $idsPgf))
            ->orderBy('matricule_vehicule')
            ->get();

        $categories = collect([
            (object) [
                'id' => 'autres',
                'nom' => 'Autre Camion',
                'vehicules' => $vehiculesTransporteurs,
            ],
            (object) [
                'id' => 'pgf',
                'nom' => 'Camion PGF',
                'vehicules' => $vehiculesPgf,
            ],
        ]);

        foreach ($categories as $categorie) {
            $vehiculeIds = $categorie->vehicules->pluck('vehicule_id')
                ->map(fn ($id) => (int) $id)
                ->filter()
                ->all();

            $categorie->nb_vehicules = $categorie->vehicules->count();
            $categorie->nb_fiches = FicheSortie::whereIn('vehicule_id', $vehiculeIds)->count();
            $categorie->total_carburant = FicheSortie::whereIn('vehicule_id', $vehiculeIds)->sum('carburant') ?? 0;
            $categorie->total_frais_route = FicheSortie::whereIn('vehicule_id', $vehiculeIds)->sum('frais_route') ?? 0;
            $categorie->total_poids = FicheSortie::whereIn('vehicule_id', $vehiculeIds)
                ->whereNotNull('poids_pont')
                ->sum('poids_pont') ?? 0;
            $categorie->total_montant_camion = FicheSortie::whereIn('vehicule_id', $vehiculeIds)
                ->whereNotNull('montant_camion')
                ->sum('montant_camion') ?? 0;

            foreach ($categorie->vehicules as $vehicule) {
                $vehicule->nb_fiches = FicheSortie::where('vehicule_id', $vehicule->vehicule_id)->count();
                $vehicule->total_carburant = FicheSortie::where('vehicule_id', $vehicule->vehicule_id)->sum('carburant') ?? 0;
                $vehicule->total_frais_route = FicheSortie::where('vehicule_id', $vehicule->vehicule_id)->sum('frais_route') ?? 0;
                $vehicule->total_poids = FicheSortie::where('vehicule_id', $vehicule->vehicule_id)
                    ->whereNotNull('poids_pont')
                    ->sum('poids_pont') ?? 0;
                $vehicule->total_montant_camion = FicheSortie::where('vehicule_id', $vehicule->vehicule_id)
                    ->whereNotNull('montant_camion')
                    ->sum('montant_camion') ?? 0;
                $vehicule->total_depenses = $vehicule->total_carburant + $vehicule->total_frais_route;
                $vehicule->marge = $vehicule->total_montant_camion - $vehicule->total_depenses;
            }
        }

        return view('bilan-vehicule.index', [
            'categories' => $categories,
        ]);
    }

    public function show(int $vehicule_id)
    {
        $groupePgf = Groupe::query()->where('nom_groupe', 'PGF')->first();
        $vehicule = $groupePgf
            ? GroupeVehicule::query()
                ->where('groupe_id', $groupePgf->id)
                ->where('vehicule_id', $vehicule_id)
                ->first()
            : null;
        $categorieLabel = 'Camion PGF';

        if (! $vehicule) {
            $vehicule = TransporteurVehicule::query()
                ->with('transporteur')
                ->where('vehicule_id', $vehicule_id)
                ->first();
            $categorieLabel = 'Autre Camion';

            if ($vehicule?->transporteur) {
                $transporteur = $vehicule->transporteur;
                $categorieLabel .= ' — '.trim(
                    ($transporteur->code ? $transporteur->code.' — ' : '')
                    .$transporteur->nom.' '.$transporteur->prenoms
                );
            }
        }

        if (!$vehicule) {
            return redirect()->route('bilan-vehicule.index')->withErrors(['error' => 'Véhicule non trouvé.']);
        }

        $fiches = FicheSortie::where('vehicule_id', $vehicule_id)
            ->orderBy('date_chargement', 'desc')
            ->get();

        $totalCarburant = $fiches->sum('carburant');
        $totalFraisRoute = $fiches->sum('frais_route');
        $totalPoids = $fiches->whereNotNull('poids_pont')->sum('poids_pont');
        $totalMontantCamion = $fiches->whereNotNull('montant_camion')->sum('montant_camion');
        $totalDepenses = $totalCarburant + $totalFraisRoute;
        $marge = $totalMontantCamion - $totalDepenses;

        return view('bilan-vehicule.show', [
            'vehicule' => $vehicule,
            'fiches' => $fiches,
            'totalCarburant' => $totalCarburant,
            'totalFraisRoute' => $totalFraisRoute,
            'totalPoids' => $totalPoids,
            'totalMontantCamion' => $totalMontantCamion,
            'totalDepenses' => $totalDepenses,
            'marge' => $marge,
            'categorieLabel' => $categorieLabel,
        ]);
    }
}
