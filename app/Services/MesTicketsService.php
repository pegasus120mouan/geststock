<?php

namespace App\Services;

use App\Models\TicketValidation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

class MesTicketsService
{
    /** @var array<string, list<array<string, mixed>>> */
    private array $cachedAllTickets = [];

    public function __construct(
        private CamionsDatabaseResolver $databaseResolver,
        private ChefEquipeContext $chefContext,
    ) {}

    /**
     * @param  array{token?: string, id_chef?: int, page?: int, per_page?: int}  $params
     * @return array{tickets: list<array<string, mixed>>, pagination: array<string, int>|null, chef: array<string, mixed>|null, error: string|null}
     */
    public function listTickets(array $params = [], ?Request $request = null): array
    {
        $request ??= request();

        $token = trim((string) ($params['token'] ?? ''));
        $idChef = (int) ($params['id_chef'] ?? 0);
        $page = max(1, (int) ($params['page'] ?? 1));
        $perPage = max(1, min(100, (int) ($params['per_page'] ?? 20)));

        if ($token === '' && $idChef <= 0) {
            $chefParams = $this->chefContext->apiQueryParams($request);
            $token = trim((string) ($chefParams['token'] ?? ''));
            $idChef = (int) ($chefParams['id_chef'] ?? 0);
        }

        if ($token === '' && $idChef <= 0) {
            return $this->emptyResult('Le paramètre token ou id_chef est requis.');
        }

        if ($this->databaseResolver->usesApi()) {
            return $this->fetchFromExternalApi($token, $idChef, $page, $perPage);
        }

        if ($this->databaseResolver->connection() !== null) {
            return $this->fetchFromDatabase($token, $idChef, $page, $perPage);
        }

        return $this->fetchFromExternalApi($token, $idChef, $page, $perPage);
    }


