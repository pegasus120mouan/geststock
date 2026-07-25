<?php

namespace App\Http\Controllers;

use App\Models\BordereauPgf;
use App\Models\FicheSortie;
use App\Models\Groupe;
use App\Models\GroupeAgent;
use App\Models\GroupeVehicule;
use App\Models\Parc;
use App\Models\ParticulierAgent;
use App\Models\ParticulierAgentPrix;
use App\Models\ParticulierGroupe;
use App\Models\PontEtat;
use App\Models\Produit;
use App\Models\Stock;
use App\Models\Ticket;
use App\Models\TicketValidation;
use App\Services\ChefEquipeContext;
use App\Services\FicheSortieDechargementService;
use App\Services\FicheSortieNumeroService;
use App\Services\FicheSortieTicketCorrespondanceService;
use App\Services\MesAgentsService;
use App\Services\MesTicketsService;
use App\Services\MontantAgentFicheService;
use App\Services\ParticulierAgentsApiService;
use App\Services\TicketPrixService;
use App\Services\TicketTransporteurFicheService;
use App\Services\UsinesParProduitService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\Rule;
use Illuminate\Database\UniqueConstraintViolationException;

class TicketController extends Controller
{
    public function __construct(
        private TicketPrixService $ticketPrixService,
        private ParticulierAgentsApiService $particulierAgentsApiService,
        private MesAgentsService $mesAgentsService,
        private MesTicketsService $mesTicketsService,
        private FicheSortieTicketCorrespondanceService $ficheTicketCorrespondance,
    ) {}

