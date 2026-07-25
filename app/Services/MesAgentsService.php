<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class MesAgentsService
{
    public function __construct(
        private CamionsDatabaseResolver $databaseResolver,
        private ChefEquipeContext $chefContext,
    ) {}

    public function resolveToken(?Request $request = null): string
    {
        return $this->chefContext->resolveToken($request);
    }

    /**
     * @param  array{token?: string, id_chef?: int, search?: string, sous_groupe?: string, page?: int, per_page?: int}  $params
     * @return array{agents: list<array<string, mixed>>, pagination: array<string, int>|null, chefs: list<array<string, mixed>>, error: string|null}
     */
    public function listAgents(array $params = [], ?Request $request = null): array
    {
        $request ??= request();

        $token = trim((string) ($params['token'] ?? ''));
        $idChef = (int) ($params['id_chef'] ?? 0);
        $search = trim((string) ($params['search'] ?? ''));
        $sousGroupe = $this->normalizeSousGroupe((string) ($params['sous_groupe'] ?? ''));
        $page = max(1, (int) ($params['page'] ?? 1));
        $perPage = max(1, min(100, (int) ($params['per_page'] ?? 15)));

        if ($token === '' && $idChef <= 0) {
            $chefParams = $this->chefContext->apiQueryParams($request);
            $token = trim((string) ($chefParams['token'] ?? ''));
            $idChef = (int) ($chefParams['id_chef'] ?? 0);
        }

        if ($token === '' && $idChef <= 0) {
            return $this->emptyResult('Le paramètre token ou id_chef est requis.');
        }

        // Filtre sous-groupe : lecture Unipalm locale (même en mode API).
        $dbConnection = $this->databaseResolver->connection();
        if ($sousGroupe !== '' && $dbConnection === null) {
            $dbConnection = $this->databaseResolver->connectionForAuth();
        }

        if ($dbConnection !== null && (! $this->databaseResolver->usesApi() || $sousGroupe !== '')) {
            return $this->fetchFromDatabase($token, $idChef, $search, $page, $perPage, $sousGroupe, $dbConnection);
        }

        if ($this->databaseResolver->usesApi()) {
            return $this->fetchFromExternalApi($token, $idChef, $search, $page, $perPage);
        }

        if ($this->databaseResolver->usesDatabaseOnly()) {
            return $this->emptyResult('Connexion base camions non configurée.');
        }

        return $this->fetchFromExternalApi($token, $idChef, $search, $page, $perPage);
    }

    private function normalizeSousGroupe(string $value): string
    {
        $value = strtolower(trim($value));

        return match ($value) {
            'particulier', 'particuliers' => 'particulier',
            'professionnel', 'professionnels' => 'professionnel',
            default => '',
        };
    }

    /**
     * @return list<int>
     */
    public function chefAgentIds(?Request $request = null): array
    {
        $request ??= request();

        if ($this->cachedChefAgentIds !== null) {
            return $this->cachedChefAgentIds;
        }

        $ids = [];
        foreach ($this->fetchAllAgents([], $request) as $agent) {
            $id = (int) ($agent['id_agent'] ?? 0);
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return $this->cachedChefAgentIds = array_values(array_unique($ids));
    }

    private ?array $cachedChefAgentIds = null;

    /** @var array<string, list<array<string, mixed>>> */
    private array $cachedAllAgents = [];

    /** @var array<int, array<string, mixed>|null> */
    private array $cachedAgentsById = [];

    public function findAgentById(int $idAgent): ?array
    {
        if ($idAgent <= 0) {
            return null;
        }

        if (array_key_exists($idAgent, $this->cachedAgentsById)) {
            return $this->cachedAgentsById[$idAgent];
        }

        $connection = $this->databaseResolver->connection();
        if ($connection !== null) {
            try {
                $row = DB::connection($connection)->selectOne(
                    'SELECT
                        a.id_agent,
                        a.numero_agent,
                        a.nom,
                        a.prenom,
                        a.contact,
                        a.date_ajout,
                        a.id_chef,
                        ce.nom AS chef_nom,
                        ce.prenoms AS chef_prenoms,
                        ce.token AS chef_token
                    FROM agents a
                    INNER JOIN chef_equipe ce ON ce.id_chef = a.id_chef
                    WHERE a.id_agent = ?
                      AND a.date_suppression IS NULL
                    LIMIT 1',
                    [$idAgent]
                );

                $agent = $row ? $this->normalizeAgentRow((array) $row) : null;

                return $this->cachedAgentsById[$idAgent] = $agent;
            } catch (\Throwable $e) {
                return $this->cachedAgentsById[$idAgent] = null;
            }
        }

        $agent = $this->findAgentByIdFromCacheOrApi($idAgent);

        return $this->cachedAgentsById[$idAgent] = $agent;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function fetchAllAgents(array $params = [], ?Request $request = null): array
    {
        $cacheKey = $this->agentsCacheKey($params, $request);
        if (isset($this->cachedAllAgents[$cacheKey])) {
            return $this->cachedAllAgents[$cacheKey];
        }

        $all = [];
        $page = 1;

        do {
            $result = $this->listAgents(array_merge($params, ['page' => $page]), $request);
            if ($result['error']) {
                break;
            }

            $batch = $result['agents'];
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
        } while ($page <= 50);

        foreach ($all as $agent) {
            $id = (int) ($agent['id_agent'] ?? 0);
            if ($id > 0) {
                $this->cachedAgentsById[$id] = $agent;
            }
        }

        return $this->cachedAllAgents[$cacheKey] = $all;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listChefs(?string $token = null): array
    {
        if ($token !== null && $token !== '') {
            $chef = $this->databaseResolver->findChefByToken($token);

            return $chef ? [$chef] : [];
        }

        return $this->databaseResolver->listChefsEquipe();
    }

    /**
     * @return array{total: int, particuliers: int, professionnels: int}
     */
    public function countBySousGroupe(?Request $request = null): array
    {
        $request ??= request();
        $empty = ['total' => 0, 'particuliers' => 0, 'professionnels' => 0];

        $token = $this->chefContext->resolveToken($request);
        $idChef = (int) ($this->chefContext->apiQueryParams($request)['id_chef'] ?? 0);

        $connection = $this->databaseResolver->connection()
            ?? $this->databaseResolver->connectionForAuth();

        if ($connection === null) {
            return $empty;
        }

        if ($token === '' && $idChef <= 0) {
            return $empty;
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
            $hasSousGroupe = \Illuminate\Support\Facades\Schema::connection($connection)
                ->hasColumn('agents', 'sous_groupe');

            if (! $hasSousGroupe) {
                $countRow = DB::connection($connection)->selectOne(
                    "SELECT COUNT(*) AS total
                    FROM agents a
                    INNER JOIN chef_equipe ce ON ce.id_chef = a.id_chef
                    WHERE {$whereSql}",
                    $bindings
                );

                return [
                    'total' => (int) ($countRow->total ?? 0),
                    'particuliers' => 0,
                    'professionnels' => 0,
                ];
            }

            $row = DB::connection($connection)->selectOne(
                "SELECT
                    COUNT(*) AS total,
                    SUM(CASE WHEN LOWER(TRIM(COALESCE(a.sous_groupe, ''))) IN ('particulier', 'particuliers') THEN 1 ELSE 0 END) AS particuliers,
                    SUM(CASE WHEN LOWER(TRIM(COALESCE(a.sous_groupe, ''))) IN ('professionnel', 'professionnels') THEN 1 ELSE 0 END) AS professionnels
                FROM agents a
                INNER JOIN chef_equipe ce ON ce.id_chef = a.id_chef
                WHERE {$whereSql}",
                $bindings
            );

            return [
                'total' => (int) ($row->total ?? 0),
                'particuliers' => (int) ($row->particuliers ?? 0),
                'professionnels' => (int) ($row->professionnels ?? 0),
            ];
        } catch (\Throwable $e) {
            return $empty;
        }
    }

    private function fetchFromDatabase(
        string $token,
        int $idChef,
        string $search,
        int $page,
        int $perPage,
        string $sousGroupe = '',
        ?string $connection = null
    ): array {
        $connection ??= $this->databaseResolver->connection();
        if ($connection === null) {
            return $this->emptyResult('Connexion base camions non configurée.');
        }

        try {
            $bindings = [];
            $where = ['a.date_suppression IS NULL'];

            if ($token === '' && $idChef <= 0) {
                return $this->emptyResult('Le paramètre token ou id_chef est requis.');
            }

            if ($token !== '') {
                $where[] = 'ce.token = ?';
                $bindings[] = $token;
            } elseif ($idChef > 0) {
                $where[] = 'a.id_chef = ?';
                $bindings[] = $idChef;
            }

            if ($search !== '') {
                $term = '%' . $search . '%';
                $where[] = '(a.nom LIKE ? OR a.prenom LIKE ? OR a.numero_agent LIKE ? OR CONCAT(a.nom, \' \', a.prenom) LIKE ?)';
                array_push($bindings, $term, $term, $term, $term);
            }

            if ($sousGroupe !== '') {
                $hasSousGroupe = \Illuminate\Support\Facades\Schema::connection($connection)
                    ->hasColumn('agents', 'sous_groupe');

                if ($hasSousGroupe) {
                    if ($sousGroupe === 'particulier') {
                        $where[] = "LOWER(TRIM(COALESCE(a.sous_groupe, ''))) IN ('particulier', 'particuliers')";
                    } else {
                        $where[] = "LOWER(TRIM(COALESCE(a.sous_groupe, ''))) IN ('professionnel', 'professionnels')";
                    }
                }
            }

            $whereSql = implode(' AND ', $where);

            $countRow = DB::connection($connection)->selectOne(
                "SELECT COUNT(*) AS total
                FROM agents a
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
                    a.id_agent,
                    a.numero_agent,
                    a.nom,
                    a.prenom,
                    a.contact,
                    a.date_ajout,
                    a.id_chef,
                    ce.nom AS chef_nom,
                    ce.prenoms AS chef_prenoms,
                    ce.token AS chef_token
                FROM agents a
                INNER JOIN chef_equipe ce ON ce.id_chef = a.id_chef
                WHERE {$whereSql}
                ORDER BY a.date_ajout DESC, a.id_agent DESC
                LIMIT {$perPage} OFFSET {$offset}",
                $bindings
            );

            $agents = array_map(
                fn ($row) => $this->normalizeAgentRow((array) $row),
                $rows
            );

            $chefs = $this->listChefs($token !== '' ? $token : null);
            if ($chefs === [] && $agents !== []) {
                $chefsMap = [];
                foreach ($agents as $agent) {
                    $chef = $agent['chef_equipe'] ?? null;
                    if (is_array($chef) && !empty($chef['id_chef'])) {
                        $chefsMap[$chef['id_chef']] = $chef;
                    }
                }
                $chefs = array_values($chefsMap);
            }

            return [
                'agents' => $agents,
                'pagination' => [
                    'current_page' => $page,
                    'last_page' => $lastPage,
                    'per_page' => $perPage,
                    'total' => $total,
                ],
                'chefs' => $chefs,
                'error' => null,
            ];
        } catch (\Throwable $e) {
            return $this->emptyResult('Erreur lors de la lecture des agents : ' . $e->getMessage());
        }
    }

    /**
     * @return array{agents: list<array<string, mixed>>, pagination: array<string, int>|null, chefs: list<array<string, mixed>>, error: string|null}
     */
    private function fetchFromExternalApi(
        string $token,
        int $idChef,
        string $search,
        int $page,
        int $perPage
    ): array {
        $url = (string) config('services.external_auth.mes_agents_url', '');
        if ($url === '') {
            return $this->emptyResult('URL API mes_agents non configurée.');
        }

        if ($token === '' && $idChef <= 0) {
            return $this->emptyResult('Le paramètre token ou id_chef est requis.');
        }

        $queryParams = [
            'page' => $page,
            'per_page' => $perPage,
        ];

        if ($search !== '') {
            $queryParams['search'] = $search;
        }
        if ($token !== '') {
            $queryParams['token'] = $token;
        } elseif ($idChef > 0) {
            $queryParams['id_chef'] = $idChef;
        }

        try {
            $response = Http::acceptJson()
                ->withoutVerifying()
                ->timeout((int) config('services.external_auth.timeout', 10))
                ->get($url, $queryParams);
        } catch (\Throwable $e) {
            return $this->emptyResult('Impossible de joindre le service agents.');
        }

        if (!$response->successful()) {
            return $this->emptyResult((string) ($response->json('error') ?? 'Erreur API agents.'));
        }

        $agents = $response->json('agents');
        if (!is_array($agents)) {
            $agents = [];
        }

        $chefs = $response->json('chefs');
        if (!is_array($chefs)) {
            $chefs = [];
            foreach ($agents as $agent) {
                if (!empty($agent['chef_equipe']['id_chef'])) {
                    $chefId = (int) $agent['chef_equipe']['id_chef'];
                    $chefs[$chefId] = $agent['chef_equipe'];
                }
            }
            $chefs = array_values($chefs);
        }

        return [
            'agents' => $agents,
            'pagination' => is_array($response->json('pagination')) ? $response->json('pagination') : null,
            'chefs' => $chefs,
            'error' => null,
        ];
    }

    private function findAgentByIdFromCacheOrApi(int $idAgent, ?Request $request = null): ?array
    {
        $request ??= request();

        foreach ($this->fetchAllAgents([], $request) as $agent) {
            if ((int) ($agent['id_agent'] ?? 0) === $idAgent) {
                return $agent;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function agentsCacheKey(array $params, ?Request $request): string
    {
        $request ??= request();
        $chefParams = $this->chefContext->apiQueryParams($request);

        return md5(json_encode([
            'params' => $params,
            'chef' => $chefParams,
        ]));
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function normalizeAgentRow(array $row): array
    {
        $nom = trim((string) ($row['nom'] ?? ''));
        $prenom = trim((string) ($row['prenom'] ?? $row['prenoms'] ?? ''));

        return [
            'id_agent' => (int) ($row['id_agent'] ?? 0),
            'numero_agent' => (string) ($row['numero_agent'] ?? ''),
            'nom' => $nom,
            'prenom' => $prenom,
            'nom_complet' => trim($nom . ' ' . $prenom),
            'contact' => (string) ($row['contact'] ?? ''),
            'date_ajout' => $row['date_ajout'] ?? null,
            'id_chef' => (int) ($row['id_chef'] ?? 0),
            'chef_equipe' => $this->normalizeChefRow([
                'id_chef' => $row['id_chef'] ?? 0,
                'nom' => $row['chef_nom'] ?? '',
                'prenoms' => $row['chef_prenoms'] ?? '',
                'token' => $row['chef_token'] ?? '',
            ]),
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
     * @return array{agents: list<array<string, mixed>>, pagination: null, chefs: list<array<string, mixed>>, error: string}
     */
    private function emptyResult(string $error): array
    {
        return [
            'agents' => [],
            'pagination' => null,
            'chefs' => [],
            'error' => $error,
        ];
    }
}
