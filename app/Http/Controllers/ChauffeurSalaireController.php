<?php

namespace App\Http\Controllers;

use App\Models\Chauffeur;
use App\Services\ChauffeurSalaireService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ChauffeurSalaireController extends Controller
{
    public function __construct(
        private ChauffeurSalaireService $salaireService
    ) {}

    public function index(Request $request)
    {
        $annee = (int) $request->input('annee', now()->year);
        $mois = (int) $request->input('mois', now()->month);
        $mois = max(1, min(12, $mois));

        $this->salaireService->ensurePeriodesMois($annee, $mois);

        $lignes = [];
        foreach ($this->salaireService->chauffeursPgf() as $chauffeur) {
            $periode = $this->salaireService->ensurePeriode($chauffeur, $annee, $mois);
            $soldes = $this->salaireService->calculPeriode($chauffeur, $periode);
            $impayees = $this->salaireService->periodesImpayees($chauffeur);

            $lignes[] = [
                'chauffeur' => $chauffeur,
                'periode' => $periode,
                'soldes' => $soldes,
                'impayees' => $impayees,
            ];
        }

        $totaux = [
            'du' => collect($lignes)->sum(fn ($l) => $l['soldes']['du']),
            'avances' => collect($lignes)->sum(fn ($l) => $l['soldes']['avances']),
            'paye' => collect($lignes)->sum(fn ($l) => $l['soldes']['paye']),
            'reste' => collect($lignes)->sum(fn ($l) => $l['soldes']['reste']),
        ];

        return view('gestion_financiere.chauffeurs_salaires.index', [
            'lignes' => $lignes,
            'annee' => $annee,
            'mois' => $mois,
            'libelleMois' => $this->salaireService->libelleMois($mois, $annee),
            'totaux' => $totaux,
            'groupePgf' => $this->salaireService->groupePgf(),
        ]);
    }

    public function show(Chauffeur $chauffeur)
    {
        $this->salaireService->assertChauffeurPgf($chauffeur);

        $this->salaireService->ensurePeriode($chauffeur, now()->year, now()->month);

        $periodes = $this->salaireService->periodesAvecSoldes($chauffeur);
        $impayees = $this->salaireService->periodesImpayees($chauffeur);

        $avances = $chauffeur->salaireAvances()
            ->with('periode')
            ->orderByDesc('date_avance')
            ->orderByDesc('id')
            ->get();

        $paiements = $chauffeur->salairePaiements()
            ->with('periodes.periode')
            ->orderByDesc('date_paiement')
            ->orderByDesc('id')
            ->get();

        return view('gestion_financiere.chauffeurs_salaires.show', [
            'chauffeur' => $chauffeur,
            'periodes' => $periodes,
            'impayees' => $impayees,
            'avances' => $avances,
            'paiements' => $paiements,
        ]);
    }

    public function storeAvance(Request $request, Chauffeur $chauffeur)
    {
        $this->salaireService->assertChauffeurPgf($chauffeur);

        $validated = $request->validate([
            'annee' => ['required', 'integer', 'min:2000', 'max:2100'],
            'mois' => ['required', 'integer', 'min:1', 'max:12'],
            'montant' => ['required', 'numeric', 'min:1'],
            'date_avance' => ['required', 'date'],
            'libelle' => ['nullable', 'string', 'max:255'],
        ]);

        $this->salaireService->enregistrerAvance(
            $chauffeur,
            (int) $validated['annee'],
            (int) $validated['mois'],
            (float) $validated['montant'],
            $validated['date_avance'],
            $validated['libelle'] ?? null
        );

        return redirect()
            ->back()
            ->with('success', 'Avance enregistrée avec succès.');
    }

    public function storePaiement(Request $request, Chauffeur $chauffeur)
    {
        $this->salaireService->assertChauffeurPgf($chauffeur);

        $validated = $request->validate([
            'periode_ids' => ['required', 'array', 'min:1'],
            'periode_ids.*' => [
                'integer',
                Rule::exists('chauffeur_salaire_periodes', 'id')->where('chauffeur_id', $chauffeur->id),
            ],
            'date_paiement' => ['required', 'date'],
            'libelle' => ['nullable', 'string', 'max:255'],
            'commentaire' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $paiement = $this->salaireService->enregistrerPaiement(
                $chauffeur,
                $validated['periode_ids'],
                $validated['date_paiement'],
                $validated['libelle'] ?? null,
                $validated['commentaire'] ?? null
            );
        } catch (\InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['periode_ids' => $e->getMessage()]);
        }

        return redirect()
            ->back()
            ->with('success', 'Paiement de ' . number_format((float) $paiement->montant_total, 0, ',', ' ') . ' FCFA enregistré.');
    }
}