    public function index(Request $request)
    {
        $vehicule = trim((string) $request->query('vehicule', ''));
        $usine = trim((string) $request->query('usine', ''));
        $agent = trim((string) $request->query('agent', ''));
        $dateDebut = trim((string) $request->query('date_debut', ''));
        $dateFin = trim((string) $request->query('date_fin', ''));
        $statutPaiement = trim((string) $request->query('statut', ''));
        $enAttenteOnly = $statutPaiement === 'en_attente';
        $onlyCamionsPgf = $request->routeIs('camions.activites') || $request->boolean('camion_pgf');
        if ($onlyCamionsPgf && ! in_array($statutPaiement, ['', 'paye', 'non_paye'], true)) {
            $statutPaiement = '';
        }
        $page = max(1, (int) $request->query('page', 1));
        $perPage = 20;
        $hasFilters = $vehicule !== '' || $usine !== '' || $agent !== ''
            || $dateDebut !== '' || $dateFin !== ''
            || ($onlyCamionsPgf && in_array($statutPaiement, ['paye', 'non_paye'], true));

        $agentsApi = $this->mesAgentsService->fetchAllAgents([], $request);
        $externalError = null;
        $filteredTickets = null;

        if ($onlyCamionsPgf || $hasFilters || $enAttenteOnly) {
            $allTickets = $this->mesTicketsService->fetchAllTickets([], $request);
            $filteredTickets = $this->mesTicketsService->filterTickets($allTickets, $vehicule, $usine, $agent);
            if ($enAttenteOnly) {
                $filteredTickets = $this->mesTicketsService->filterTicketsNonValides($filteredTickets);
            }
            if ($onlyCamionsPgf) {
                $vehiculesPgfLookupEarly = $this->vehiculesPgfLookup();
                $filteredTickets = array_values(array_filter(
                    $filteredTickets,
                    function (array $ticket) use ($vehiculesPgfLookupEarly): bool {
                        return $this->vehiculeEstCamionPgf(
                            (int) ($ticket['vehicule_id'] ?? 0),
                            (string) ($ticket['matricule_vehicule'] ?? $ticket['matricule'] ?? ''),
                            $vehiculesPgfLookupEarly
                        );
                    }
                ));

                if ($dateDebut !== '' || $dateFin !== '') {
                    $filteredTickets = array_values(array_filter(
                        $filteredTickets,
                        function (array $ticket) use ($dateDebut, $dateFin): bool {
                            $raw = (string) ($ticket['date_ticket'] ?? '');
                            if ($raw === '') {
                                return false;
                            }
                            try {
                                $date = \Carbon\Carbon::parse($raw)->startOfDay();
                            } catch (\Throwable $e) {
                                return false;
                            }
                            if ($dateDebut !== '') {
                                try {
                                    if ($date->lt(\Carbon\Carbon::parse($dateDebut)->startOfDay())) {
                                        return false;
                                    }
                                } catch (\Throwable $e) {
                                }
                            }
                            if ($dateFin !== '') {
                                try {
                                    if ($date->gt(\Carbon\Carbon::parse($dateFin)->startOfDay())) {
                                        return false;
                                    }
                                } catch (\Throwable $e) {
                                }
                            }

                            return true;
                        }
                    ));
                }

                if (in_array($statutPaiement, ['paye', 'non_paye'], true)) {
                    $ids = array_values(array_filter(array_map(
                        static fn (array $t) => (int) ($t['id_ticket'] ?? 0),
                        $filteredTickets
                    )));
                    $numeros = array_values(array_filter(array_map(
                        static fn (array $t) => trim((string) ($t['numero_ticket'] ?? '')),
                        $filteredTickets
                    )));

                    $locaux = collect();
                    if ($ids !== [] || $numeros !== []) {
                        $locaux = Ticket::query()
                            ->where(function ($query) use ($ids, $numeros) {
                                if ($ids !== []) {
                                    $query->whereIn('id_ticket', $ids);
                                }
                                if ($numeros !== []) {
                                    if ($ids !== []) {
                                        $query->orWhereIn('numero_ticket', $numeros);
                                    } else {
                                        $query->whereIn('numero_ticket', $numeros);
                                    }
                                }
                            })
                            ->get(['id_ticket', 'numero_ticket', 'statut_ticket']);
                    }
                    $byId = $locaux->keyBy(fn ($t) => (int) $t->id_ticket);
                    $byNumero = $locaux->keyBy(fn ($t) => trim((string) $t->numero_ticket));

                    $filteredTickets = array_values(array_filter(
                        $filteredTickets,
                        function (array $ticket) use ($statutPaiement, $byId, $byNumero): bool {
                            $id = (int) ($ticket['id_ticket'] ?? 0);
                            $numero = trim((string) ($ticket['numero_ticket'] ?? ''));
                            $local = $byId->get($id) ?? ($numero !== '' ? $byNumero->get($numero) : null);
                            $raw = mb_strtolower(trim((string) ($local?->statut_ticket ?? $ticket['statut_ticket'] ?? 'non soldé')), 'UTF-8');
                            $estPaye = in_array($raw, ['soldé', 'solde', 'payé', 'paye'], true);

                            return $statutPaiement === 'paye' ? $estPaye : ! $estPaye;
                        }
                    ));
                }
            }
            $total = count($filteredTickets);
            $lastPage = max(1, (int) ceil($total / $perPage));
            $page = min($page, $lastPage);
            $ticketsApi = array_slice($filteredTickets, ($page - 1) * $perPage, $perPage);
            $pagination = [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => $lastPage,
            ];
        } else {
            $result = $this->mesTicketsService->listTickets(['page' => $page, 'per_page' => $perPage], $request);
            $ticketsApi = $result['tickets'];
            $pagination = $result['pagination'] ?? [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => count($ticketsApi),
                'last_page' => 1,
            ];
            $externalError = $result['error'];
        }

        // Usines : base locale d'abord, complément API mis en cache 5 min
        $timeout = 10;
        $usinesById = \App\Models\Usine::query()
            ->pluck('nom_usine', 'id_usine')
            ->all();

        try {
            $usinesUrl = (string) config('services.external_auth.mes_usines_url', 'https://api.objetombrepegasus.online/api/camions/mes_usines.php');
            $usinesApi = Cache::remember('mes_usines_api', 300, function () use ($usinesUrl, $timeout) {
                $all = [];
                $pageU = 1;
                $hasMoreU = true;

                while ($hasMoreU && $pageU <= 20) {
                    $usinesResponse = Http::acceptJson()
                        ->withoutVerifying()
                        ->timeout($timeout)
                        ->get($usinesUrl, ['page' => $pageU]);

                    if (! $usinesResponse->successful()) {
                        break;
                    }

                    $pageUsines = $usinesResponse->json('usines') ?? [];
                    if ($pageUsines === []) {
                        break;
                    }

                    $all = array_merge($all, $pageUsines);
                    $paginationU = $usinesResponse->json('pagination');
                    $currentPageU = (int) ($paginationU['current_page'] ?? $pageU);
                    $lastPageU = (int) ($paginationU['last_page'] ?? 1);
                    $hasMoreU = $currentPageU < $lastPageU;
                    $pageU++;
                }

                return $all;
            });

            foreach ($usinesApi as $u) {
                $idUsine = (int) ($u['id_usine'] ?? 0);
                if ($idUsine > 0 && ! isset($usinesById[$idUsine])) {
                    $usinesById[$idUsine] = (string) ($u['nom_usine'] ?? '');
                }
            }
        } catch (\Throwable $e) {}
        $agentsById = [];
        foreach ($agentsApi as $a) {
            $agentsById[$a['id_agent'] ?? 0] = $a['nom_complet'] ?? '';
        }

        $ticketIds = array_column($ticketsApi, 'id_ticket');
        $numerosTickets = array_values(array_filter(array_map(
            static fn (array $t) => trim((string) ($t['numero_ticket'] ?? '')),
            $ticketsApi
        )));
        $localTickets = ($ticketIds !== [] || $numerosTickets !== [])
            ? Ticket::with('particulierAgent.groupe')
                ->where(function ($query) use ($ticketIds, $numerosTickets) {
                    if ($ticketIds !== []) {
                        $query->whereIn('id_ticket', $ticketIds);
                    }
                    if ($numerosTickets !== []) {
                        if ($ticketIds !== []) {
                            $query->orWhereIn('numero_ticket', $numerosTickets);
                        } else {
                            $query->whereIn('numero_ticket', $numerosTickets);
                        }
                    }
                })
                ->get()
            : collect();
        $localTicketsById = $localTickets->keyBy('id_ticket');
        $localTicketsByNumero = $localTickets->keyBy('numero_ticket');

        $validations = ($ticketIds !== [] || $numerosTickets !== [])
            ? TicketValidation::query()
                ->where(function ($query) use ($ticketIds, $numerosTickets) {
                    if ($ticketIds !== []) {
                        $query->whereIn('id_ticket', $ticketIds);
                    }
                    if ($numerosTickets !== []) {
                        $ticketIds !== []
                            ? $query->orWhereIn('numero_ticket', $numerosTickets)
                            : $query->whereIn('numero_ticket', $numerosTickets);
                    }
                })
                ->get(['id_ticket', 'numero_ticket'])
            : collect();
        $validatedTicketIds = $validations->pluck('id_ticket')
            ->map(static fn ($id) => (int) $id)
            ->flip()
            ->all();
        $validatedNumeros = $validations->pluck('numero_ticket')
            ->map(static fn ($n) => trim((string) $n))
            ->filter()
            ->flip()
            ->all();

        $particulierAgentIds = $localTickets->pluck('particulier_agent_id')->filter()->unique()->values();
        $prixParticuliers = $particulierAgentIds->isNotEmpty()
            ? ParticulierAgentPrix::whereIn('particulier_agent_id', $particulierAgentIds)->get()
            : collect();

        $ticketsArray = [];
        foreach ($ticketsApi as $ticket) {
            $idTicket = (int) ($ticket['id_ticket'] ?? 0);
            $numeroTicket = trim((string) ($ticket['numero_ticket'] ?? ''));
            $local = $localTicketsById->get($idTicket);
            $localParNumero = (!$local && $numeroTicket !== '')
                ? $localTicketsByNumero->get($numeroTicket)
                : null;
            $localPourDonnees = $local ?? $localParNumero;

            $ticketsArray[] = [
                'id_ticket' => $idTicket,
                'numero_ticket' => $ticket['numero_ticket'] ?? '',
                'date_ticket' => $ticket['date_ticket'] ?? null,
                'matricule_vehicule' => $ticket['matricule_vehicule'] ?? '',
                'vehicule_id' => (int) ($ticket['vehicule_id'] ?? 0),
                'poids' => $ticket['poids'] ?? 0,
                'id_usine' => (int) ($ticket['id_usine'] ?? 0),
                'nom_usine' => $ticket['nom_usine'] ?: ($usinesById[$ticket['id_usine'] ?? 0] ?? '-'),
                'id_pont' => (int) ($ticket['id_pont'] ?? 0),
                'nom_pont' => (string) ($ticket['nom_pont'] ?? ''),
                'id_agent' => (int) ($ticket['id_agent'] ?? 0),
                'nom_agent' => $this->resolveNomAgentPourAffichage($ticket, $localPourDonnees, $agentsById),
                'nom_groupe' => $localPourDonnees?->particulierAgent?->groupe?->nom_groupe ?? '-',
                'particulier_agent_id' => $localPourDonnees?->particulier_agent_id,
                'prix_unitaire' => $ticket['prix_unitaire'] ?? $localPourDonnees?->prix_unitaire,
                'prix_unitaire_agent' => null,
                'montant_calcule' => null,
                'montant_paie' => $ticket['montant_paie'] ?? $localPourDonnees?->montant_paie,
                'statut_ticket' => $onlyCamionsPgf
                    ? ($localPourDonnees?->statut_ticket ?? $ticket['statut_ticket'] ?? 'non soldé')
                    : ($ticket['statut_ticket'] ?? $localPourDonnees?->statut_ticket),
                'created_at' => $ticket['created_at'] ?? ($localPourDonnees?->created_at?->format('Y-m-d H:i:s')),
                'conformite' => (isset($validatedTicketIds[$idTicket])
                    || ($numeroTicket !== '' && isset($validatedNumeros[$numeroTicket]))) ? 'valide' : null,
            ];
        }

        $vehiculesSource = ($hasFilters || $onlyCamionsPgf)
            ? ($filteredTickets ?? $ticketsApi)
            : $ticketsApi;
        $vehicules = collect($vehiculesSource)
            ->pluck('matricule_vehicule')
            ->filter()
            ->unique()
            ->values()
            ->all();

        // Récupérer les fiches de sortie associées aux tickets
        $ticketIds = array_column($ticketsArray, 'id_ticket');
        $fichesSortie = [];
        if (!empty($ticketIds)) {
            $fiches = FicheSortie::whereIn('id_ticket', $ticketIds)->get()->keyBy('id_ticket');
            foreach ($fiches as $idTicket => $fiche) {
                $fichesSortie[$idTicket] = [
                    'fiche_id' => $fiche->id,
                    'origine' => $fiche->nom_pont,
                    'date_chargement' => $fiche->date_chargement ? $fiche->date_chargement->format('d-m-Y') : '',
                    'poids_parc' => $fiche->poids_pont,
                    'prix_unitaire_transport' => $fiche->prix_unitaire_transport,
                    'poids_unitaire_regime' => $fiche->poids_unitaire_regime,
                    'nom_produit' => $fiche->nom_produit,
                    'produit_id' => $fiche->produit_id,
                    'id_agent' => $fiche->id_agent,
                    'nom_agent' => $fiche->nom_agent,
                ];
            }
        }

        $ticketsById = $localTicketsById;
        $usinesParProduitService = app(UsinesParProduitService::class);

        // Ajouter les infos de fiche de sortie et calculer le prix unitaire
        foreach ($ticketsArray as &$ticket) {
            $idTicket = $ticket['id_ticket'] ?? null;
            $produitId = null;
            $idAgentFiche = null;

            if ($idTicket && isset($fichesSortie[$idTicket])) {
                $ticket['fiche_id'] = $fichesSortie[$idTicket]['fiche_id'];
                $ticket['origine'] = $fichesSortie[$idTicket]['origine'];
                $ticket['date_chargement_fiche'] = $fichesSortie[$idTicket]['date_chargement'];
                $ticket['poids_parc'] = $fichesSortie[$idTicket]['poids_parc'];
                $ticket['prix_unitaire_transport'] = $fichesSortie[$idTicket]['prix_unitaire_transport'];
                $ticket['poids_unitaire_regime'] = $fichesSortie[$idTicket]['poids_unitaire_regime'];
                $ticket['nom_produit'] = $fichesSortie[$idTicket]['nom_produit'];
                $produitId = $fichesSortie[$idTicket]['produit_id'] ?? null;
                $idAgentFiche = !empty($fichesSortie[$idTicket]['id_agent'])
                    ? (int) $fichesSortie[$idTicket]['id_agent']
                    : null;
                if (($ticket['nom_agent'] ?? '-') === '-' && !empty($fichesSortie[$idTicket]['nom_agent'])) {
                    $ticket['nom_agent'] = $fichesSortie[$idTicket]['nom_agent'];
                }
            } else {
                $ticket['fiche_id'] = null;
                $ticket['origine'] = '';
                $ticket['date_chargement_fiche'] = '';
                $ticket['poids_parc'] = '';
                $ticket['prix_unitaire_transport'] = null;
                $ticket['poids_unitaire_regime'] = null;
                $ticket['nom_produit'] = null;
            }

            $modeleTicket = $ticketsById->get($idTicket) ?? $this->ticketModeleDepuisApi($ticket);
            $local = $ticketsById->get($idTicket);

            $nomUsine = trim((string) ($ticket['nom_usine'] ?? ''));
            if ($nomUsine === '' || $nomUsine === '-') {
                $nomUsine = $usinesById[$ticket['id_usine'] ?? 0] ?? '';
                if ($nomUsine !== '') {
                    $ticket['nom_usine'] = $nomUsine;
                }
            }

            if (! $produitId && $nomUsine !== '') {
                $produitInfo = $usinesParProduitService->produitPourUsine(
                    (int) ($ticket['id_usine'] ?? 0) ?: null,
                    $nomUsine
                );
                if ($produitInfo) {
                    $produitId = (int) $produitInfo['produit_id'];
                    if (empty($ticket['nom_produit'])) {
                        $ticket['nom_produit'] = $produitInfo['nom'];
                    }
                }
            }
            $ticket['produit_id'] = $produitId;

            $prixStocke = (float) ($local?->prix_unitaire ?? $ticket['prix_unitaire'] ?? 0);
            $montantStocke = (float) ($local?->montant_paie ?? $ticket['montant_paie'] ?? 0);

            if ($prixStocke > 0) {
                $prixUnitaireAgent = $prixStocke;
            } else {
                $prixUnitaireAgent = $this->ticketPrixService->prixUnitairePourTicket(
                    $modeleTicket,
                    $produitId,
                    $ticket['date_ticket'] ?? null,
                    $prixParticuliers,
                    $idAgentFiche,
                    $nomUsine !== '' ? $nomUsine : null,
                );
            }
            $ticket['prix_unitaire_agent'] = $prixUnitaireAgent;

            if ($montantStocke > 0) {
                $ticket['montant_calcule'] = $montantStocke;
            } elseif ($prixUnitaireAgent !== null && (float) ($ticket['poids'] ?? 0) > 0) {
                $ticket['montant_calcule'] = $prixUnitaireAgent * (float) $ticket['poids'];
            }

            // Activités PGF : prix uniquement manuel (jamais le calcul automatique).
            if ($onlyCamionsPgf) {
                $ticket['prix_unitaire_manuel'] = ($local && $local->prix_saisi_manuel)
                    ? (float) $local->prix_unitaire
                    : null;
                $ticket['montant_manuel'] = ($local && $local->prix_saisi_manuel && $local->montant_paie !== null)
                    ? (float) $local->montant_paie
                    : null;
                $ticket['bordereau_pgf_id'] = $local?->bordereau_pgf_id
                    ? (int) $local->bordereau_pgf_id
                    : null;
            }
        }
        unset($ticket);

        if ($onlyCamionsPgf) {
            $bordereauIds = collect($ticketsArray)
                ->pluck('bordereau_pgf_id')
                ->filter()
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values()
                ->all();
            $numerosBordereau = $bordereauIds === []
                ? collect()
                : BordereauPgf::query()->whereIn('id', $bordereauIds)->pluck('numero', 'id');

            foreach ($ticketsArray as &$ticket) {
                $bordereauId = $ticket['bordereau_pgf_id'] ?? null;
                $ticket['numero_bordereau'] = $bordereauId
                    ? (string) ($numerosBordereau[$bordereauId] ?? '')
                    : null;
                if ($ticket['numero_bordereau'] === '') {
                    $ticket['numero_bordereau'] = null;
                }
            }
            unset($ticket);
        }

        $vehiculesPgfLookup = $this->vehiculesPgfLookup();
        $fichesNonDechargees = $this->fichesNonDechargeesPourChef($request);
        foreach ($ticketsArray as &$ticket) {
            $ticket['est_camion_pgf'] = $this->vehiculeEstCamionPgf(
                (int) ($ticket['vehicule_id'] ?? 0),
                (string) ($ticket['matricule_vehicule'] ?? ''),
                $vehiculesPgfLookup
            );

            if ($ticket['est_camion_pgf']) {
                $nomUsineTicket = trim((string) ($ticket['nom_usine'] ?? ''));
                if ($nomUsineTicket === '' || $nomUsineTicket === '-') {
                    $ticket['nom_usine'] = $usinesById[$ticket['id_usine'] ?? 0] ?? '';
                }
                $ticket['fiches_correspondantes'] = $this->ficheTicketCorrespondance
                    ->filtrer($fichesNonDechargees, $this->ficheTicketCorrespondance->ticketDepuisApi($ticket))
                    ->all();
            } else {
                $ticket['fiches_correspondantes'] = [];
            }
        }
        unset($ticket);

        // Récupérer les véhicules depuis l'API pour le modal
        $vehiculesApi = [];
        try {
            $vehiculesResponse = Http::acceptJson()
                ->withoutVerifying()
                ->timeout($timeout)
                ->get('https://api.objetombrepegasus.online/api/camions/mes_camions.php');
            if ($vehiculesResponse->successful()) {
                $vehiculesApi = $vehiculesResponse->json('vehicules') ?? [];
            }
        } catch (\Throwable $e) {}

        // Récupérer les agents du groupe PGF depuis la base locale
        $groupePgf = Groupe::where('nom_groupe', 'Groupe PGF')->first();
        $agentsPgf = [];
        $vehiculesPgf = [];
        if ($groupePgf) {
            // Agents PGF
            $groupeAgents = GroupeAgent::where('groupe_id', $groupePgf->id)->get();
            $agentsById = [];
            foreach ($agentsApi as $a) {
                $agentsById[$a['id_agent'] ?? 0] = $a;
            }
            foreach ($groupeAgents as $ga) {
                if (isset($agentsById[$ga->id_agent])) {
                    $agentsPgf[] = $agentsById[$ga->id_agent];
                } else {
                    $agentsPgf[] = [
                        'id_agent' => $ga->id_agent,
                        'nom_complet' => 'Agent #' . $ga->id_agent,
                        'numero_agent' => $ga->type_agent ?? '',
                    ];
                }
            }
        }

        // Véhicules PGF - chercher dans tous les groupes contenant "PGF"
        $groupesPgf = Groupe::where('nom_groupe', 'like', '%PGF%')->pluck('id')->toArray();
        if (!empty($groupesPgf)) {
            $vehiculesPgfIds = GroupeVehicule::whereIn('groupe_id', $groupesPgf)->pluck('vehicule_id')->toArray();
            $vehiculesPgf = array_filter($vehiculesApi, function ($v) use ($vehiculesPgfIds) {
                return in_array($v['vehicules_id'] ?? 0, $vehiculesPgfIds);
            });
            $vehiculesPgf = array_values($vehiculesPgf);
        }

        $groupesParticuliers = ParticulierGroupe::with('agents')
            ->orderBy('nom_groupe')
            ->get();

        $agentsParGroupe = $this->particulierAgentsApiService->agentsParGroupePourSelect(
            $groupesParticuliers,
            $agentsApi
        );

        // Ponts gérables (depuis pont_etats)
        $pontEtatsGerables = PontEtat::where('gerable', true)->get();
        $idsPontsGerables = $pontEtatsGerables->pluck('id_pont')->toArray();

        // Récupérer les ponts API et filtrer les gérables
        $mesPontsUrl = (string) config('services.external_auth.mes_ponts_url');
        $pontsApi = [];
        try {
            $pontResponse = Http::acceptJson()->withoutVerifying()->timeout($timeout)->get($mesPontsUrl);
            if ($pontResponse->successful()) {
                $pontsApi = $pontResponse->json('ponts') ?? [];
            }
        } catch (\Throwable $e) {}

        $pontsGerables = array_values(array_filter($pontsApi, function ($p) use ($idsPontsGerables) {
            return in_array((int) ($p['id_pont'] ?? 0), $idsPontsGerables, true);
        }));

        // Tous les ponts annotés avec leur statut gérable
        $tousLesPonts = array_map(function ($p) use ($idsPontsGerables) {
            $p['gerable'] = in_array((int) ($p['id_pont'] ?? 0), $idsPontsGerables, true);
            return $p;
        }, $pontsApi);

        $pontsById = [];
        foreach ($pontsApi as $pont) {
            $idPont = (int) ($pont['id_pont'] ?? 0);
            if ($idPont > 0) {
                $pontsById[$idPont] = (string) ($pont['nom_pont'] ?? '');
            }
        }
        foreach (PontEtat::query()->get(['id_pont', 'nom_pont']) as $pontEtat) {
            $idPont = (int) $pontEtat->id_pont;
            if ($idPont > 0 && ($pontsById[$idPont] ?? '') === '' && ($pontEtat->nom_pont ?? '') !== '') {
                $pontsById[$idPont] = (string) $pontEtat->nom_pont;
            }
        }

        foreach ($ticketsArray as &$ticket) {
            $nomPont = trim((string) ($ticket['nom_pont'] ?? ''));
            if ($nomPont === '' || $nomPont === '-') {
                $idPont = (int) ($ticket['id_pont'] ?? 0);
                if ($idPont > 0 && ($pontsById[$idPont] ?? '') !== '') {
                    $ticket['nom_pont'] = $pontsById[$idPont];
                } elseif (! empty($ticket['origine'])) {
                    $ticket['nom_pont'] = (string) $ticket['origine'];
                }
            }
        }
        unset($ticket);

        // Produits locaux
        $produitsLocaux = Produit::orderBy('nom')->get();

        // Usines par produit pour le select dynamique
        $usinesParProduit = app(UsinesParProduitService::class)->usinesParProduitPourSelect();

        // Parcs par pont + produit (depuis stocks ouverts)
        // Structure : [ id_pont => [ produit_id => [ {id, nom, code} ] ] ]
        $parcsParPontProduit = [];
        $stocksOuverts = Stock::where('type', 'entree')
            ->where('statut', 'ouvert')
            ->whereNotNull('id_pont')
            ->whereNotNull('produit_id')
            ->with('parc')
            ->get();

        foreach ($stocksOuverts as $stock) {
            $idPont = (int) $stock->id_pont;
            $produitId = (int) $stock->produit_id;
            $parc = $stock->parc ?? Parc::find($stock->parc_id);
            if (!$parc) {
                continue;
            }
            $dejaPresent = collect($parcsParPontProduit[$idPont][$produitId] ?? [])
                ->contains(fn ($p) => (int) ($p['id'] ?? 0) === (int) $parc->id);
            if ($dejaPresent) {
                continue;
            }
            $parcsParPontProduit[$idPont][$produitId][] = [
                'id' => $parc->id,
                'nom' => $parc->nom,
                'code' => $parc->code,
            ];
        }

        return view('tickets.index', [
            'tickets' => $ticketsArray,
            'pagination' => $pagination,
            'vehicules' => $vehicules,
            'vehiculesApi' => $vehiculesApi,
            'vehiculesPgf' => $vehiculesPgf,
            'usines' => $usinesApi,
            'agents' => $agentsApi,
            'agentsPgf' => $agentsPgf,
            'groupesParticuliers' => $groupesParticuliers,
            'agentsParGroupe' => $agentsParGroupe,
            'pontsGerables' => $pontsGerables,
            'tousLesPonts' => $tousLesPonts,
            'produitsLocaux' => $produitsLocaux,
            'usinesParProduit' => $usinesParProduit,
            'parcsParPontProduit' => $parcsParPontProduit,
            'external_error' => $externalError,
            'enAttenteOnly' => $enAttenteOnly,
            'onlyCamionsPgf' => $onlyCamionsPgf,
            'ticketsIndexRoute' => $onlyCamionsPgf ? 'camions.activites' : 'tickets.index',
        ]);
    }

