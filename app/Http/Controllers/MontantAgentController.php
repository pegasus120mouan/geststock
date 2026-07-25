<?php

namespace App\Http\Controllers;

use App\Models\BordereauAgent;
use App\Models\FicheSortie;
use App\Models\PaiementAgent;
use App\Models\Produit;
use App\Models\Ticket;
use App\Models\User;
use App\Services\BordereauAgentService;
use App\Services\CaisseService;
use App\Services\ChefEquipeSession;
use App\Services\FinancementService;
use App\Services\MesAgentsService;
use App\Services\MontantAgentFicheService;
use App\Services\MontantAgentReportingService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class MontantAgentController extends Controller
{
    public function __construct(
        private MontantAgentReportingService $reporting,
        private MontantAgentFicheService $montantAgentFiche,
        private BordereauAgentService $bordereauAgent,
        private MesAgentsService $mesAgentsService,
        private FinancementService $financementService,
        private CaisseService $caisseService,
    ) {}

    public function index(Request $request)
    {
        $filtres = $this->reporting->filtresDepuisRequest($request);
        $options = $this->reporting->optionsFiltres();
        $search = trim((string) $request->query('q', ''));
        $agents = $this->fetchAgentsFromApi($search !== '' ? $search : null);
        $data = [];

        if ($agents === null) {
            return view('gestion_financiere.montant_agent', [
                'data' => [],
                'external_error' => 'Impossible de charger la liste des agents. Vérifiez l’API agents et la connexion réseau, puis rechargez la page.',
                'search' => trim((string) $request->query('q', '')),
                'agentNoms' => [],
                'filtres' => $filtres,
                'filtresActifs' => false,
                'produits' => $options['produits'],
                'usines' => $options['usines'],
            ]);
        }

        $agentIds = array_values(array_filter(array_map(
            static fn (array $agent): int => (int) ($agent['id_agent'] ?? 0),
            $agents
        )));
        $soldesFinancement = $this->financementService->soldesFinancementByAgentIds($agentIds);

        foreach ($agents as $agent) {
            $idAgent = (int) ($agent['id_agent'] ?? 0);
            if ($idAgent <= 0) {
                continue;
            }

            $montantDuGlobal = (int) round($this->reporting->calculerMontantDuAgentPourIndex($idAgent, ['id_agent' => $idAgent]));
            $montantDu = $this->reporting->filtresActifs($filtres)
                ? (int) round($this->reporting->calculerMontantDuAgentPourIndex($idAgent, $filtres))
                : $montantDuGlobal;
            $filtresActifs = $this->reporting->filtresActifs($filtres);

            if ($filtresActifs && $montantDu === 0) {
                continue;
            }

            $montantFinancement = (int) ($soldesFinancement[$idAgent] ?? 0);

            $data[] = [
                'agent' => $agent,
                'montant_du' => $montantDu,
                'montant_du_global' => $montantDuGlobal,
                'montant_paye' => $this->montantPayeBordereauxAgent($idAgent),
                'montant_avances' => $this->montantAvancesAgent($idAgent),
                'montant_financement' => $montantFinancement,
                'reste_a_payer' => $this->resteAPayerAgent(
                    $idAgent,
                    $montantDuGlobal,
                    $montantFinancement
                ),
            ];
        }

        usort($data, function ($a, $b) {
            return strcasecmp(
                (string) ($a['agent']['nom_complet'] ?? ''),
                (string) ($b['agent']['nom_complet'] ?? '')
            );
        });

        $agentNoms = collect($data)
            ->map(function ($item) {
                $agent = $item['agent'];
                $nom = trim((string) ($agent['nom_complet'] ?? ''));
                if ($nom === '') {
                    $nom = trim(($agent['nom_agent'] ?? '') . ' ' . ($agent['prenom_agent'] ?? ''));
                }

                return $nom;
            })
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();

        if ($search !== '') {
            $needle = mb_strtolower($search);
            $data = array_values(array_filter($data, function ($item) use ($needle) {
                $agent = $item['agent'];
                $nomComplet = mb_strtolower(trim((string) ($agent['nom_complet'] ?? '')));
                if ($nomComplet === '') {
                    $nomComplet = mb_strtolower(trim(($agent['nom_agent'] ?? '') . ' ' . ($agent['prenom_agent'] ?? '')));
                }
                $numeroAgent = mb_strtolower((string) ($agent['numero_agent'] ?? ''));

                return str_contains($nomComplet, $needle) || str_contains($numeroAgent, $needle);
            }));
        }

        return view('gestion_financiere.montant_agent', [
            'data' => $data,
            'external_error' => null,
            'search' => $search,
            'agentNoms' => $agentNoms,
            'filtres' => $filtres,
            'filtresActifs' => $this->reporting->filtresActifs($filtres),
            'produits' => $options['produits'],
            'usines' => $options['usines'],
        ]);
    }

    public function syntheseProduit(Request $request)
    {
        $filtres = $this->reporting->filtresDepuisRequest($request);
        $options = $this->reporting->optionsFiltres();
        $synthese = $this->reporting->syntheseParProduit($filtres);

        $totaux = [
            'montant' => (int) collect($synthese)->sum('montant_total'),
            'poids' => (float) collect($synthese)->sum('poids_total'),
            'fiches' => (int) collect($synthese)->sum('nb_fiches'),
        ];

        return view('gestion_financiere.synthese_produit', [
            'synthese' => $synthese,
            'filtres' => $filtres,
            'filtresActifs' => $this->reporting->filtresActifs($filtres),
            'produits' => $options['produits'],
            'usines' => $options['usines'],
            'totaux' => $totaux,
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>|null
     */
    private function fetchAgentsFromApi(?string $search = null): ?array
    {
        $params = ['per_page' => 100];
        if ($search !== null && $search !== '') {
            $params['search'] = $search;
        }

        $agents = $this->mesAgentsService->fetchAllAgents($params);
        if ($agents === []) {
            $probe = $this->mesAgentsService->listAgents(array_merge($params, ['page' => 1]));
            if ($probe['error']) {
                return null;
            }
        }

        return $agents;
    }

    public function show(Request $request, int $id_agent)
    {
        $agent = $this->findAgentById($id_agent);
        if (!$agent) {
            return redirect()->route('gestionfinanciere.montant_agent')
                ->withErrors(['error' => 'Agent non trouvé.']);
        }

        $filtres = $this->reporting->filtresDepuisRequest($request);
        $filtres['id_agent'] = $id_agent;

        $this->reporting->synchroniserTicketsAgent($id_agent, $request);

        $montantDu = (int) round($this->reporting->calculerMontantDuAgent($id_agent, $filtres));
        $montantDuGlobal = (int) round($this->reporting->calculerMontantDuAgent($id_agent, ['id_agent' => $id_agent]));
        $paiements = PaiementAgent::where('id_agent', $id_agent)
            ->with('bordereau')
            ->orderBy('date_paiement', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        $demandesAvanceEnAttente = \App\Models\DemandeAvance::query()
            ->where('id_agent', $id_agent)
            ->where('statut', \App\Models\DemandeAvance::STATUT_EN_ATTENTE)
            ->orderByDesc('date_demande')
            ->orderByDesc('id')
            ->get();

        $historiquePaiements = collect();
        foreach ($demandesAvanceEnAttente as $demande) {
            $historiquePaiements->push((object) [
                'kind' => 'demande',
                'date' => $demande->date_demande,
                'date_sort' => optional($demande->date_demande)->format('Y-m-d') ?: '1970-01-01',
                'id_sort' => (int) $demande->id,
                'bordereau_label' => null,
                'is_avance' => true,
                'mode' => $demande->mode_paiement,
                'montant' => (float) $demande->montant,
                'statut' => 'en_attente',
                'statut_label' => 'En attente de paiement',
                'pdf_url' => null,
                'compte_label' => 'Caisse Unipalm',
            ]);
        }
        foreach ($paiements as $paiement) {
            $isAvance = $paiement->id_bordereau === null;
            $compte = (string) ($paiement->caisse ?? '');
            $historiquePaiements->push((object) [
                'kind' => 'paiement',
                'date' => $paiement->date_paiement,
                'date_sort' => optional($paiement->date_paiement)->format('Y-m-d') ?: '1970-01-01',
                'id_sort' => (int) $paiement->id,
                'is_avance' => $isAvance,
                'bordereau_label' => $isAvance ? null : ($paiement->bordereau?->numero),
                'mode' => $paiement->mode_paiement,
                'montant' => (float) $paiement->montant,
                'statut' => 'paye',
                'statut_label' => 'Payé',
                'pdf_url' => route('gestionfinanciere.recus.pdf', $paiement->id),
                'compte_label' => $compte === 'api'
                    ? 'Caisse Unipalm'
                    : ($compte === 'local' ? 'Caisse locale' : null),
            ]);
        }
        $historiquePaiements = $historiquePaiements
            ->sortByDesc(fn ($row) => sprintf('%s-%010d', $row->date_sort, $row->id_sort))
            ->values();

        $montantPaye = $this->montantPayeAgent($id_agent);
        $montantPayeBordereaux = $this->montantPayeBordereauxAgent($id_agent);
        $montantAvances = $this->montantAvancesAgent($id_agent);
        $statsFinancement = $this->financementService->statsForAgent($id_agent);
        $montantFinancement = (int) round($statsFinancement['solde_financement'] ?? 0);
        $resteAPayer = $this->resteAPayerAgent($id_agent, $montantDuGlobal, $montantFinancement);
        $soldeCaisseLocale = (int) round($this->caisseService->getSolde());

        $fichesAvecMontant = $this->reporting->fichesAvecMontant($filtres);
        $groupesProduitUsine = $this->reporting->grouperParProduitEtUsine($fichesAvecMontant);
        $options = $this->reporting->optionsFiltres();
        $bordereaux = BordereauAgent::where('id_agent', $id_agent)
            ->orderBy('date_generation', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        $nomComplet = trim((string) ($agent['nom_complet'] ?? ''));
        if ($nomComplet === '') {
            $nomComplet = trim(($agent['nom_agent'] ?? '') . ' ' . ($agent['prenom_agent'] ?? ''));
        }

        return view('gestion_financiere.agent_financier_detail', [
            'agent' => $agent,
            'exempleNumeroBordereau' => $this->bordereauAgent->exempleNumero(
                $agent['numero_agent'] ?? null,
                $nomComplet
            ),
            'fichesAvecMontant' => $fichesAvecMontant,
            'groupesProduitUsine' => $groupesProduitUsine,
            'bordereaux' => $bordereaux,
            'paiements' => $paiements,
            'historiquePaiements' => $historiquePaiements,
            'montantDu' => $montantDu,
            'montantDuGlobal' => $montantDuGlobal,
            'montantPaye' => $montantPayeBordereaux,
            'montantPayeTotal' => $montantPaye,
            'montantPayeBordereaux' => $montantPayeBordereaux,
            'montantAvances' => $montantAvances,
            'montantFinancement' => $montantFinancement,
            'soldeCaisseLocale' => $soldeCaisseLocale,
            'resteAPayer' => $resteAPayer,
            'filtres' => $filtres,
            'filtresActifs' => $this->reporting->filtresActifs($filtres),
            'produits' => $options['produits'],
            'usines' => $options['usines'],
            'queryFiltres' => $this->reporting->filtresPourUrl($filtres),
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findAgentById(int $id_agent): ?array
    {
        return $this->mesAgentsService->findAgentById($id_agent);
    }

    private function montantPayeBordereauxAgent(int $idAgent): int
    {
        return (int) round((float) BordereauAgent::where('id_agent', $idAgent)->sum('montant_paye'));
    }

    private function montantAvancesAgent(int $idAgent): int
    {
        return (int) round((float) PaiementAgent::where('id_agent', $idAgent)->whereNull('id_bordereau')->sum('montant'));
    }

    private function montantPayeAgent(int $idAgent): int
    {
        return $this->montantPayeBordereauxAgent($idAgent) + $this->montantAvancesAgent($idAgent);
    }

    /**
     * Reste dû : montant dû − paiements bordereaux − solde financement.
     */
    private function resteAPayerAgent(int $idAgent, int $montantDuGlobal, ?int $montantFinancement = null): int
    {
        $financement = $montantFinancement
            ?? (int) round($this->financementService->soldeFinancementAgent($idAgent));

        return $montantDuGlobal
            - $this->montantPayeBordereauxAgent($idAgent)
            - max(0, $financement);
    }

    public function updateProduitTicket(Request $request, int $id_agent, int $id_ticket)
    {
        if (! $this->findAgentById($id_agent)) {
            return redirect()->route('gestionfinanciere.montant_agent')
                ->withErrors(['error' => 'Agent non trouvé.']);
        }

        $validated = $request->validate([
            'produit_id' => ['required', 'integer', 'exists:produits,id'],
        ]);

        $ticket = Ticket::query()
            ->where('id_ticket', $id_ticket)
            ->where(function ($query) use ($id_agent) {
                $query->where('id_agent', $id_agent)
                    ->orWhereHas('validation')
                    ->orWhere('conformite', 'valide');
            })
            ->firstOrFail();

        if ($ticket->bordereau_agent_id) {
            return back()->with('error', 'Impossible de modifier le produit : ticket déjà sur un bordereau.');
        }

        $fiche = $this->montantAgentFiche->fichePourTicket($ticket);
        if ($fiche?->bordereau_agent_id) {
            return back()->with('error', 'Impossible de modifier le produit : fiche déjà sur un bordereau.');
        }

        $fiche = $fiche ?? $this->montantAgentFiche->assurerFichePourTicketAgent($ticket);

        $produit = Produit::query()->findOrFail((int) $validated['produit_id']);

        $fiche->update([
            'produit_id' => $produit->id,
            'nom_produit' => $produit->nom,
        ]);

        return back()->with(
            'success',
            'Produit « ' . $produit->nom . ' » enregistré pour le ticket ' . ($ticket->numero_ticket ?: $ticket->id_ticket) . '.'
        );
    }

    public function storeAvance(Request $request, int $id_agent)
    {
        $agent = $this->findAgentById($id_agent);
        if (! $agent) {
            return redirect()->route('gestionfinanciere.montant_agent')
                ->withErrors(['error' => 'Agent non trouvé.']);
        }

        $request->merge([
            'montant' => preg_replace('/\s+/u', '', (string) $request->input('montant', '')),
        ]);

        $validated = $request->validate([
            'montant' => ['required', 'integer', 'min:1'],
            'date_paiement' => ['required', 'date'],
            'compte' => ['required', 'in:local,api'],
            'mode_paiement' => ['nullable', 'string', 'max:50'],
            'reference' => ['nullable', 'string', 'max:100'],
            'commentaire' => ['nullable', 'string', 'max:500'],
        ]);

        $montant = (int) $validated['montant'];
        $compte = $validated['compte'];
        $nomComplet = trim((string) ($agent['nom_complet'] ?? ''));
        if ($nomComplet === '') {
            $nomComplet = trim(($agent['nom_agent'] ?? '').' '.($agent['prenom_agent'] ?? ''));
        }

        // Compte API : demande envoyée vers Unipalm (onglet Financement).
        if ($compte === 'api') {
            \App\Models\DemandeAvance::create([
                'id_agent' => $id_agent,
                'agent_nom' => $nomComplet !== '' ? $nomComplet : null,
                'agent_numero' => $agent['numero_agent'] ?? null,
                'montant' => $montant,
                'date_demande' => $validated['date_paiement'],
                'mode_paiement' => $validated['mode_paiement'] ?? 'Espèces',
                'reference' => $validated['reference'] ?? null,
                'commentaire' => $validated['commentaire'] ?? 'Avance',
                'source' => \App\Models\DemandeAvance::SOURCE_API,
                'statut' => \App\Models\DemandeAvance::STATUT_EN_ATTENTE,
            ]);

            return redirect()
                ->route('gestionfinanciere.agent.show', ['id_agent' => $id_agent])
                ->with(
                    'success',
                    'Demande d\'avance de '.number_format($montant, 0, ',', ' ')
                    .' FCFA envoyée vers la caisse Unipalm. Elle apparaîtra dans l\'onglet Financement pour paiement.'
                );
        }

        // Compte local : débit immédiat de la caisse locale + enregistrement avance/financement.
        $soldeCaisse = (int) round($this->caisseService->getSolde());
        if ($montant > $soldeCaisse) {
            $message = 'Solde de la caisse locale insuffisant. Disponible : '
                .number_format($soldeCaisse, 0, ',', ' ').' FCFA.';

            return back()
                ->withErrors(['montant' => $message])
                ->with('error', $message)
                ->withInput();
        }

        $user = $this->resolveOptionalUser($request);

        try {
            $paiement = DB::transaction(function () use ($validated, $montant, $id_agent, $user, $nomComplet, $agent) {
                $paiement = PaiementAgent::create([
                    'id_agent' => $id_agent,
                    'id_bordereau' => null,
                    'montant' => $montant,
                    'date_paiement' => $validated['date_paiement'],
                    'mode_paiement' => $validated['mode_paiement'] ?? 'Espèces',
                    'caisse' => 'local',
                    'reference' => $validated['reference'] ?? null,
                    'commentaire' => $validated['commentaire'] ?? 'Avance',
                ]);

                app(\App\Services\RecuPaiementService::class)->assignerNumero($paiement);
                $paiement->refresh();

                $this->caisseService->debiter(
                    $montant,
                    'Avance agent '.($nomComplet !== '' ? $nomComplet : '#'.$id_agent),
                    $user,
                    'Local',
                );

                $this->financementService->enregistrerFinancementDepuisAvance($paiement);

                \App\Models\DemandeAvance::create([
                    'id_agent' => $id_agent,
                    'agent_nom' => $nomComplet !== '' ? $nomComplet : null,
                    'agent_numero' => $agent['numero_agent'] ?? null,
                    'montant' => $montant,
                    'date_demande' => $validated['date_paiement'],
                    'mode_paiement' => $validated['mode_paiement'] ?? 'Espèces',
                    'reference' => $validated['reference'] ?? null,
                    'commentaire' => $validated['commentaire'] ?? 'Avance',
                    'source' => \App\Models\DemandeAvance::SOURCE_LOCAL,
                    'statut' => \App\Models\DemandeAvance::STATUT_PAYEE,
                    'paiement_agent_id' => $paiement->id,
                    'payee_at' => now(),
                    'payee_par' => $user
                        ? trim(($user->name ?? '').' '.($user->prenom ?? ''))
                        : 'Caisse locale',
                ]);

                return $paiement;
            });
        } catch (\InvalidArgumentException $e) {
            return back()
                ->withErrors(['montant' => $e->getMessage()])
                ->with('error', $e->getMessage())
                ->withInput();
        }

        return redirect()
            ->route('gestionfinanciere.agent.show', ['id_agent' => $id_agent])
            ->with(
                'success',
                'Avance de '.number_format($montant, 0, ',', ' ')
                .' FCFA enregistrée et débitée de la caisse locale.'
            )
            ->with('recu_paiement_id', $paiement->id);
    }

    public function storePaiement(Request $request, int $id_agent)
    {
        return back()->withErrors([
            'error' => 'Les paiements doivent être enregistrés sur un bordereau (bouton paiement dans la section Gestion bordereaux).',
        ]);
    }

    public function storePaiementBordereau(Request $request, int $id_agent, int $id)
    {
        if (!$this->findAgentById($id_agent)) {
            return redirect()->route('gestionfinanciere.montant_agent')
                ->withErrors(['error' => 'Agent non trouvé.']);
        }

        $bordereau = BordereauAgent::where('id_agent', $id_agent)->findOrFail($id);

        $request->merge([
            'montant' => preg_replace('/\s+/u', '', (string) $request->input('montant', '')),
        ]);

        $validated = $request->validate([
            'montant' => ['required', 'integer', 'min:1'],
            'date_paiement' => ['required', 'date'],
            'mode_paiement' => ['nullable', 'string', 'max:50'],
            'reference' => ['nullable', 'string', 'max:100'],
            'commentaire' => ['nullable', 'string', 'max:500'],
        ]);

        $montant = (int) $validated['montant'];
        $soldeFinancement = $this->financementService->soldeFinancementAgent($id_agent);
        $soldeCaisseLocale = (int) round($this->caisseService->getSolde());
        $resteBordereau = (int) round($bordereau->reste_a_payer);

        // Priorité : financement agent s'il existe, sinon caisse locale (Gestion de caisse).
        $source = $soldeFinancement > 0 ? 'financement' : 'local';

        if ($source === 'financement') {
            $plafond = $resteBordereau > 0
                ? min($resteBordereau, $soldeFinancement)
                : $soldeFinancement;
        } else {
            $plafond = $resteBordereau > 0
                ? min($resteBordereau, max(0, $soldeCaisseLocale))
                : max(0, $soldeCaisseLocale);
        }

        if ($plafond <= 0) {
            $message = $source === 'local'
                ? 'Solde de la caisse locale insuffisant pour effectuer ce paiement.'
                : 'Financement insuffisant pour effectuer ce paiement.';

            return back()
                ->withErrors(['montant' => $message])
                ->with('error', $message)
                ->withInput();
        }

        if ($montant > $plafond) {
            $message = $source === 'financement'
                ? 'Le paiement ne peut pas dépasser le financement disponible ('
                    . number_format($soldeFinancement, 0, ',', ' ')
                    . ' FCFA). Maximum autorisé : '
                    . number_format($plafond, 0, ',', ' ')
                    . ' FCFA.'
                : 'Le paiement ne peut pas dépasser le solde de la caisse locale ('
                    . number_format($soldeCaisseLocale, 0, ',', ' ')
                    . ' FCFA). Maximum autorisé : '
                    . number_format($plafond, 0, ',', ' ')
                    . ' FCFA.';

            return back()
                ->withErrors(['montant' => $message])
                ->with('error', $message)
                ->withInput();
        }

        $user = $this->resolveOptionalUser($request);

        try {
            DB::transaction(function () use ($validated, $montant, $source, $id_agent, $bordereau, $user) {
                $paiement = PaiementAgent::create([
                    'id_agent' => $id_agent,
                    'id_bordereau' => $bordereau->id,
                    'montant' => $montant,
                    'date_paiement' => $validated['date_paiement'],
                    'mode_paiement' => $source === 'financement'
                        ? 'Remboursement'
                        : ($validated['mode_paiement'] ?: 'Caisse locale'),
                    'caisse' => $source,
                    'reference' => $validated['reference'] ?? null,
                    'commentaire' => $validated['commentaire'] ?? null,
                ]);

                app(\App\Services\RecuPaiementService::class)->assignerNumero($paiement);

                $bordereau->update([
                    'montant_paye' => (float) $bordereau->montant_paye + $montant,
                ]);

                if ($source === 'financement') {
                    $this->financementService->deduireFinancementPourPaiementBordereau($paiement, $bordereau);
                } else {
                    $this->caisseService->debiter(
                        $montant,
                        'Paiement bordereau '.$bordereau->numero,
                        $user,
                        'Local',
                    );
                }
            });
        } catch (\InvalidArgumentException $e) {
            return back()
                ->withErrors(['montant' => $e->getMessage()])
                ->with('error', $e->getMessage())
                ->withInput();
        }

        $message = 'Paiement de '.number_format($montant, 0, ',', ' ')
            .' FCFA enregistré pour le bordereau '.$bordereau->numero.'.';
        $message .= $source === 'financement'
            ? ' Déduit du financement agent.'
            : ' Débité de la caisse locale.';

        return back()->with('success', $message);
    }

    private function resolveOptionalUser(Request $request): ?User
    {
        $user = $request->user();
        if ($user instanceof User) {
            return $user;
        }

        try {
            $chef = app(ChefEquipeSession::class)->chef($request);
        } catch (\Throwable) {
            return null;
        }

        if (! $chef) {
            return null;
        }

        $idChef = (int) ($chef['id_chef'] ?? 0);
        $login = trim((string) ($chef['login'] ?? ''));

        $userQuery = User::query();
        if ($idChef > 0) {
            $userQuery->where('id_chef', $idChef);
        }
        if ($login !== '') {
            $userQuery->when(
                $idChef > 0,
                fn ($q) => $q->orWhere('login', $login),
                fn ($q) => $q->where('login', $login),
            );
        }

        $existing = $userQuery->first();
        if ($existing) {
            return $existing;
        }

        if ($idChef <= 0 && $login === '') {
            return null;
        }

        $loginToUse = $login !== '' ? $login : 'chef-'.$idChef;
        if (User::query()->where('login', $loginToUse)->exists()) {
            $loginToUse = 'chef-'.$idChef.'-'.Str::lower(Str::random(4));
        }

        return User::create([
            'name' => (string) ($chef['nom'] ?? 'Chef'),
            'prenom' => $chef['prenoms'] ?? null,
            'login' => $loginToUse,
            'id_chef' => $idChef > 0 ? $idChef : null,
            'chef_equipe_token' => (string) ($chef['token'] ?? ''),
            'password' => Hash::make(Str::random(32)),
            'role' => 'agent',
        ]);
    }

    public function fichesEligiblesBordereau(Request $request, int $id_agent)
    {
        if (!$this->findAgentById($id_agent)) {
            return response()->json(['message' => 'Agent non trouvé.'], 404);
        }

        $validated = $request->validate([
            'date_debut' => ['required', 'date'],
            'date_fin' => ['required', 'date', 'after_or_equal:date_debut'],
        ]);

        $fiches = $this->bordereauAgent->fichesEligibles(
            $id_agent,
            $validated['date_debut'],
            $validated['date_fin']
        );

        return response()->json([
            'fiches' => $fiches,
            'total_montant' => (int) collect($fiches)->sum('montant'),
            'total_poids' => (float) collect($fiches)->sum('poids'),
        ]);
    }

    public function storeBordereau(Request $request, int $id_agent)
    {
        $agent = $this->findAgentById($id_agent);
        if (!$agent) {
            return redirect()->route('gestionfinanciere.montant_agent')
                ->withErrors(['error' => 'Agent non trouvé.']);
        }

        $validated = $request->validate([
            'date_debut' => ['required', 'date'],
            'date_fin' => ['required', 'date', 'after_or_equal:date_debut'],
            'fiche_ids' => ['nullable', 'array'],
            'fiche_ids.*' => ['integer'],
            'ticket_ids' => ['required', 'array', 'min:1'],
            'ticket_ids.*' => ['integer'],
        ]);

        $ticketIds = $validated['ticket_ids'] ?? $validated['fiche_ids'] ?? [];
        $lignesData = $this->bordereauAgent->construireLignesData($id_agent, $ticketIds);

        if ($lignesData === []) {
            return back()->withErrors(['error' => 'Aucun ticket valide sélectionné (déjà bordereau ou introuvable).']);
        }

        $nomComplet = trim((string) ($agent['nom_complet'] ?? ''));
        if ($nomComplet === '') {
            $nomComplet = trim(($agent['nom_agent'] ?? '') . ' ' . ($agent['prenom_agent'] ?? ''));
        }

        $bordereau = BordereauAgent::create([
            'id_agent' => $id_agent,
            'numero' => $this->bordereauAgent->genererNumero(
                $agent['numero_agent'] ?? null,
                $nomComplet
            ),
            'agent_nom' => $nomComplet,
            'agent_numero' => $agent['numero_agent'] ?? null,
            'date_generation' => now()->toDateString(),
            'date_debut' => $validated['date_debut'],
            'date_fin' => $validated['date_fin'],
            'montant_total' => collect($lignesData)->sum('montant'),
            'poids_total' => collect($lignesData)->sum('poids'),
            'fiches_data' => $lignesData,
        ]);

        $this->bordereauAgent->assignerLignesAuBordereau($bordereau, $lignesData);

        return redirect()->route('gestionfinanciere.agent.bordereau.show', [
            'id_agent' => $id_agent,
            'id' => $bordereau->id,
        ])->with('success', 'Bordereau ' . $bordereau->numero . ' généré avec succès.');
    }

    public function showBordereau(int $id_agent, int $id)
    {
        $agent = $this->findAgentById($id_agent);
        if (!$agent) {
            return redirect()->route('gestionfinanciere.montant_agent')
                ->withErrors(['error' => 'Agent non trouvé.']);
        }

        $bordereau = BordereauAgent::where('id_agent', $id_agent)->findOrFail($id);
        $groupesUsine = $this->bordereauAgent->grouperParUsine($bordereau->fiches_data ?? []);

        return view('gestion_financiere.bordereau_agent_show', [
            'agent' => $agent,
            'bordereau' => $bordereau,
            'groupesUsine' => $groupesUsine,
        ]);
    }

    public function exportBordereauPdf(int $id_agent, int $id)
    {
        $agent = $this->findAgentById($id_agent);
        if (!$agent) {
            abort(404);
        }

        $bordereau = BordereauAgent::where('id_agent', $id_agent)->findOrFail($id);
        $groupesUsine = $this->bordereauAgent->grouperParUsine($bordereau->fiches_data ?? []);

        $nomComplet = trim((string) ($agent['nom_complet'] ?? ''));
        if ($nomComplet === '') {
            $nomComplet = trim(($agent['nom_agent'] ?? '') . ' ' . ($agent['prenom_agent'] ?? ''));
        }

        $logoPath = public_path('img/logo/logo.png');
        if (!file_exists($logoPath)) {
            $logoPath = null;
        }

        $pdf = Pdf::loadView('gestion_financiere.bordereau_agent_pdf', [
            'bordereau' => $bordereau,
            'groupesUsine' => $groupesUsine,
            'agentNom' => $nomComplet,
            'agentNumero' => $agent['numero_agent'] ?? '',
            'logoPath' => $logoPath,
            'dateCreation' => ($bordereau->created_at ?? now())->format('d/m/Y \à H:i'),
        ]);

        $pdf->setPaper('A4', 'portrait');

        $filename = 'bordereau_' . preg_replace('/[^a-zA-Z0-9_-]+/', '_', $bordereau->numero) . '.pdf';

        return $pdf->stream($filename);
    }

    public function destroyBordereau(int $id_agent, int $id)
    {
        $bordereau = BordereauAgent::where('id_agent', $id_agent)->findOrFail($id);
        $this->bordereauAgent->libererFichesDuBordereau($bordereau);
        $bordereau->delete();

        return redirect()->route('gestionfinanciere.agent.show', ['id_agent' => $id_agent])
            ->with('success', 'Bordereau supprimé.');
    }
}
