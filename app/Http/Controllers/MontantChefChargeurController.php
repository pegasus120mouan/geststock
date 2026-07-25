<?php

namespace App\Http\Controllers;

use App\Models\ChefChargeur;
use App\Models\FicheSortie;
use App\Models\PaiementChefChargeur;
use App\Models\ChefChargeurPrix;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;

class MontantChefChargeurController extends Controller
{
    public function index()
    {
        $chefChargeurs = ChefChargeur::orderBy('nom')->get();

        $data = [];
        foreach ($chefChargeurs as $chef) {
            // Calculer le montant dû (somme des paiements chargeur des fiches de sortie)
            $montantDu = $this->calculerMontantDu($chef);

            // Calculer le montant payé
            $montantPaye = $chef->paiements()->sum('montant');

            // Reste à payer
            $resteAPayer = $montantDu - $montantPaye;

            $data[] = [
                'chef' => $chef,
                'montant_du' => $montantDu,
                'montant_paye' => $montantPaye,
                'reste_a_payer' => $resteAPayer,
            ];
        }

        return view('gestion_financiere.montant_chef_chargeur', [
            'data' => $data,
        ]);
    }

    private function calculerMontantDu(ChefChargeur $chef): int
    {
        $total = 0;

        // Récupérer toutes les fiches de sortie de ce chef
        $fiches = FicheSortie::where('id_chef_chargeur', $chef->id)
            ->whereNotNull('poids_pont')
            ->whereNotNull('date_chargement')
            ->get();

        foreach ($fiches as $fiche) {
            // Trouver le prix unitaire applicable à la date de chargement
            $prixPeriode = ChefChargeurPrix::where('id_chef_chargeur', $chef->id)
                ->where('date_debut', '<=', $fiche->date_chargement)
                ->where(function ($query) use ($fiche) {
                    $query->whereNull('date_fin')
                          ->orWhere('date_fin', '>=', $fiche->date_chargement);
                })
                ->first();

            if ($prixPeriode) {
                // Convertir kg en tonnes (diviser par 1000)
                $poidsEnTonnes = (float) $fiche->poids_pont / 1000;
                $total += $prixPeriode->prix_unitaire * $poidsEnTonnes;
            }
        }

        return (int) $total;
    }

    public function storePaiement(Request $request, ChefChargeur $chefChargeur)
    {
        $validated = $request->validate([
            'montant' => ['required', 'integer', 'min:1'],
            'date_paiement' => ['required', 'date'],
            'mode_paiement' => ['nullable', 'string', 'max:50'],
            'reference' => ['nullable', 'string', 'max:100'],
            'commentaire' => ['nullable', 'string', 'max:500'],
        ]);

        $chefChargeur->paiements()->create($validated);

        return redirect()->route('gestionfinanciere.montant_chef_chargeur')
            ->with('success', 'Paiement enregistré avec succès.');
    }

    public function show($id)
    {
        $chef = ChefChargeur::findOrFail($id);
        
        $montantDu = $this->calculerMontantDu($chef);
        $paiements = $chef->paiements()->orderBy('date_paiement', 'desc')->get();
        $montantPaye = $paiements->sum('montant');
        $resteAPayer = $montantDu - $montantPaye;

        // Récupérer les fiches de sortie pour le détail
        $fiches = FicheSortie::where('id_chef_chargeur', $chef->id)
            ->whereNotNull('poids_pont')
            ->whereNotNull('date_chargement')
            ->orderBy('date_chargement', 'desc')
            ->get();

        // Calculer le montant pour chaque fiche
        $fichesAvecMontant = [];
        foreach ($fiches as $fiche) {
            $prixPeriode = ChefChargeurPrix::where('id_chef_chargeur', $chef->id)
                ->where('date_debut', '<=', $fiche->date_chargement)
                ->where(function ($query) use ($fiche) {
                    $query->whereNull('date_fin')
                          ->orWhere('date_fin', '>=', $fiche->date_chargement);
                })
                ->first();

            // Convertir kg en tonnes (diviser par 1000)
            $poidsEnTonnes = (float) $fiche->poids_pont / 1000;
            $montant = $prixPeriode ? $prixPeriode->prix_unitaire * $poidsEnTonnes : 0;
            
            $fichesAvecMontant[] = [
                'fiche' => $fiche,
                'montant' => $montant,
            ];
        }

        return view('gestion_financiere.chef_chargeur_detail', [
            'chef' => $chef,
            'fichesAvecMontant' => $fichesAvecMontant,
            'paiements' => $paiements,
            'montantDu' => $montantDu,
            'montantPaye' => $montantPaye,
            'resteAPayer' => $resteAPayer,
        ]);
    }

    public function exportPdf(Request $request, $id)
    {
        $chef = ChefChargeur::findOrFail($id);
        $dateDebut = $request->input('date_debut');
        $dateFin = $request->input('date_fin');

        // Récupérer les fiches de sortie filtrées par date
        $fichesQuery = FicheSortie::where('id_chef_chargeur', $chef->id)
            ->whereNotNull('poids_pont')
            ->whereNotNull('date_chargement');
        
        if ($dateDebut && $dateFin) {
            $fichesQuery->whereBetween('date_chargement', [$dateDebut, $dateFin]);
        }
        
        $fiches = $fichesQuery->orderBy('date_chargement', 'desc')->get();

        // Calculer le montant pour chaque fiche
        $fichesAvecMontant = [];
        $montantDu = 0;
        foreach ($fiches as $fiche) {
            $prixPeriode = ChefChargeurPrix::where('id_chef_chargeur', $chef->id)
                ->where('date_debut', '<=', $fiche->date_chargement)
                ->where(function ($query) use ($fiche) {
                    $query->whereNull('date_fin')
                          ->orWhere('date_fin', '>=', $fiche->date_chargement);
                })
                ->first();

            // Convertir kg en tonnes (diviser par 1000)
            $poidsEnTonnes = (float) $fiche->poids_pont / 1000;
            $montant = $prixPeriode ? $prixPeriode->prix_unitaire * $poidsEnTonnes : 0;
            $montantDu += $montant;
            
            $fichesAvecMontant[] = [
                'fiche' => $fiche,
                'montant' => $montant,
            ];
        }

        // Récupérer les paiements filtrés par date
        $paiementsQuery = $chef->paiements();
        if ($dateDebut && $dateFin) {
            $paiementsQuery->whereBetween('date_paiement', [$dateDebut, $dateFin]);
        }
        $paiements = $paiementsQuery->orderBy('date_paiement', 'desc')->get();
        $montantPaye = $paiements->sum('montant');
        $resteAPayer = $montantDu - $montantPaye;

        $pdf = Pdf::loadView('gestion_financiere.chef_chargeur_pdf', [
            'chef' => $chef,
            'fichesAvecMontant' => $fichesAvecMontant,
            'paiements' => $paiements,
            'montantDu' => $montantDu,
            'montantPaye' => $montantPaye,
            'resteAPayer' => $resteAPayer,
            'dateDebut' => $dateDebut,
            'dateFin' => $dateFin,
        ]);

        return $pdf->download('historique_' . str_replace(' ', '_', $chef->nom . '_' . $chef->prenoms) . '_' . date('Y-m-d') . '.pdf');
    }
}