    /**
     * @return \Illuminate\Support\Collection<int, FicheSortie>
     */
    private function fichesNonDechargeesPourChef(Request $request)
    {
        $chefAgentIds = $this->mesAgentsService->chefAgentIds($request);
        if ($chefAgentIds === []) {
            return collect();
        }

        return FicheSortie::query()
            ->whereNull('date_dechargement')
            ->whereIn('id_agent', $chefAgentIds)
            ->orderByDesc('date_chargement')
            ->orderByDesc('id')
            ->get();
    }

    private function findTicketLocal(int $idTicket, string $numeroTicket = ''): ?Ticket
    {
        $ticket = Ticket::find($idTicket);
        if ($ticket) {
            return $ticket;
        }

        if ($numeroTicket !== '') {
            return Ticket::where('numero_ticket', $numeroTicket)->first();
        }

        return null;
    }

    private function realignerIdTicketLocal(int $ancienId, int $nouvelId): void
    {
        if ($ancienId === $nouvelId || $ancienId <= 0 || $nouvelId <= 0) {
            return;
        }

        if (Ticket::query()->where('id_ticket', $nouvelId)->exists()) {
            return;
        }

        DB::transaction(function () use ($ancienId, $nouvelId) {
            FicheSortie::query()->where('id_ticket', $ancienId)->update(['id_ticket' => $nouvelId]);
            TicketValidation::query()->where('id_ticket', $ancienId)->update(['id_ticket' => $nouvelId]);
            Ticket::query()->where('id_ticket', $ancienId)->update(['id_ticket' => $nouvelId]);
        });
    }