    /**
     * @return list<array<string, mixed>>
     */
    public function fetchAllTickets(array $params = [], ?Request $request = null): array
    {
        $cacheKey = $this->ticketsCacheKey($params, $request);
        if (isset($this->cachedAllTickets[$cacheKey])) {
            return $this->cachedAllTickets[$cacheKey];
        }

        $all = [];
        $page = 1;

        do {
            $result = $this->listTickets(array_merge($params, ['page' => $page]), $request);
            if ($result['error']) {
                break;
            }

            $batch = $result['tickets'];
            if ($batch === []) {
                break;
            }

            $all = array_merge($all, $batch);

            $pagination = $result['pagination'] ?? [];
            $lastPage = (int) ($pagination['last_page'] ?? 1);
            if ($page >= $lastPage) {
                break;
            }
            $page++;
        } while ($page <= 100);

        return $this->cachedAllTickets[$cacheKey] = $all;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function fetchTicketsForAgent(int $idAgent, ?Request $request = null): array
    {
        if ($idAgent <= 0) {
            return [];
        }

        $connection = $this->databaseResolver->connection();
        if ($connection !== null) {
            try {
                $rows = DB::connection($connection)->select(
                    'SELECT
                        t.id_ticket,
                        t.id_usine,
                        t.date_ticket,
                        t.id_agent,
                        t.numero_ticket,
                        t.vehicule_id,
                        t.id_pont,
                        t.poids,
                        t.prix_unitaire,
                        t.montant_paie,
                        t.montant_payer,
                        t.montant_reste,
                        t.statut_ticket,
                        t.created_at,
                        v.matricule_vehicule,
                        a.nom AS agent_nom,
                        a.prenom AS agent_prenom,
                        u.nom_usine,
                        pb.nom_pont
                    FROM tickets t
                    INNER JOIN agents a ON a.id_agent = t.id_agent
                    LEFT JOIN vehicules v ON v.vehicules_id = t.vehicule_id
                    LEFT JOIN usines u ON u.id_usine = t.id_usine
                    LEFT JOIN pont_bascule pb ON pb.id_pont = t.id_pont
                    WHERE t.id_agent = ?
                      AND a.date_suppression IS NULL
                    ORDER BY t.id_ticket DESC',
                    [$idAgent]
                );

                return $this->enrichTicketsWithPontData(
                    array_map(fn ($row) => $this->normalizeTicketRow((array) $row), $rows)
                );
            } catch (\Throwable $e) {
            }
        }

        return array_values(array_filter(
            $this->fetchAllTickets([], $request),
            static fn (array $ticket): bool => (int) ($ticket['id_agent'] ?? 0) === $idAgent
        ));
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function ticketsCacheKey(array $params, ?Request $request): string
    {
        $request ??= request();
        $chefParams = $this->chefContext->apiQueryParams($request);

        return md5(json_encode([
            'params' => $params,
            'chef' => $chefParams,
        ]));
    }

  /**
     * @return array<string, mixed>|null
     */
    public function findTicketById(int $idTicket, ?Request $request = null): ?array
    {
        if ($idTicket <= 0) {
            return null;
        }

        foreach ($this->fetchAllTickets([], $request) as $ticket) {
            if ((int) ($ticket['id_ticket'] ?? 0) === $idTicket) {
                return $ticket;
            }
        }

        return null;
    }

    /**
     * @return array{tickets: list<array<string, mixed>>, pagination: array<string, int>|null, chef: array<string, mixed>|null, error: string|null}
     */
    private function fetchFromExternalApi(string $token, int $idChef, int $page, int $perPage): array
    {
        $url = (string) config('services.external_auth.mes_tickets_url', '');
        if ($url === '') {
            return $this->emptyResult('URL API mes_tickets non configurée.');
        }

        $query = ['page' => $page, 'per_page' => $perPage];
        if ($token !== '') {
            $query['token'] = $token;
        } else {
            $query['id_chef'] = $idChef;
        }

        try {
            $response = Http::acceptJson()
                ->withoutVerifying()
                ->timeout((int) config('services.external_auth.timeout', 10))
                ->get($url, $query);
        } catch (\Throwable $e) {
            return $this->emptyResult('Impossible de joindre le service tickets.');
        }

        if (!$response->successful()) {
            return $this->emptyResult((string) ($response->json('error') ?? 'Erreur API tickets.'));
        }

        $tickets = $response->json('tickets');
        if (!is_array($tickets)) {
            $tickets = [];
        }

        $normalized = array_map([$this, 'normalizeTicketRow'], $tickets);

        return [
            'tickets' => $this->enrichTicketsWithPontData($normalized),
            'pagination' => is_array($response->json('pagination')) ? $response->json('pagination') : null,
            'chef' => is_array($response->json('chef')) ? $response->json('chef') : null,
            'error' => null,
        ];
    }

    /**
     * @return array{tickets: list<array<string, mixed>>, pagination: array<string, int>|null, chef: array<string, mixed>|null, error: string|null}
     */
    private function fetchFromDatabase(string $token, int $idChef, int $page, int $perPage): array
    {
        $connection = $this->databaseResolver->connection();
        if ($connection === null) {
            return $this->emptyResult('Connexion base camions non configurée.');
        }

        try {
            $bindings = [];
            $where = ['a.date_suppression IS NULL'];

            if ($token !== '') {
                $where[] = 'ce.token = ?';
                $bindings[] = $token;
            } else {
                $where[] = 'a.id_chef = ?';
                $bindings[] = $idChef;
            }

            $whereSql = implode(' AND ', $where);

            $countRow = DB::connection($connection)->selectOne(
                "SELECT COUNT(*) AS total
                FROM tickets t
                INNER JOIN agents a ON a.id_agent = t.id_agent
                INNER JOIN chef_equipe ce ON ce.id_chef = a.id_chef
                WHERE {$whereSql}",
                $bindings
            );
            $total = (int) ($countRow->total ?? 0);
            $lastPage = max(1, (int) ceil($total / $perPage));
            $page = min($page, $lastPage);
            $offset = ($page - 1) * $perPage;

            $rows = DB::connection($connection)->select(
                "SELECT
                    t.id_ticket,
                    t.id_usine,
                    t.date_ticket,
                    t.id_agent,
                    t.numero_ticket,
                    t.vehicule_id,
                    t.id_pont,
                    t.poids,
                    t.prix_unitaire,
                    t.montant_paie,
                    t.montant_payer,
                    t.montant_reste,
                    t.statut_ticket,
                    t.created_at,
                    v.matricule_vehicule,
                    a.nom AS agent_nom,
                    a.prenom AS agent_prenom,
                    u.nom_usine,
                    pb.nom_pont
                FROM tickets t
                INNER JOIN agents a ON a.id_agent = t.id_agent
                INNER JOIN chef_equipe ce ON ce.id_chef = a.id_chef
                LEFT JOIN vehicules v ON v.vehicules_id = t.vehicule_id
                LEFT JOIN usines u ON u.id_usine = t.id_usine
                LEFT JOIN pont_bascule pb ON pb.id_pont = t.id_pont
                WHERE {$whereSql}
                ORDER BY t.id_ticket DESC
                LIMIT {$perPage} OFFSET {$offset}",
                $bindings
            );

            $chef = null;
            if ($token !== '') {
                $chefRow = DB::connection($connection)->selectOne(
                    'SELECT id_chef, nom, prenoms, token FROM chef_equipe WHERE token = ? LIMIT 1',
                    [$token]
                );
                if ($chefRow) {
                    $chef = $this->normalizeChefRow((array) $chefRow);
                }
            } elseif ($idChef > 0) {
                $chefRow = DB::connection($connection)->selectOne(
                    'SELECT id_chef, nom, prenoms, token FROM chef_equipe WHERE id_chef = ? LIMIT 1',
                    [$idChef]
                );
                if ($chefRow) {
                    $chef = $this->normalizeChefRow((array) $chefRow);
                }
            }

            return [
                'tickets' => $this->enrichTicketsWithPontData(
                    array_map(fn ($row) => $this->normalizeTicketRow((array) $row), $rows)
                ),
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $perPage,
                    'total' => $total,
                    'last_page' => $lastPage,
                ],
                'chef' => $chef,
                'error' => null,
            ];
        } catch (\Throwable $e) {
            return $this->emptyResult('Erreur lors de la lecture des tickets : ' . $e->getMessage());
        }
    }

    /**
     * Complète id_pont / nom_pont lorsque l'API mes_tickets ne les fournit pas encore.
     *
     * @param  list<array<string, mixed>>  $tickets
     * @return list<array<string, mixed>>
     */
    public function enrichTicketsWithPontData(array $tickets): array
    {
        if ($tickets === []) {
            return $tickets;
        }

        $needsEnrichment = false;
        foreach ($tickets as $ticket) {
            if (trim((string) ($ticket['nom_pont'] ?? '')) === '') {
                $needsEnrichment = true;
                break;
            }
        }

        if (! $needsEnrichment) {
            return $tickets;
        }

        $ticketIds = array_values(array_filter(array_map(
            static fn (array $ticket): int => (int) ($ticket['id_ticket'] ?? 0),
            $tickets
        )));

        $pontByTicketId = $this->fetchPontDataForTicketIds($ticketIds);
        $pontNamesById = $this->fetchPontNamesById();

        return array_map(function (array $ticket) use ($pontByTicketId, $pontNamesById) {
            $idTicket = (int) ($ticket['id_ticket'] ?? 0);

            if ($idTicket > 0 && isset($pontByTicketId[$idTicket])) {
                $fromDb = $pontByTicketId[$idTicket];
                if ((int) ($ticket['id_pont'] ?? 0) <= 0 && $fromDb['id_pont'] > 0) {
                    $ticket['id_pont'] = $fromDb['id_pont'];
                }
                if (trim((string) ($ticket['nom_pont'] ?? '')) === '' && $fromDb['nom_pont'] !== '') {
                    $ticket['nom_pont'] = $fromDb['nom_pont'];
                }
            }

            $idPont = (int) ($ticket['id_pont'] ?? 0);
            if (trim((string) ($ticket['nom_pont'] ?? '')) === '' && $idPont > 0) {
                $ticket['nom_pont'] = (string) ($pontNamesById[$idPont] ?? '');
            }

            return $ticket;
        }, $tickets);
    }

    /**
     * @param  list<int>  $ticketIds
     * @return array<int, array{id_pont: int, nom_pont: string}>
     */
    private function fetchPontDataForTicketIds(array $ticketIds): array
    {
        $ticketIds = array_values(array_unique(array_filter($ticketIds, static fn (int $id): bool => $id > 0)));
        if ($ticketIds === []) {
            return [];
        }

        $connection = $this->databaseResolver->connectionForAuth();
        if ($connection === null) {
            return [];
        }

        try {
            if (! Schema::connection($connection)->hasTable('tickets')
                || ! Schema::connection($connection)->hasColumn('tickets', 'id_pont')) {
                return [];
            }

            $placeholders = implode(',', array_fill(0, count($ticketIds), '?'));
            $joinPont = Schema::connection($connection)->hasTable('pont_bascule')
                ? 'LEFT JOIN pont_bascule pb ON pb.id_pont = t.id_pont'
                : '';
            $selectNomPont = $joinPont !== '' ? ', pb.nom_pont' : ", '' AS nom_pont";

            $rows = DB::connection($connection)->select(
                "SELECT t.id_ticket, t.id_pont{$selectNomPont}
                FROM tickets t
                {$joinPont}
                WHERE t.id_ticket IN ({$placeholders})",
                $ticketIds
            );

            $result = [];
            foreach ($rows as $row) {
                $idTicket = (int) ($row->id_ticket ?? 0);
                if ($idTicket <= 0) {
                    continue;
                }

                $result[$idTicket] = [
                    'id_pont' => (int) ($row->id_pont ?? 0),
                    'nom_pont' => trim((string) ($row->nom_pont ?? '')),
                ];
            }

            return $result;
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * @return array<int, string>
     */
    private function fetchPontNamesById(): array
    {
        static $cache = null;
        if (is_array($cache)) {
            return $cache;
        }

        $cache = [];

        $connection = $this->databaseResolver->connectionForAuth();
        if ($connection !== null && Schema::connection($connection)->hasTable('pont_bascule')) {
            try {
                $rows = DB::connection($connection)
                    ->table('pont_bascule')
                    ->select(['id_pont', 'nom_pont'])
                    ->get();

                foreach ($rows as $row) {
                    $idPont = (int) ($row->id_pont ?? 0);
                    $nomPont = trim((string) ($row->nom_pont ?? ''));
                    if ($idPont > 0 && $nomPont !== '') {
                        $cache[$idPont] = $nomPont;
                    }
                }
            } catch (\Throwable $e) {
                // Ignorer
            }
        }

        if ($cache !== []) {
            return $cache;
        }

        $url = (string) config('services.external_auth.mes_ponts_url', '');
        if ($url === '') {
            return $cache;
        }

        try {
            $response = Http::acceptJson()
                ->withoutVerifying()
                ->timeout((int) config('services.external_auth.timeout', 10))
                ->get($url);

            if ($response->successful()) {
                foreach ($response->json('ponts') ?? [] as $pont) {
                    $idPont = (int) ($pont['id_pont'] ?? 0);
                    $nomPont = trim((string) ($pont['nom_pont'] ?? ''));
                    if ($idPont > 0 && $nomPont !== '') {
                        $cache[$idPont] = $nomPont;
                    }
                }
            }
        } catch (\Throwable $e) {
            // Ignorer
        }

        return $cache;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    public function normalizeTicketRow(array $row): array
    {
        $agentNom = trim((string) ($row['agent_nom'] ?? ''));
        $agentPrenom = trim((string) ($row['agent_prenom'] ?? ''));
        $nomAgent = trim($agentNom . ' ' . $agentPrenom);

        return [
            'id_ticket' => (int) ($row['id_ticket'] ?? 0),
            'numero_ticket' => (string) ($row['numero_ticket'] ?? ''),
            'date_ticket' => $row['date_ticket'] ?? null,
            'matricule_vehicule' => (string) ($row['matricule_vehicule'] ?? ''),
            'vehicule_id' => (int) ($row['vehicule_id'] ?? 0),
            'poids' => (float) ($row['poids'] ?? 0),
            'id_usine' => (int) ($row['id_usine'] ?? 0),
            'nom_usine' => (string) ($row['nom_usine'] ?? ''),
            'id_pont' => (int) ($row['id_pont'] ?? 0),
            'nom_pont' => (string) ($row['nom_pont'] ?? ''),
            'id_agent' => (int) ($row['id_agent'] ?? 0),
            'nom_agent' => $nomAgent !== '' ? $nomAgent : '-',
            'prix_unitaire' => $row['prix_unitaire'] ?? null,
            'montant_paie' => $row['montant_paie'] ?? null,
            'statut_ticket' => $row['statut_ticket'] ?? null,
            'created_at' => $row['created_at'] ?? null,
            'conformite' => null,
            'nom_groupe' => '-',
            'particulier_agent_id' => null,
            'prix_unitaire_agent' => null,
            'montant_calcule' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function normalizeChefRow(array $row): array
    {
        $nom = trim((string) ($row['nom'] ?? ''));
        $prenoms = trim((string) ($row['prenoms'] ?? ''));

        return [
            'id_chef' => (int) ($row['id_chef'] ?? 0),
            'nom' => $nom,
            'prenoms' => $prenoms,
            'nom_complet' => trim($nom . ' ' . $prenoms),
            'token' => (string) ($row['token'] ?? ''),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $tickets
     * @return list<array<string, mixed>>
     */
    public function filterTickets(array $tickets, string $vehicule, string $usine, string $agent): array
    {
        return array_values(array_filter($tickets, function (array $t) use ($vehicule, $usine, $agent) {
            if ($vehicule !== '') {
                $matricule = mb_strtolower((string) ($t['matricule_vehicule'] ?? ''), 'UTF-8');
                if (!str_contains($matricule, mb_strtolower($vehicule, 'UTF-8'))) {
                    return false;
                }
            }

            if ($usine !== '') {
                $nomUsine = mb_strtolower((string) ($t['nom_usine'] ?? ''), 'UTF-8');
                $idUsine = (string) ($t['id_usine'] ?? '');
                $search = mb_strtolower($usine, 'UTF-8');
                if ($idUsine !== $usine && !str_contains($nomUsine, $search)) {
                    return false;
                }
            }

            if ($agent !== '') {
                $nomAgent = mb_strtolower((string) ($t['nom_agent'] ?? ''), 'UTF-8');
                $search = mb_strtolower($agent, 'UTF-8');
                if (ctype_digit($agent)) {
                    if ((int) ($t['id_agent'] ?? 0) !== (int) $agent) {
                        return false;
                    }
                } elseif (!str_contains($nomAgent, $search)) {
                    return false;
                }
            }

            return true;
        }));
    }

    /**
     * Tickets non encore validés (sans ligne dans ticket_validations).
     *
     * @param  list<array<string, mixed>>  $tickets
     * @return list<array<string, mixed>>
     */
    public function filterTicketsNonValides(array $tickets): array
    {
        $ids = array_values(array_filter(array_map(
            static fn (array $t) => (int) ($t['id_ticket'] ?? 0),
            $tickets
        )));

        $numeros = array_values(array_filter(array_map(
            static fn (array $t) => trim((string) ($t['numero_ticket'] ?? '')),
            $tickets
        )));

        if ($ids === [] && $numeros === []) {
            return [];
        }

        // L'id API peut changer : on matche aussi par numéro de ticket.
        $validations = TicketValidation::query()
            ->where(function ($query) use ($ids, $numeros) {
                if ($ids !== []) {
                    $query->whereIn('id_ticket', $ids);
                }
                if ($numeros !== []) {
                    $ids !== []
                        ? $query->orWhereIn('numero_ticket', $numeros)
                        : $query->whereIn('numero_ticket', $numeros);
                }
            })
            ->get(['id_ticket', 'numero_ticket']);

        $validatedIds = $validations->pluck('id_ticket')
            ->map(static fn ($id) => (int) $id)
            ->flip()
            ->all();
        $validatedNumeros = $validations->pluck('numero_ticket')
            ->map(static fn ($n) => trim((string) $n))
            ->filter()
            ->flip()
            ->all();

        return array_values(array_filter(
            $tickets,
            static function (array $t) use ($validatedIds, $validatedNumeros) {
                if (isset($validatedIds[(int) ($t['id_ticket'] ?? 0)])) {
                    return false;
                }

                $numero = trim((string) ($t['numero_ticket'] ?? ''));

                return $numero === '' || ! isset($validatedNumeros[$numero]);
            }
        ));
    }

    public function countTicketsEnAttente(?Request $request = null): int
    {
        $request ??= request();
        $cacheKey = 'tickets_en_attente_count:'.$this->ticketsCacheKey([], $request);

        return (int) Cache::remember($cacheKey, 120, function () use ($request) {
            $chefParams = $this->chefContext->apiQueryParams($request);
            $token = trim((string) ($chefParams['token'] ?? ''));
            $idChef = (int) ($chefParams['id_chef'] ?? 0);

            if ($token === '' && $idChef <= 0) {
                return 0;
            }

            $connection = $this->databaseResolver->connection();
            if ($connection !== null && ! $this->databaseResolver->usesApi()) {
                return $this->countEnAttenteFromDatabase($token, $idChef, $connection);
            }

            $all = $this->fetchAllTickets([], $request);

            return count($this->filterTicketsNonValides($all));
        });
    }

    private function countEnAttenteFromDatabase(string $token, int $idChef, string $connection): int
    {
        $bindings = [];
        $where = ['a.date_suppression IS NULL'];

        if ($token !== '') {
            $where[] = 'ce.token = ?';
            $bindings[] = $token;
        } else {
            $where[] = 'a.id_chef = ?';
            $bindings[] = $idChef;
        }

        $whereSql = implode(' AND ', $where);

        $rows = DB::connection($connection)->select(
            "SELECT t.id_ticket
            FROM tickets t
            INNER JOIN agents a ON a.id_agent = t.id_agent
            INNER JOIN chef_equipe ce ON ce.id_chef = a.id_chef
            WHERE {$whereSql}",
            $bindings
        );

        $ids = array_map(static fn ($row) => (int) $row->id_ticket, $rows);
        if ($ids === []) {
            return 0;
        }

        $validated = TicketValidation::query()
            ->whereIn('id_ticket', $ids)
            ->pluck('id_ticket')
            ->flip()
            ->all();

        return count(array_filter($ids, static fn (int $id) => ! isset($validated[$id])));
    }

    public function forgetEnAttenteCountCache(?Request $request = null): void
    {
        $request ??= request();
        Cache::forget('tickets_en_attente_count:'.$this->ticketsCacheKey([], $request));
    }

    /**
     * @return array{tickets: list<array<string, mixed>>, pagination: null, chef: null, error: string}
     */
    private function emptyResult(string $error): array
    {
        return [
            'tickets' => [],
            'pagination' => null,
            'chef' => null,
            'error' => $error,
        ];
    }
}
