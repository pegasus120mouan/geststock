<?php

namespace App\Http\Controllers;

use App\Models\Fournisseur;
use App\Models\PaiementFournisseur;
use App\Models\Depense;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;

class MontantFournisseurController extends Controller
{
    public function index()
    {
        $fournisseurs = Fournisseur::with(['service', 'paiements'])->orderBy('nom')->get();

        // Calculer les montants pour chaque fournisseur
        $fournisseursData = $fournisseurs->map(function ($fournisseur) {
            // Montant dû = somme des dépenses où description = nom du fournisseur
            $montantDu = Depense::where('description', $fournisseur->nom)->sum('montant');
            
            // Montant payé = somme des paiements
            $montantPaye = $fournisseur->paiements->sum('montant');
            
            // Reste à payer
            $resteAPayer = $montantDu - $montantPaye;

            return [
                'fournisseur' => $fournisseur,
                'montant_du' => $montantDu,
                'montant_paye' => $montantPaye,
                'reste_a_payer' => $resteAPayer,
            ];
        });

        return view('gestion_financiere.montant_fournisseur', [
            'fournisseursData' => $fournisseursData,
        ]);
    }

    public function storePaiement(Request $request)
    {
        $validated = $request->validate([
            'fournisseur_id' => 'required|exists:fournisseurs,id',
            'montant' => 'required|numeric|min:0',
            'date_paiement' => 'required|date',
            'mode_paiement' => 'required|string',
            'reference' => 'nullable|string|max:255',
            'commentaire' => 'nullable|string',
        ]);

        PaiementFournisseur::create($validated);

        return back()->with('success', 'Paiement enregistré avec succès.');
    }

    public function show($nom)
    {
        // Trouver le fournisseur par son nom
        $fournisseur = Fournisseur::with(['service', 'paiements'])->where('nom', $nom)->first();

        if (!$fournisseur) {
            // Si pas trouvé par nom exact, chercher les dépenses avec ce nom
            $depenses = Depense::where('description', $nom)->orderBy('date_depense', 'desc')->get();
            $paiements = collect();
            $montantDu = $depenses->sum('montant');
            $montantPaye = 0;
            $fournisseurNom = $nom;
            $service = null;
        } else {
            $depenses = Depense::where('description', $fournisseur->nom)->orderBy('date_depense', 'desc')->get();
            $paiements = $fournisseur->paiements()->orderBy('date_paiement', 'desc')->get();
            $montantDu = $depenses->sum('montant');
            $montantPaye = $paiements->sum('montant');
            $fournisseurNom = $fournisseur->nom;
            $service = $fournisseur->service;
        }

        $resteAPayer = $montantDu - $montantPaye;

        return view('gestion_financiere.fournisseur_detail', [
            'fournisseur' => $fournisseur,
            'fournisseurNom' => $fournisseurNom,
            'service' => $service,
            'depenses' => $depenses,
            'paiements' => $paiements,
            'montantDu' => $montantDu,
            'montantPaye' => $montantPaye,
            'resteAPayer' => $resteAPayer,
        ]);
    }

    public function exportPdf(Request $request, $nom)
    {
        $dateDebut = $request->input('date_debut');
        $dateFin = $request->input('date_fin');

        // Trouver le fournisseur par son nom
        $fournisseur = Fournisseur::with(['service', 'paiements'])->where('nom', $nom)->first();

        if (!$fournisseur) {
            $depensesQuery = Depense::where('description', $nom);
            if ($dateDebut && $dateFin) {
                $depensesQuery->whereBetween('date_depense', [$dateDebut, $dateFin]);
            }
            $depenses = $depensesQuery->orderBy('date_depense', 'desc')->get();
            $paiements = collect();
            $montantDu = $depenses->sum('montant');
            $montantPaye = 0;
            $fournisseurNom = $nom;
            $service = null;
        } else {
            $depensesQuery = Depense::where('description', $fournisseur->nom);
            if ($dateDebut && $dateFin) {
                $depensesQuery->whereBetween('date_depense', [$dateDebut, $dateFin]);
            }
            $depenses = $depensesQuery->orderBy('date_depense', 'desc')->get();

            $paiementsQuery = $fournisseur->paiements();
            if ($dateDebut && $dateFin) {
                $paiementsQuery->whereBetween('date_paiement', [$dateDebut, $dateFin]);
            }
            $paiements = $paiementsQuery->orderBy('date_paiement', 'desc')->get();

            $montantDu = $depenses->sum('montant');
            $montantPaye = $paiements->sum('montant');
            $fournisseurNom = $fournisseur->nom;
            $service = $fournisseur->service;
        }

        $resteAPayer = $montantDu - $montantPaye;

        $pdf = Pdf::loadView('gestion_financiere.fournisseur_pdf', [
            'fournisseur' => $fournisseur,
            'fournisseurNom' => $fournisseurNom,
            'service' => $service,
            'depenses' => $depenses,
            'paiements' => $paiements,
            'montantDu' => $montantDu,
            'montantPaye' => $montantPaye,
            'resteAPayer' => $resteAPayer,
            'dateDebut' => $dateDebut,
            'dateFin' => $dateFin,
        ]);

        return $pdf->download('historique_' . str_replace(' ', '_', $fournisseurNom) . '_' . date('Y-m-d') . '.pdf');
    }
}