    private function ticketEstDejaValide(?Ticket $ticket, int $idTicket = 0, string $numeroTicket = ''): bool
    {
        if ($idTicket > 0 && TicketValidation::where('id_ticket', $idTicket)->exists()) {
            return true;
        }

        if ($ticket !== null && $ticket->estValide()) {
            return true;
        }

        $numeroTicket = trim($numeroTicket);

        return $numeroTicket !== ''
            && TicketValidation::where('numero_ticket', $numeroTicket)->exists();
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
     * @param  array{ids: array<int, true>, matricules: array<string, true>}|null  $lookup
     */
    private function vehiculeEstCamionPgf(int $vehiculeId, ?string $matricule, ?array $lookup = null): bool
    {
        $lookup ??= $this->vehiculesPgfLookup();

        if ($vehiculeId > 0 && isset($lookup['ids'][$vehiculeId])) {
            return true;
        }

        $matricule = strtoupper(trim((string) $matricule));

        return $matricule !== '' && isset($lookup['matricules'][$matricule]);
    }

    /**
     * @param  array<string, mixed>  $ticket
     */
    private function resoudreProduitIdPourTicket(
        array $ticket,
        ?int $produitFromFicheLiee,
        \Illuminate\Support\Collection $fichesNonDechargees
    ): ?int {
        if ($produitFromFicheLiee) {
            return (int) $produitFromFicheLiee;
        }

        $idAgent = (int) ($ticket['id_agent'] ?? 0);
        $vehicule = strtoupper(trim((string) ($ticket['matricule_vehicule'] ?? '')));
        $usine = trim((string) ($ticket['nom_usine'] ?? ''));
        if ($usine === '' || $usine === '-') {
            $usine = '';
        }

        if ($idAgent <= 0) {
            return null;
        }

        $candidat = $fichesNonDechargees->first(function (FicheSortie $f) use ($idAgent, $vehicule, $usine) {
            if ((int) $f->id_agent !== $idAgent || !$f->produit_id) {
                return false;
            }
            if ($vehicule !== '' && strcasecmp(trim((string) $f->matricule_vehicule), $vehicule) !== 0) {
                return false;
            }
            if ($usine !== '' && $f->usine && strcasecmp(trim((string) $f->usine), $usine) !== 0) {
                return false;
            }

            return true;
        });

        return $candidat?->produit_id ? (int) $candidat->produit_id : null;
    }

    /**
     * @param  array<string, mixed>  $ticket
     */
    private function ticketModeleDepuisApi(array $ticket): Ticket
    {
        $modele = new Ticket([
            'id_ticket' => (int) ($ticket['id_ticket'] ?? 0),
            'id_usine' => (int) ($ticket['id_usine'] ?? 0),
            'id_agent' => (int) ($ticket['id_agent'] ?? 0),
            'matricule_vehicule' => (string) ($ticket['matricule_vehicule'] ?? ''),
            'particulier_agent_id' => $ticket['particulier_agent_id'] ?? null,
            'poids' => $ticket['poids'] ?? null,
        ]);

        if (!empty($ticket['date_ticket'])) {
            try {
                $modele->date_ticket = Carbon::parse((string) $ticket['date_ticket']);
            } catch (\Throwable $e) {
            }
        }

        return $modele;
    }

    public function updatePrixUnitaire(Request $request, int $id)
    {
        $wantsJson = $request->expectsJson() || $request->ajax();

        $validated = $request->validate([
            'prix_unitaire' => ['required', 'numeric', 'min:0'],
        ]);

        $apiTicket = $this->mesTicketsService->findTicketById($id, $request);
        if (! $apiTicket) {
            $message = 'Ticket introuvable pour votre équipe.';

            return $wantsJson
                ? response()->json(['success' => false, 'message' => $message], 404)
                : back()->with('error', $message);
        }

        $estCamionPgf = $this->vehiculeEstCamionPgf(
            (int) ($apiTicket['vehicule_id'] ?? 0),
            (string) ($apiTicket['matricule_vehicule'] ?? '')
        );
        if (! $estCamionPgf) {
            $message = 'Le prix unitaire manuel est réservé aux tickets camions PGF.';

            return $wantsJson
                ? response()->json(['success' => false, 'message' => $message], 422)
                : back()->with('error', $message);
        }

        $numeroTicket = trim((string) ($apiTicket['numero_ticket'] ?? ''));
        $ticket = $this->findTicketLocal($id, $numeroTicket);

        if ($ticket && (int) $ticket->id_ticket !== $id) {
            $ticketApiId = Ticket::query()->find($id);
            if ($ticketApiId) {
                $ticket = $ticketApiId;
            } else {
                $this->realignerIdTicketLocal((int) $ticket->id_ticket, $id);
                $ticket = Ticket::query()->find($id);
            }
        }

        if (! $ticket) {
            $ticket = new Ticket();
            $ticket->id_ticket = $id;
            $ticket->id_utilisateur = Auth::id() ?? 1;
            $ticket->statut_ticket = $apiTicket['statut_ticket'] ?? 'non soldé';
        }

        $poids = (float) ($apiTicket['poids'] ?? $ticket->poids ?? 0);
        $prix = (float) $validated['prix_unitaire'];
        $montant = $poids > 0 ? round($prix * $poids, 2) : 0.0;

        $dateTicket = $ticket->date_ticket;
        if (! empty($apiTicket['date_ticket'])) {
            try {
                $dateTicket = \Carbon\Carbon::parse((string) $apiTicket['date_ticket']);
            } catch (\Throwable $e) {
            }
        }

        $ticket->fill([
            'numero_ticket' => $numeroTicket !== '' ? $numeroTicket : (string) ($ticket->numero_ticket ?? $id),
            'date_ticket' => $dateTicket ?? now(),
            'matricule_vehicule' => (string) ($apiTicket['matricule_vehicule'] ?? $ticket->matricule_vehicule ?? ''),
            'vehicule_id' => (int) ($apiTicket['vehicule_id'] ?? 0) ?: ($ticket->vehicule_id ?? null),
            'poids' => $poids > 0 ? $poids : ($ticket->poids ?? null),
            'id_usine' => (int) ($apiTicket['id_usine'] ?? $ticket->id_usine ?? 0) ?: null,
            'id_agent' => (int) ($apiTicket['id_agent'] ?? $ticket->id_agent ?? 0) ?: null,
            'prix_unitaire' => $prix,
            'prix_saisi_manuel' => true,
            'montant_paie' => $montant,
            'id_utilisateur' => $ticket->id_utilisateur ?? Auth::id() ?? 1,
        ]);
        $ticket->save();

        if ($wantsJson) {
            return response()->json([
                'success' => true,
                'message' => 'Prix unitaire enregistré.',
                'prix_unitaire' => $prix,
                'montant' => $montant,
                'montant_affiche' => number_format($montant, 0, ',', ' ').' FCFA',
            ]);
        }

        return redirect()
            ->route('camions.activites', $request->only(['vehicule', 'agent', 'statut', 'date_debut', 'date_fin', 'page']))
            ->with('success', 'Prix unitaire enregistré. Montant : '.number_format($montant, 0, ',', ' ').' FCFA.');
    }

    public function marquerPaye(Request $request, int $id)
    {
        $wantsJson = $request->expectsJson() || $request->ajax();

        $apiTicket = $this->mesTicketsService->findTicketById($id, $request);
        if (! $apiTicket) {
            $message = 'Ticket introuvable pour votre équipe.';

            return $wantsJson
                ? response()->json(['success' => false, 'message' => $message], 404)
                : back()->with('error', $message);
        }

        $estCamionPgf = $this->vehiculeEstCamionPgf(
            (int) ($apiTicket['vehicule_id'] ?? 0),
            (string) ($apiTicket['matricule_vehicule'] ?? '')
        );
        if (! $estCamionPgf) {
            $message = 'Le paiement manuel est réservé aux tickets camions PGF.';

            return $wantsJson
                ? response()->json(['success' => false, 'message' => $message], 422)
                : back()->with('error', $message);
        }

        $numeroTicket = trim((string) ($apiTicket['numero_ticket'] ?? ''));
        $ticket = $this->findTicketLocal($id, $numeroTicket);

        if ($ticket && (int) $ticket->id_ticket !== $id) {
            $ticketApiId = Ticket::query()->find($id);
            if ($ticketApiId) {
                $ticket = $ticketApiId;
            } else {
                $this->realignerIdTicketLocal((int) $ticket->id_ticket, $id);
                $ticket = Ticket::query()->find($id);
            }
        }

        if (! $ticket) {
            $ticket = new Ticket();
            $ticket->id_ticket = $id;
            $ticket->id_utilisateur = Auth::id() ?? 1;
        }

        if (($ticket->statut_ticket ?? '') === 'soldé') {
            $message = 'Ce ticket est déjà payé.';

            return $wantsJson
                ? response()->json(['success' => true, 'message' => $message, 'statut' => 'Payé', 'deja_paye' => true])
                : back()->with('success', $message);
        }

        $prixOk = (bool) ($ticket->prix_saisi_manuel ?? false)
            && $ticket->prix_unitaire !== null
            && (float) $ticket->prix_unitaire >= 0
            && $ticket->montant_paie !== null
            && (float) $ticket->montant_paie > 0;

        if (! $prixOk) {
            $message = 'Saisissez d’abord le prix unitaire et le montant avant de payer.';

            return $wantsJson
                ? response()->json(['success' => false, 'message' => $message], 422)
                : back()->with('error', $message);
        }

        $dateTicket = $ticket->date_ticket;
        if (! empty($apiTicket['date_ticket'])) {
            try {
                $dateTicket = \Carbon\Carbon::parse((string) $apiTicket['date_ticket']);
            } catch (\Throwable $e) {
            }
        }

        $ticket->fill([
            'numero_ticket' => $numeroTicket !== '' ? $numeroTicket : (string) ($ticket->numero_ticket ?? $id),
            'date_ticket' => $dateTicket ?? now(),
            'matricule_vehicule' => (string) ($apiTicket['matricule_vehicule'] ?? $ticket->matricule_vehicule ?? ''),
            'vehicule_id' => (int) ($apiTicket['vehicule_id'] ?? 0) ?: ($ticket->vehicule_id ?? null),
            'poids' => (float) ($apiTicket['poids'] ?? $ticket->poids ?? 0) ?: ($ticket->poids ?? null),
            'id_usine' => (int) ($apiTicket['id_usine'] ?? $ticket->id_usine ?? 0) ?: null,
            'id_agent' => (int) ($apiTicket['id_agent'] ?? $ticket->id_agent ?? 0) ?: null,
            'statut_ticket' => 'soldé',
            'date_paie' => now(),
            'id_utilisateur' => $ticket->id_utilisateur ?? Auth::id() ?? 1,
        ]);
        $ticket->save();

        if ($wantsJson) {
            return response()->json([
                'success' => true,
                'message' => 'Ticket marqué comme payé.',
                'statut' => 'Payé',
            ]);
        }

        return redirect()
            ->route('camions.activites', $request->only(['vehicule', 'agent', 'statut', 'date_debut', 'date_fin', 'page']))
            ->with('success', 'Ticket marqué comme payé.');
    }

    public function store(Request $request)
    {
        $request->merge([
            'numero_ticket' => trim((string) $request->input('numero_ticket', '')),
        ]);

        $idPontSaisi = $request->filled('id_pont') ? (int) $request->input('id_pont') : null;
        $pontGerable = $idPontSaisi
            && PontEtat::where('id_pont', $idPontSaisi)->where('gerable', true)->exists();

        $validated = $request->validate([
            'numero_ticket'        => ['required', 'string', 'max:255', Rule::unique('tickets', 'numero_ticket')],
            'date_ticket'          => ['required', 'date'],
            'matricule_vehicule'   => ['required', 'string', 'max:255'],
            'vehicule_id'          => ['nullable', 'integer', 'min:1'],
            'poids'                => ['nullable', 'numeric', 'min:0'],
            'id_usine'             => ['required', 'integer', 'min:1'],
            'particulier_groupe_id'=> ['required', 'exists:particulier_groupes,id'],
            'agent_ref'            => ['required', 'string', 'regex:/^(api|local):\d+$/'],
            'prix_unitaire'        => ['nullable', 'numeric', 'min:0'],
            'statut_ticket'        => ['nullable', 'in:soldé,non soldé'],
            'id_pont'              => ['nullable', 'integer', 'min:1'],
            'parc_id'              => [$pontGerable ? 'required' : 'nullable', 'integer', 'min:1'],
            'produit_id'           => [$pontGerable ? 'required' : 'nullable', 'integer', 'min:1'],
        ], [
            'numero_ticket.unique' => 'Ce N° ticket existe déjà.',
            'parc_id.required' => 'Le parc est obligatoire pour un pont gérable.',
            'produit_id.required' => 'Le produit est obligatoire pour un pont gérable.',
        ]);

        if ($pontGerable) {
            $parcId = (int) $validated['parc_id'];
            $produitId = (int) $validated['produit_id'];
            $parcValide = Stock::where('id_pont', $idPontSaisi)
                ->where('produit_id', $produitId)
                ->where('parc_id', $parcId)
                ->where('type', 'entree')
                ->where('statut', 'ouvert')
                ->exists();

            if (!$parcValide) {
                return back()->withInput()->withErrors([
                    'parc_id' => 'Parc invalide ou indisponible pour ce pont et ce produit.',
                ]);
            }
        }

        $agentsApi = $this->particulierAgentsApiService->fetchAll($request);
        $agent = $this->particulierAgentsApiService->resolveAgentForTicket(
            (int) $validated['particulier_groupe_id'],
            (string) $validated['agent_ref'],
            $agentsApi
        );

        $ticket = Ticket::create([
            'numero_ticket'       => $validated['numero_ticket'],
            'date_ticket'         => $validated['date_ticket'],
            'matricule_vehicule'  => trim($validated['matricule_vehicule']),
            'vehicule_id'         => $validated['vehicule_id'] ?? null,
            'poids'               => $validated['poids'] ?? null,
            'id_usine'            => $validated['id_usine'],
            'id_agent'            => null,
            'particulier_agent_id'=> $agent->id,
            'id_utilisateur'      => Auth::id() ?? 1,
            'prix_unitaire'       => $validated['prix_unitaire'] ?? 0,
            'statut_ticket'       => $validated['statut_ticket'] ?? 'non soldé',
        ]);

        $idPont   = $validated['id_pont'] ?? null;
        $parcId   = $validated['parc_id'] ?? null;
        $produitId = $validated['produit_id'] ?? null;
        $ficheCreee = null;
        $produit = $produitId ? Produit::find($produitId) : null;
        $nomAgent = trim($agent->nom . ' ' . $agent->prenoms);
        $numeroAgent = $agent->numero_agent ?? '';
        $nomUsine = $this->nomUsinePourTicket((int) $ticket->id_usine);

        if ($idPont && $produitId) {
            // Trouver le stock ouvert actif pour ce pont + produit (+ parc si fourni)
            $stockQuery = Stock::where('id_pont', $idPont)
                ->where('produit_id', $produitId)
                ->where('type', 'entree')
                ->where('statut', 'ouvert')
                ->where(fn ($q) => $q->whereNull('etat')->orWhere('etat', 'actif'));

            if ($parcId) {
                $stockQuery->where('parc_id', $parcId);
            }

            $stock = $stockQuery->orderBy('id')->first();

            if ($stock) {
                $parc    = $parcId ? Parc::find($parcId) : null;

                // Récupérer infos pont depuis pont_etats
                $pontEtat = PontEtat::where('id_pont', $idPont)->first();
                $nomPont  = $pontEtat?->nom_pont ?? '';
                $codePont = $pontEtat?->code_pont ?? '';

                $numeroFiche = app(FicheSortieNumeroService::class)->generer($nomPont, $idPont);

                $ficheCreee = FicheSortie::create([
                    'numero_fiche'       => $numeroFiche,
                    'stock_id'           => $stock->id,
                    'parc_id'            => $parc?->id,
                    'nom_parc'           => $parc?->nom ?? '',
                    'vehicule_id'        => $ticket->vehicule_id ?? 0,
                    'matricule_vehicule' => $ticket->matricule_vehicule,
                    'id_pont'            => $idPont,
                    'nom_pont'           => $nomPont,
                    'code_pont'          => $codePont,
                    'id_agent'           => $agent->id_agent ?? 0,
                    'nom_agent'          => $nomAgent,
                    'numero_agent'       => $numeroAgent,
                    'usine'              => $nomUsine,
                    'produit_id'         => $produit?->id,
                    'nom_produit'        => $produit?->nom ?? '',
                    'id_ticket'          => $ticket->id_ticket,
                    'numero_ticket'      => $ticket->numero_ticket,
                    'date_chargement'    => $ticket->date_ticket,
                    'poids_pont'         => $ticket->poids ?? 0,
                ]);
            }
        }

        $transporteurLie = app(TicketTransporteurFicheService::class)->synchroniserTicketTransporteur(
            $ticket,
            $ficheCreee,
            [
                'nom_usine' => $nomUsine ?? null,
                'produit_id' => $produitId ? (int) $produitId : null,
                'nom_produit' => $produit?->nom ?? '',
                'id_agent' => $agent->id_agent ?? null,
                'nom_agent' => $nomAgent ?? '',
                'numero_agent' => $numeroAgent ?? '',
            ]
        );

        $message = 'Ticket créé avec succès.';
        if ($transporteurLie) {
            $message .= ' Les informations ont été transmises au transporteur '
                . $transporteurLie->code . ' (' . $transporteurLie->nom . ' ' . $transporteurLie->prenoms . ').';
        }

        return redirect()->route('tickets.index')
            ->with('success', $message);
    }

    public function valider(Request $request, int $id, FicheSortieDechargementService $dechargementService)
    {
        $chefAgentIds = $this->mesAgentsService->chefAgentIds($request);
        $apiTicket = $this->mesTicketsService->findTicketById($id, $request);

        if (!$apiTicket) {
            return redirect()->route('tickets.index')
                ->with('error', 'Ticket introuvable pour votre équipe.');
        }

        $estCamionPgf = $this->vehiculeEstCamionPgf(
            (int) ($apiTicket['vehicule_id'] ?? 0),
            (string) ($apiTicket['matricule_vehicule'] ?? '')
        );

        $rules = [
            'parc_id' => ['nullable', 'integer', 'exists:parcs,id'],
        ];
        if ($estCamionPgf) {
            $rules['fiche_id'] = ['required', 'integer', 'exists:fiches_sortie,id'];
        }

        $validated = $request->validate($rules, [
            'fiche_id.required' => 'Sélectionnez une fiche de sortie pour ce camion PGF.',
        ]);

        if (! $request->boolean('confirm_validation')) {
            return redirect()->route('tickets.index')
                ->with('error', 'Validation refusée : action non confirmée.');
        }

        $numeroTicket = trim((string) ($apiTicket['numero_ticket'] ?? ''));

        $existing = $this->findTicketLocal($id, $numeroTicket);
        if ($this->ticketEstDejaValide($existing, $id, $numeroTicket)) {
            return redirect()->route('tickets.index')
                ->with('error', 'Ce ticket est déjà validé.');
        }

        if (!$existing && $numeroTicket !== '') {
            $dejaValideParNumero = TicketValidation::query()
                ->where('numero_ticket', $numeroTicket)
                ->exists();
            if ($dejaValideParNumero) {
                return redirect()->route('tickets.index')
                    ->with('error', 'Ce ticket est déjà validé.');
            }
        }

        $fiche = null;
        if ($estCamionPgf) {
            $fiche = FicheSortie::query()
                ->whereNull('date_dechargement')
                ->whereIn('id_agent', $chefAgentIds ?: [-1])
                ->find($validated['fiche_id']);

            if (!$fiche) {
                return redirect()->route('tickets.index')
                    ->with('error', 'Fiche de sortie introuvable ou déjà déchargée.');
            }

            $raison = $this->ficheTicketCorrespondance->raisonNonCorrespondance(
                $this->ficheTicketCorrespondance->ticketDepuisApi($apiTicket),
                $fiche
            );
            if ($raison !== null) {
                return redirect()->route('tickets.index')
                    ->with('error', $raison);
            }
        }

        if ($existing && (int) $existing->id_ticket !== $id) {
            $ticketApiId = Ticket::query()->find($id);
            if ($ticketApiId) {
                $existing = $ticketApiId;
            } else {
                $this->realignerIdTicketLocal((int) $existing->id_ticket, $id);
                $existing = Ticket::query()->find($id);
            }
        }

        $ticket = $existing ?? new Ticket();
        $ticket->id_ticket = $id;

        $dateTicket = $apiTicket['date_ticket'] ?? now()->format('Y-m-d');
        $poidsTicket = (float) ($apiTicket['poids'] ?? 0);
        $idUsine = (int) ($apiTicket['id_usine'] ?? 0) ?: null;
        $nomUsine = trim((string) ($apiTicket['nom_usine'] ?? $fiche?->usine ?? ''));
        if ($nomUsine === '' && $idUsine) {
            $nomUsine = trim((string) (\App\Models\Usine::query()->where('id_usine', $idUsine)->value('nom_usine') ?? ''));
        }

        $produitInfo = app(UsinesParProduitService::class)->produitPourUsine($idUsine, $nomUsine !== '' ? $nomUsine : null);
        $produitId = $fiche?->produit_id
            ? (int) $fiche->produit_id
            : ($produitInfo ? (int) $produitInfo['produit_id'] : null);

        $idAgentApi = (int) ($apiTicket['id_agent'] ?? 0);
        if ($idAgentApi <= 0 && $fiche) {
            $idAgentApi = (int) $fiche->id_agent;
        }
        if ($idAgentApi <= 0) {
            return redirect()->route('tickets.index')
                ->with('error', 'Impossible de valider : agent introuvable sur ce ticket.');
        }

        $ticket->fill([
            'numero_ticket' => (string) ($apiTicket['numero_ticket'] ?? ''),
            'date_ticket' => $dateTicket,
            'matricule_vehicule' => (string) ($apiTicket['matricule_vehicule'] ?? ''),
            'vehicule_id' => (int) ($apiTicket['vehicule_id'] ?? 0) ?: null,
            'poids' => $apiTicket['poids'] ?? null,
            'id_usine' => $idUsine,
            'id_agent' => $idAgentApi,
            'statut_ticket' => $apiTicket['statut_ticket'] ?? 'non soldé',
            'id_utilisateur' => $ticket->id_utilisateur ?? Auth::id() ?? 1,
            'poids_unipalm' => $apiTicket['poids'] ?? null,
            'date_confirmation_unipalm' => now(),
        ]);

        $prixUnitaire = $this->ticketPrixService->prixUnitairePourTicket(
            $ticket,
            $produitId,
            $dateTicket,
            null,
            $idAgentApi,
            $nomUsine !== '' ? $nomUsine : null,
        );
        $montantPaie = ($prixUnitaire !== null && $poidsTicket > 0)
            ? round($prixUnitaire * $poidsTicket, 2)
            : null;

        $ticket->prix_unitaire = $prixUnitaire ?? (float) ($apiTicket['prix_unitaire'] ?? 0);
        $ticket->montant_paie = $montantPaie ?? $apiTicket['montant_paie'] ?? null;

        try {
            DB::transaction(function () use ($ticket, $dechargementService, $fiche, $apiTicket, $dateTicket, $poidsTicket, $id, $validated, $numeroTicket) {
                $ticket->save();

                TicketValidation::updateOrCreate(
                    ['id_ticket' => $id],
                    [
                        'numero_ticket' => $numeroTicket !== '' ? $numeroTicket : (string) ($apiTicket['numero_ticket'] ?? $id),
                        'validated_at' => now(),
                        'validated_by' => Auth::id(),
                    ]
                );

                if ($fiche) {
                    $dechargementService->decharger(
                        $fiche,
                        (string) ($apiTicket['numero_ticket'] ?? ''),
                        $dateTicket,
                        $poidsTicket,
                        $id,
                        isset($apiTicket['nom_usine']) ? (string) $apiTicket['nom_usine'] : null,
                        isset($validated['parc_id']) ? (int) $validated['parc_id'] : null,
                    );
                }
            });
        } catch (UniqueConstraintViolationException) {
            return redirect()->route('tickets.index')
                ->with('error', 'Ce ticket est déjà validé ou existe déjà dans le système.');
        } catch (\InvalidArgumentException $e) {
            return redirect()->route('tickets.index')
                ->with('error', $e->getMessage());
        }

        $ticket = Ticket::query()->find($id) ?? $this->findTicketLocal($id, $numeroTicket);
        if (! $ticket) {
            return redirect()->route('tickets.index')
                ->with('error', 'Le ticket a été validé mais est introuvable en base (id ' . $id . '). Contactez l\'administrateur.');
        }

        $ficheTransporteur = $fiche;
        if ($estCamionPgf && $ficheTransporteur) {
            $ficheTransporteur->refresh();
            app(TicketTransporteurFicheService::class)->synchroniserDonneesTicketSurFiche($ficheTransporteur);
        } else {
            app(MontantAgentFicheService::class)->assurerFichePourTicketAgent($ticket, $produitInfo);
            $ficheTransporteur = FicheSortie::query()
                ->where('id_ticket', $ticket->id_ticket)
                ->orderByDesc('id')
                ->first();
        }

        $transporteurLie = app(TicketTransporteurFicheService::class)->synchroniserTicketTransporteur(
            $ticket,
            $ficheTransporteur,
            [
                'nom_usine' => $nomUsine !== '' ? $nomUsine : null,
                'produit_id' => $produitId,
                'nom_produit' => $ficheTransporteur?->nom_produit ?? ($produitInfo['nom'] ?? ''),
                'id_agent' => $idAgentApi,
                'nom_agent' => $ficheTransporteur?->nom_agent ?? trim((string) ($apiTicket['nom_agent'] ?? '')),
                'numero_agent' => $ficheTransporteur?->numero_agent ?? '',
            ]
        );

        Cache::forget('agent_tickets_sync:' . $idAgentApi);
        Cache::forget('montant_agent_index:' . $idAgentApi . ':' . md5(json_encode(['id_agent' => $idAgentApi])));
        $this->mesTicketsService->forgetEnAttenteCountCache($request);

        if ($fiche) {
            $fiche->refresh();
            $stockMsg = $dechargementService->pontEstGerable((int) $fiche->id_pont)
                ? ' Le stock du parc a été déduit.'
                : '';

            $message = 'Ticket « ' . ($apiTicket['numero_ticket'] ?? $id) . ' » validé et associé à la fiche '
                . ($fiche->numero_fiche ?? $fiche->id) . '.';
            if ($produitInfo && ! $fiche->produit_id) {
                $message .= ' Produit : ' . $produitInfo['nom'] . '.';
            }
            if ($transporteurLie) {
                $message .= ' Transmis au transporteur ' . $transporteurLie->code . '.';
            }

            return redirect()->route('tickets.index')
                ->with('success', $message . $stockMsg);
        }

        $message = 'Ticket « ' . ($apiTicket['numero_ticket'] ?? $id) . ' » validé avec succès.';
        if ($produitInfo) {
            $message .= ' Produit : ' . $produitInfo['nom'] . '.';
        }
        if ($transporteurLie) {
            $message .= ' Les informations ont été transmises au transporteur ' . $transporteurLie->code . '.';
        }

        return redirect()->route('tickets.index')
            ->with('success', $message);
    }

    public function confirmUnipalm(Request $request, int $id, ChefEquipeContext $chefContext)
    {
        $ticket = Ticket::findOrFail($id);

        // Récupérer les tickets depuis l'API Unipalm
        $timeout = 10;
        $ticketsApi = [];
        $mesTicketsUrl = (string) config('services.external_auth.mes_tickets_url');

        try {
            $response = Http::acceptJson()
                ->withoutVerifying()
                ->timeout($timeout)
                ->get($mesTicketsUrl, $chefContext->apiQueryParams($request));
            if ($response->successful()) {
                $ticketsApi = $response->json('tickets') ?? [];
            }
        } catch (\Throwable $e) {
            return redirect()->route('tickets.index')
                ->with('error', 'Impossible de joindre l\'API Unipalm.');
        }

        // Récupérer les noms d'usine et agent depuis l'API pour la comparaison
        $usinesApi = [];
        $agentsApi = [];
        try {
            $usinesResponse = Http::acceptJson()->withoutVerifying()->timeout($timeout)
                ->get('https://api.objetombrepegasus.online/api/camions/mes_usines.php');
            if ($usinesResponse->successful()) {
                $usinesApi = $usinesResponse->json('usines') ?? [];
            }
        } catch (\Throwable $e) {}

        try {
            $agentsApi = $this->mesAgentsService->fetchAllAgents([], $request);
        } catch (\Throwable $e) {}

        // Indexer par ID
        $usinesById = [];
        foreach ($usinesApi as $u) {
            $usinesById[$u['id_usine'] ?? 0] = $u['nom_usine'] ?? '';
        }
        $agentsById = [];
        foreach ($agentsApi as $a) {
            $agentsById[$a['id_agent'] ?? 0] = $a['nom_complet'] ?? '';
        }

        // Préparer les données du ticket local pour comparaison
        $ticketDate = $ticket->date_ticket ? $ticket->date_ticket->format('Y-m-d') : '';
        $ticketNumero = $ticket->numero_ticket;
        $ticketUsine = $usinesById[$ticket->id_usine] ?? '';
        $ticketAgent = $agentsById[$ticket->id_agent] ?? '';
        $ticketPoids = (float) $ticket->poids;

        // Chercher un ticket correspondant dans l'API
        $ticketTrouve = null;
        foreach ($ticketsApi as $apiTicket) {
            $apiDate = $apiTicket['date_ticket'] ?? '';
            $apiNumero = $apiTicket['numero_ticket'] ?? ($apiTicket['num_ticket'] ?? '');
            $apiUsine = $apiTicket['nom_usine'] ?? ($apiTicket['usine'] ?? '');
            $apiPoids = (float) ($apiTicket['poids'] ?? ($apiTicket['poids_usine'] ?? 0));

            // Comparaison - Date, N°Ticket, Usine, Poids
            $matchDate = ($ticketDate === $apiDate);
            $matchNumero = (strtolower(trim($ticketNumero)) === strtolower(trim($apiNumero)));
            $matchUsine = (strtolower(trim($ticketUsine)) === strtolower(trim($apiUsine)));
            $matchPoids = (abs($ticketPoids - $apiPoids) < 10); // Tolérance de 10 kg

            if ($matchDate && $matchNumero && $matchUsine && $matchPoids) {
                $ticketTrouve = $apiTicket;
                break;
            }
        }

        if ($ticketTrouve) {
            $ticket->update([
                'poids_unipalm' => $ticketTrouve['poids'] ?? null,
                'date_confirmation_unipalm' => now(),
            ]);
            return redirect()->route('tickets.index')
                ->with('success', 'Correspondance Unipalm trouvée (N°' . ($ticketTrouve['numero_ticket'] ?? '') . ', poids: '
                    . number_format((float) ($ticketTrouve['poids'] ?? 0), 0, ',', ' ') . ' kg). '
                    . 'Le ticket n\'est pas validé : utilisez le bouton Valider pour confirmer.');
        }

        $ticket->update([
            'poids_unipalm' => null,
            'date_confirmation_unipalm' => now(),
        ]);

        return redirect()->route('tickets.index')
            ->with('error', 'Aucun ticket correspondant trouvé dans Unipalm. Vérifiez les données (Date, N°Ticket, Usine, Poids).');
    }

    public function exportBordereauPdf(int $id)
    {
        $ticket = Ticket::with('particulierAgent.groupe')->findOrFail($id);

        $nomUsine = $this->nomUsinePourTicket($ticket->id_usine);

        $ficheSortie = \App\Models\FicheSortie::where('id_ticket', $ticket->id_ticket)->first();
        $chargeMission = $this->resolveNomAgentPourTicketLocal($ticket, $ficheSortie?->nom_agent);
        $nomGroupe = $ticket->particulierAgent?->groupe?->nom_groupe ?? null;

        $dateTicket = $ticket->date_ticket
            ? Carbon::parse($ticket->date_ticket)
            : now();
        $dateReception = $ticket->created_at
            ? Carbon::parse($ticket->created_at)
            : $dateTicket;

        $formatCourt = fn (Carbon $d) => $d->format('d/m/y');

        $poids = (float) ($ticket->poids ?? 0);
        $poidsFormate = number_format($poids, 0, ',', ' ');

        $produitId = $ficheSortie?->produit_id ? (int) $ficheSortie->produit_id : null;
        $idAgentFiche = $ficheSortie?->id_agent ? (int) $ficheSortie->id_agent : null;
        $nomUsinePdf = $ficheSortie?->usine ?: null;
        $prixUnitaire = $this->ticketPrixService->prixUnitairePourTicket(
            $ticket,
            $produitId,
            $ticket->date_ticket?->format('Y-m-d'),
            null,
            $idAgentFiche,
            $nomUsinePdf
        );
        $montantCalcule = $this->ticketPrixService->montantPourTicket(
            $ticket,
            $produitId,
            $ticket->date_ticket?->format('Y-m-d'),
            null,
            $idAgentFiche,
            $nomUsinePdf
        );
        if ($montantCalcule === null && (float) ($ticket->prix_unitaire ?? 0) > 0 && $poids > 0) {
            $prixUnitaire = (float) $ticket->prix_unitaire;
            $montantCalcule = $prixUnitaire * $poids;
        }

        $montantFormate = $montantCalcule !== null
            ? number_format($montantCalcule, 0, ',', ' ')
            : '—';

        $logoPath = public_path('img/logo/logo.png');
        if (!is_file($logoPath)) {
            $logoPath = null;
        }

        $pdf = Pdf::loadView('tickets.bordereau_pdf', [
            'logoPath' => $logoPath,
            'chargeMission' => strtoupper($chargeMission),
            'nomGroupe' => $nomGroupe,
            'periodeDebut' => $formatCourt($dateTicket),
            'periodeFin' => $formatCourt($dateTicket),
            'nomUsine' => strtoupper($nomUsine),
            'ligne' => [
                'date_reception' => $formatCourt($dateReception),
                'date_ticket' => $formatCourt($dateTicket),
                'vehicule' => $ticket->matricule_vehicule ?? '—',
                'numero_ticket' => $ticket->numero_ticket ?? '—',
                'poids' => $poidsFormate,
                'montant' => $montantFormate,
            ],
            'lieu' => 'Divo',
            'dateDocument' => now()->format('d/m/y'),
        ]);

        $pdf->setPaper('A4', 'portrait');

        $filename = 'bordereau_' . preg_replace('/[^a-zA-Z0-9_-]+/', '_', (string) $ticket->numero_ticket) . '.pdf';

        return $pdf->stream($filename);
    }

    private function nomUsinePourTicket(?int $idUsine): string
    {
        if (!$idUsine) {
            return '—';
        }

        try {
            $response = Http::acceptJson()
                ->withoutVerifying()
                ->timeout(10)
                ->get('https://api.objetombrepegasus.online/api/camions/mes_usines.php');

            if ($response->successful()) {
                foreach ($response->json('usines') ?? [] as $usine) {
                    if ((int) ($usine['id_usine'] ?? 0) === $idUsine) {
                        return (string) ($usine['nom_usine'] ?? '—');
                    }
                }
            }
        } catch (\Throwable $e) {
        }

        return 'Usine #' . $idUsine;
    }

    /**
     * Mettre à jour un ticket
     */
    public function update(Request $request, $id)
    {
        $ticket = Ticket::findOrFail($id);

        $request->merge([
            'numero_ticket' => trim((string) $request->input('numero_ticket', '')),
        ]);

        $validated = $request->validate([
            'numero_ticket' => [
                'required',
                'string',
                'max:255',
                Rule::unique('tickets', 'numero_ticket')->ignore($ticket->id_ticket, 'id_ticket'),
            ],
            'date_ticket' => ['required', 'date'],
            'matricule_vehicule' => ['nullable', 'string', 'max:255'],
            'poids' => ['nullable', 'numeric', 'min:0'],
            'poids_parc' => ['nullable', 'numeric', 'min:0'],
            'prix_unitaire_transport' => ['nullable', 'numeric', 'min:0'],
        ], [
            'numero_ticket.unique' => 'Ce N° ticket existe déjà.',
        ]);

        $ticket->update([
            'numero_ticket' => $validated['numero_ticket'],
            'date_ticket' => $validated['date_ticket'],
            'matricule_vehicule' => $validated['matricule_vehicule'] ?? $ticket->matricule_vehicule,
            'poids' => $validated['poids'] ?? $ticket->poids,
        ]);

        FicheSortie::where('id_ticket', $ticket->id_ticket)->update([
            'numero_ticket' => $validated['numero_ticket'],
        ]);

        return redirect()->route('tickets.index')
            ->with('success', 'Ticket modifié avec succès.');
    }

    /**
     * Supprimer un ticket
     */
    public function destroy($id)
    {
        $ticket = Ticket::findOrFail($id);

        // Supprimer la fiche de sortie liée → restitue automatiquement le poids au stock du parc
        FicheSortie::where('id_ticket', $ticket->id_ticket)->delete();

        TicketValidation::where('id_ticket', $ticket->id_ticket)->delete();

        $ticket->delete();

        return redirect()->route('tickets.index')
            ->with('success', 'Ticket supprimé avec succès.');
    }

    /**
     * Afficher les tickets Unipalm (API) filtrés par les camions du groupe PGF
     */
    public function unipalm(Request $request, ChefEquipeContext $chefContext)
    {
        $timeout = 10;
        $page = max(1, (int) $request->query('page', 1));
        $mesTicketsUrl = (string) config('services.external_auth.mes_tickets_url');

        // Récupérer le groupe PGF
        $groupePgf = Groupe::where('nom_groupe', 'PGF')->first();
        $vehiculesPgfIds = [];
        $vehiculesPgfMatricules = [];

        if ($groupePgf) {
            $groupeVehicules = GroupeVehicule::where('groupe_id', $groupePgf->id)->get();
            $vehiculesPgfIds = $groupeVehicules->pluck('vehicule_id')->toArray();
            $vehiculesPgfMatricules = $groupeVehicules->pluck('matricule_vehicule')->toArray();
        }

        // Récupérer les tickets depuis l'API
        $tickets = [];
        $pagination = [
            'current_page' => $page,
            'per_page' => 20,
            'total' => 0,
            'last_page' => 1,
        ];

        try {
            $response = Http::acceptJson()
                ->withoutVerifying()
                ->timeout($timeout)
                ->get($mesTicketsUrl, array_merge(
                    $chefContext->apiQueryParams($request),
                    ['page' => $page],
                ));

            if ($response->successful()) {
                $allTickets = $response->json('tickets') ?? [];
                $apiPagination = $response->json('pagination') ?? [];

                // Filtrer les tickets par les véhicules du groupe PGF
                $tickets = array_filter($allTickets, function ($t) use ($vehiculesPgfIds, $vehiculesPgfMatricules) {
                    $vehiculeId = $t['vehicule_id'] ?? 0;
                    $matricule = $t['matricule_vehicule'] ?? '';
                    return in_array($vehiculeId, $vehiculesPgfIds) || in_array($matricule, $vehiculesPgfMatricules);
                });
                $tickets = array_values($tickets);

                // Récupérer les fiches de sortie associées aux tickets
                $ticketIds = array_column($tickets, 'id_ticket');
                $fichesAssociees = [];
                if (!empty($ticketIds)) {
                    $fiches = FicheSortie::whereIn('id_ticket', $ticketIds)->get()->keyBy('id_ticket');
                    foreach ($fiches as $idTicket => $fiche) {
                        $fichesAssociees[$idTicket] = [
                            'origine' => $fiche->nom_pont,
                            'date_chargement' => $fiche->date_chargement ? $fiche->date_chargement->format('d-m-Y') : '-',
                            'poids_parc' => $fiche->poids_pont,
                        ];
                    }
                }

                // Enrichir les tickets avec les données de fiche de sortie
                foreach ($tickets as &$ticket) {
                    $idTicket = $ticket['id_ticket'] ?? 0;
                    if (isset($fichesAssociees[$idTicket])) {
                        $ticket['origine'] = $fichesAssociees[$idTicket]['origine'];
                        $ticket['date_chargement'] = $fichesAssociees[$idTicket]['date_chargement'];
                        $ticket['poids_parc'] = $fichesAssociees[$idTicket]['poids_parc'];
                        $ticket['has_fiche'] = true;
                    } else {
                        $ticket['has_fiche'] = false;
                    }
                }
                unset($ticket);

                $pagination = [
                    'current_page' => $apiPagination['current_page'] ?? $page,
                    'per_page' => $apiPagination['per_page'] ?? 20,
                    'total' => count($tickets),
                    'last_page' => $apiPagination['last_page'] ?? 1,
                ];
            }
        } catch (\Throwable $e) {}

        // Récupérer les fiches de sortie disponibles (non associées à un ticket)
        $fichesDisponibles = FicheSortie::whereNull('id_ticket')
            ->orderBy('date_chargement', 'desc')
            ->get();

        foreach ($tickets as &$ticket) {
            $normalized = $this->mesTicketsService->normalizeTicketRow($ticket);
            $ticket['fiches_correspondantes'] = $this->ficheTicketCorrespondance
                ->filtrer($fichesDisponibles, $this->ficheTicketCorrespondance->ticketDepuisApi($normalized))
                ->all();
        }
        unset($ticket);

        return view('tickets.unipalm', [
            'tickets' => $tickets,
            'pagination' => $pagination,
            'groupe_pgf' => $groupePgf,
            'fiches_disponibles' => $fichesDisponibles,
        ]);
    }

    public function associerFiche(Request $request)
    {
        $validated = $request->validate([
            'id_ticket' => ['required', 'integer'],
            'numero_ticket' => ['required', 'string'],
            'fiche_id' => ['required', 'integer', 'exists:fiches_sortie,id'],
        ]);

        $fiche = FicheSortie::findOrFail($validated['fiche_id']);

        $apiTicket = $this->mesTicketsService->findTicketById((int) $validated['id_ticket'], $request);
        if ($apiTicket) {
            $raison = $this->ficheTicketCorrespondance->raisonNonCorrespondance(
                $this->ficheTicketCorrespondance->ticketDepuisApi($apiTicket),
                $fiche
            );
            if ($raison !== null) {
                return redirect()->route('tickets.unipalm')
                    ->with('error', $raison);
            }
        }

        $fiche->update([
            'id_ticket' => $validated['id_ticket'],
            'numero_ticket' => $validated['numero_ticket'],
        ]);

        return redirect()->route('tickets.unipalm')
            ->with('success', 'Fiche de sortie associée au ticket avec succès.');
    }

    /**
     * @param  array<string, mixed>  $ticketApi
     * @param  array<int, string>  $agentsById
     */
    private function resolveNomAgentPourAffichage(array $ticketApi, ?Ticket $local, array $agentsById): string
    {
        $apiNom = trim((string) ($ticketApi['nom_agent'] ?? ''));
        if ($apiNom !== '' && $apiNom !== '-') {
            return $apiNom;
        }

        $idAgent = (int) ($ticketApi['id_agent'] ?? 0);
        if ($idAgent > 0) {
            $nomDepuisListe = trim((string) ($agentsById[$idAgent] ?? ''));
            if ($nomDepuisListe !== '') {
                return $nomDepuisListe;
            }

            $agent = $this->mesAgentsService->findAgentById($idAgent);
            if ($agent) {
                return trim((string) ($agent['nom_complet'] ?? ''));
            }
        }

        if ($local?->particulierAgent) {
            return $local->particulierAgent->nom_complet;
        }

        return '-';
    }

    private function resolveNomAgentPourTicketLocal(Ticket $ticket, ?string $nomAgentFiche = null): string
    {
        $idAgent = (int) ($ticket->id_agent ?? 0);
        if ($idAgent > 0) {
            $agent = $this->mesAgentsService->findAgentById($idAgent);
            if ($agent) {
                $nom = trim((string) ($agent['nom_complet'] ?? ''));
                if ($nom !== '') {
                    return $nom;
                }
            }
        }

        if ($ticket->particulierAgent) {
            return $ticket->particulierAgent->nom_complet;
        }

        $nomFiche = trim((string) ($nomAgentFiche ?? ''));
        if ($nomFiche !== '') {
            return $nomFiche;
        }

        return $idAgent > 0 ? '#'.$idAgent : '—';
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<Ticket>  $query
     * @param  list<int>  $chefAgentIds
     */
    private function appliquerFiltreAgentsChef($query, array $chefAgentIds): void
    {
        if ($chefAgentIds === []) {
            $query->whereRaw('1 = 0');

            return;
        }

        $particulierAgentIds = ParticulierAgent::query()
            ->whereIn('id_agent', $chefAgentIds)
            ->pluck('id');

        $query->where(function ($q) use ($chefAgentIds, $particulierAgentIds) {
            $q->whereIn('id_agent', $chefAgentIds);
            if ($particulierAgentIds->isNotEmpty()) {
                $q->orWhereIn('particulier_agent_id', $particulierAgentIds);
            }
        });
    }
}
