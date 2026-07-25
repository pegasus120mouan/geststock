<?php

namespace App\Http\Controllers;

use App\Models\BordereauAgent;
use App\Models\BordereauTransporteur;
use App\Models\DemandeAvance;
use App\Models\Depense;
use App\Models\Fournisseur;
use App\Services\CaisseService;
use App\Services\ChauffeurSalaireService;
use App\Services\FinancementService;
use App\Services\MesAgentsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EffectuerPaiementController extends Controller
{
    private const ONGLETS = ['agents', 'transporteurs', 'fournisseurs', 'financements', 'salaires'];

    public function __construct(
        private readonly MesAgentsService $mesAgentsService,
        private readonly FinancementService $financementService,
        private readonly CaisseService $caisseService,
        private readonly ChauffeurSalaireService $salaireService,
    ) {}

    public function index(Request $request)
    {
        $onglet = (string) $request->query('onglet', 'agents');
        if (! in_array($onglet, self::ONGLETS, true)) {
            $onglet = 'agents';
        }

        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'statut' => trim((string) $request->query('statut', 'a_payer')),
        ];

        $data = match ($onglet) {
            'transporteurs' => $this->dataTransporteurs($filters),
            'fournisseurs' => $this->dataFournisseurs($filters),
            'financements' => $this->dataFinancements($filters),
            'salaires' => $this->dataSalaires($request, $filters),
            default => $this->dataAgents($request, $filters),
        };

        return view('effectuer_paiement.index', array_merge([
            'onglet' => $onglet,
            'filters' => $filters,
            'soldeCaisseLocale' => (int) round($this->caisseService->getSolde()),
        ], $data));
    }

    private function dataAgents(Request $request, array $filters): array
    {
        $agentIds = [];
        try {
            $agentIds = $this->mesAgentsService->chefAgentIds($request);
        } catch (\Throwable) {
            $agentIds = [];
        }

        $query = BordereauAgent::query()
            ->orderByDesc('date_generation')
            ->orderByDesc('id');

        if ($agentIds !== []) {
            $query->whereIn('id_agent', $agentIds);
        }

        if ($filters['q'] !== '') {
            $q = $filters['q'];
            $query->where(function ($builder) use ($q) {
                $builder->where('numero', 'like', "%{$q}%")
                    ->orWhere('agent_nom', 'like', "%{$q}%")
                    ->orWhere('agent_numero', 'like', "%{$q}%");
            });
        }

        $this->appliquerStatutBordereau($query, $filters['statut']);

        $bordereaux = $query->paginate(25)->withQueryString();

        $financements = [];
        $agentIdsOnPage = $bordereaux->getCollection()
            ->pluck('id_agent')
            ->unique()
            ->filter()
            ->values();

        foreach ($agentIdsOnPage as $idAgent) {
            $financements[(int) $idAgent] = $this->financementService->soldeFinancementAgent((int) $idAgent);
        }

        $statsQuery = BordereauAgent::query();
        if ($agentIds !== []) {
            $statsQuery->whereIn('id_agent', $agentIds);
        }

        return [
            'bordereaux' => $bordereaux,
            'financements' => $financements,
            'stats' => $this->statsBordereaux($statsQuery),
        ];
    }

    private function dataTransporteurs(array $filters): array
    {
        $query = BordereauTransporteur::query()
            ->orderByDesc('date_generation')
            ->orderByDesc('id');

        if ($filters['q'] !== '') {
            $q = $filters['q'];
            $query->where(function ($builder) use ($q) {
                $builder->where('numero', 'like', "%{$q}%")
                    ->orWhere('transporteur_nom', 'like', "%{$q}%")
                    ->orWhere('transporteur_code', 'like', "%{$q}%");
            });
        }

        $this->appliquerStatutBordereau($query, $filters['statut']);

        $bordereauxTransporteur = $query->paginate(25)->withQueryString();

        $avances = [];
        $transporteurIds = $bordereauxTransporteur->getCollection()
            ->pluck('transporteur_id')
            ->unique()
            ->filter()
            ->values();

        if ($transporteurIds->isNotEmpty()) {
            // Query builder (pas Eloquent) : le modèle AvanceTransporteur a un
            // accessor `solde` qui écraserait l'alias SQL (montant/utilise absents).
            $soldes = DB::table('avances_transporteur')
                ->whereIn('transporteur_id', $transporteurIds)
                ->groupBy('transporteur_id')
                ->selectRaw('transporteur_id, COALESCE(SUM(GREATEST(montant - montant_utilise, 0)), 0) as solde')
                ->pluck('solde', 'transporteur_id');

            foreach ($transporteurIds as $id) {
                $avances[(int) $id] = (int) round((float) ($soldes[(int) $id] ?? $soldes[(string) $id] ?? 0));
            }
        }

        return [
            'bordereauxTransporteur' => $bordereauxTransporteur,
            'avancesTransporteurs' => $avances,
            'stats' => $this->statsBordereaux(BordereauTransporteur::query()),
        ];
    }

    private function dataFournisseurs(array $filters): array
    {
        $fournisseurs = Fournisseur::with(['service', 'paiements'])->orderBy('nom')->get();

        if ($filters['q'] !== '') {
            $q = mb_strtolower($filters['q']);
            $fournisseurs = $fournisseurs->filter(
                fn ($f) => str_contains(mb_strtolower($f->nom), $q)
            )->values();
        }

        $lignes = $fournisseurs->map(function ($fournisseur) {
            $montantDu = (float) Depense::where('description', $fournisseur->nom)->sum('montant');
            $montantPaye = (float) $fournisseur->paiements->sum('montant');

            return [
                'fournisseur' => $fournisseur,
                'montant_du' => $montantDu,
                'montant_paye' => $montantPaye,
                'reste_a_payer' => $montantDu - $montantPaye,
            ];
        });

        $stats = [
            'total' => $lignes->count(),
            'a_payer' => $lignes->filter(fn ($l) => $l['reste_a_payer'] > 0)->count(),
            'reste_total' => (float) $lignes->sum(fn ($l) => max(0, $l['reste_a_payer'])),
        ];

        if ($filters['statut'] === 'a_payer') {
            $lignes = $lignes->filter(fn ($l) => $l['reste_a_payer'] > 0)->values();
        } elseif ($filters['statut'] === 'soldes') {
            $lignes = $lignes->filter(fn ($l) => $l['reste_a_payer'] <= 0)->values();
        }

        return [
            'fournisseursData' => $lignes,
            'stats' => $stats,
        ];
    }

    private function dataFinancements(array $filters): array
    {
        $query = DemandeAvance::query()
            ->orderByDesc('date_demande')
            ->orderByDesc('id');

        if ($filters['q'] !== '') {
            $q = $filters['q'];
            $query->where(function ($builder) use ($q) {
                $builder->where('agent_nom', 'like', "%{$q}%")
                    ->orWhere('agent_numero', 'like', "%{$q}%")
                    ->orWhere('reference', 'like', "%{$q}%")
                    ->orWhere('commentaire', 'like', "%{$q}%");
            });
        }

        if ($filters['statut'] === 'a_payer') {
            $query->where('statut', DemandeAvance::STATUT_EN_ATTENTE);
        } elseif ($filters['statut'] === 'soldes') {
            $query->where('statut', DemandeAvance::STATUT_PAYEE);
        }

        $demandes = $query->paginate(25)->withQueryString();

        $statsQuery = DemandeAvance::query();

        return [
            'demandesFinancement' => $demandes,
            'stats' => [
                'total' => (clone $statsQuery)->count(),
                'a_payer' => (clone $statsQuery)
                    ->where('statut', DemandeAvance::STATUT_EN_ATTENTE)
                    ->count(),
                'reste_total' => (float) (clone $statsQuery)
                    ->where('statut', DemandeAvance::STATUT_EN_ATTENTE)
                    ->sum('montant'),
            ],
        ];
    }

    private function dataSalaires(Request $request, array $filters): array
    {
        $annee = (int) $request->input('annee', now()->year);
        $mois = max(1, min(12, (int) $request->input('mois', now()->month)));

        $this->salaireService->ensurePeriodesMois($annee, $mois);

        $lignes = collect();
        foreach ($this->salaireService->chauffeursPgf() as $chauffeur) {
            $periode = $this->salaireService->ensurePeriode($chauffeur, $annee, $mois);
            $soldes = $this->salaireService->calculPeriode($chauffeur, $periode);

            $lignes->push([
                'chauffeur' => $chauffeur,
                'periode' => $periode,
                'soldes' => $soldes,
            ]);
        }

        if ($filters['q'] !== '') {
            $q = mb_strtolower($filters['q']);
            $lignes = $lignes->filter(function ($l) use ($q) {
                $nom = mb_strtolower(trim($l['chauffeur']->nom . ' ' . $l['chauffeur']->prenoms));

                return str_contains($nom, $q)
                    || str_contains(mb_strtolower((string) $l['chauffeur']->matricule_vehicule), $q);
            })->values();
        }

        $stats = [
            'total' => $lignes->count(),
            'a_payer' => $lignes->filter(fn ($l) => $l['soldes']['reste'] > 0)->count(),
            'reste_total' => (float) $lignes->sum(fn ($l) => max(0, $l['soldes']['reste'])),
        ];

        if ($filters['statut'] === 'a_payer') {
            $lignes = $lignes->filter(fn ($l) => $l['soldes']['reste'] > 0)->values();
        } elseif ($filters['statut'] === 'soldes') {
            $lignes = $lignes->filter(fn ($l) => $l['soldes']['reste'] <= 0)->values();
        }

        return [
            'salairesLignes' => $lignes,
            'salairesAnnee' => $annee,
            'salairesMois' => $mois,
            'salairesLibelleMois' => $this->salaireService->libelleMois($mois, $annee),
            'stats' => $stats,
        ];
    }

    private function appliquerStatutBordereau($query, string $statut): void
    {
        if ($statut === 'a_payer') {
            $query->whereRaw('COALESCE(montant_total, 0) > COALESCE(montant_paye, 0)');
        } elseif ($statut === 'soldes') {
            $query->whereRaw('COALESCE(montant_total, 0) <= COALESCE(montant_paye, 0)');
        }
    }

    private function statsBordereaux($statsQuery): array
    {
        return [
            'total' => (clone $statsQuery)->count(),
            'a_payer' => (clone $statsQuery)
                ->whereRaw('COALESCE(montant_total, 0) > COALESCE(montant_paye, 0)')
                ->count(),
            'reste_total' => (float) (clone $statsQuery)
                ->selectRaw('COALESCE(SUM(GREATEST(COALESCE(montant_total, 0) - COALESCE(montant_paye, 0), 0)), 0) as reste')
                ->value('reste'),
        ];
    }
}
