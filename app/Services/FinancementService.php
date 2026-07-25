<?php

namespace App\Services;

use App\Models\BordereauAgent;
use App\Models\DemandeAvance;
use App\Models\Financement;
use App\Models\PaiementAgent;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class FinancementService
{
    public function __construct(
        private CamionsDatabaseResolver $databaseResolver,
        private MesAgentsService $mesAgentsService,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginatedAgentSummaries(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $params = $this->chefListParams($filters);
        if ($params === null) {
            return $this->emptyPaginator($perPage);
        }

        if (! empty($filters['search'])) {
            $params['search'] = $filters['search'];
        }

        $allAgents = $this->mesAgentsService->fetchAllAgents($params);

        if (! empty($filters['agent_id'])) {
            $agentId = (int) $filters['agent_id'];
            $allAgents = array_values(array_filter(
                $allAgents,
                fn (array $agent) => (int) ($agent['id_agent'] ?? 0) === $agentId
            ));
        }

        $agentIds = array_values(array_filter(array_map(
            fn (array $agent) => (int) ($agent['id_agent'] ?? 0),
            $allAgents
        )));

        $statsByAgent = $this->financementStatsForAgentIds($agentIds, $filters);
        $dateFiltersActive = $this->dateFiltersActive($filters);

        $rows = [];
        foreach ($allAgents as $agent) {
            $id = (int) ($agent['id_agent'] ?? 0);
            if ($id <= 0) {
                continue;
            }

            $stats = $statsByAgent[$id] ?? $this->emptyFinancementStats();

            if ($dateFiltersActive && ! $stats['has_activity']) {
                continue;
            }

            $nom = trim((string) ($agent['nom_complet'] ?? ''));
            if ($nom === '') {
                $nom = trim(($agent['nom_agent'] ?? $agent['nom'] ?? '') . ' ' . ($agent['prenom_agent'] ?? $agent['prenom'] ?? ''));
            }

            $rows[] = (object) [
                'id_agent' => $id,
                'nom_agent' => $nom !== '' ? $nom : ('Agent #' . $id),
                'numero_agent' => $agent['numero_agent'] ?? null,
                'nombre_financements' => $stats['nombre'],
                'montant_initial' => $stats['montant_initial'],
                'montant_rembourse' => $stats['montant_rembourse'],
                'solde_financement' => $stats['solde'],
                'date_ajout' => $agent['date_ajout'] ?? null,
            ];
        }

        usort($rows, function ($a, $b) {
            $dateA = $a->date_ajout ? strtotime((string) $a->date_ajout) : 0;
            $dateB = $b->date_ajout ? strtotime((string) $b->date_ajout) : 0;
            if ($dateA !== $dateB) {
                return $dateB <=> $dateA;
            }

            return $b->id_agent <=> $a->id_agent;
        });

        $page = max(1, (int) ($filters['page'] ?? 1));
        $total = count($rows);
        $slice = array_slice($rows, ($page - 1) * $perPage, $perPage);

        return new Paginator(
            $slice,
            $total,
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => request()->query()]
        );
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, object>
     */
    public function detailedList(array $filters): Collection
    {
        $connection = $this->financementConnection();
        if ($connection === null || ! $this->hasFinancementOnConnection($connection)) {
            return collect();
        }

        $agentIds = $this->chefAgentIds($filters);
        if ($agentIds === null || $agentIds === []) {
            return collect();
        }

        $query = DB::connection($connection)->table('financement')->whereIn('id_agent', $agentIds);

        if (! empty($filters['agent_id'])) {
            $query->where('id_agent', (int) $filters['agent_id']);
        }

        if (! empty($filters['search'])) {
            $term = '%' . $filters['search'] . '%';
            $query->where(function ($sub) use ($term, $connection) {
                $sub->where('Numero_financement', 'like', $term)
                    ->orWhere('motif', 'like', $term);
                if ($this->hasColumn($connection, 'financement', 'code_financement')) {
                    $sub->orWhere('code_financement', 'like', $term);
                }
            });
        }

        if (! empty($filters['date_debut']) && ! empty($filters['date_fin'])) {
            $query->whereBetween(DB::raw('DATE(date_financement)'), [
                $filters['date_debut'],
                $filters['date_fin'],
            ]);
        } elseif (! empty($filters['date_debut'])) {
            $query->whereDate('date_financement', '>=', $filters['date_debut']);
        } elseif (! empty($filters['date_fin'])) {
            $query->whereDate('date_financement', '<=', $filters['date_fin']);
        }

        $rows = $query->orderByDesc('Numero_financement')->get();
        $agentsById = collect($this->listAgentsForSelect($filters))->keyBy('id_agent');

        return $rows->map(function ($row) use ($agentsById) {
            $id = (int) $row->id_agent;
            $agent = $agentsById->get($id);

            return (object) [
                'Numero_financement' => $row->Numero_financement,
                'code_financement' => $row->code_financement ?? null,
                'id_agent' => $id,
                'montant' => $row->montant,
                'motif' => $row->motif,
                'motif_affiche' => Financement::formatMotifAffiche($row->motif ?? null),
                'date_financement' => $row->date_financement,
                'nom_agent' => $agent['nom_complet'] ?? ('Agent #' . $id),
            ];
        });
    }

    /**
     * @return array{montant_initial: float, montant_rembourse: float, solde_financement: float, total_operations: int}
     */
    public function statsForAgent(int $agentId): array
    {
        $this->synchroniserAvancesAgents([$agentId]);

        $connection = $this->financementConnection();
        if ($connection === null || ! $this->hasFinancementOnConnection($connection)) {
            return [
                'montant_initial' => 0.0,
                'montant_rembourse' => 0.0,
                'solde_financement' => 0.0,
                'total_operations' => 0,
            ];
        }

        $rows = DB::connection($connection)
            ->table('financement')
            ->where('id_agent', $agentId)
            ->get(['montant']);

        $montantInitial = 0.0;
        $montantRembourse = 0.0;
        $solde = 0.0;

        foreach ($rows as $row) {
            $montant = (float) $row->montant;
            if ($montant > 0) {
                $montantInitial += $montant;
            } else {
                $montantRembourse += abs($montant);
            }
            $solde += $montant;
        }

        return [
            'montant_initial' => $montantInitial,
            'montant_rembourse' => $montantRembourse,
            'solde_financement' => max(0, $solde),
            'total_operations' => $rows->count(),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginatedAgentHistory(int $agentId, array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $connection = $this->financementConnection();
        if ($connection === null || ! $this->hasFinancementOnConnection($connection)) {
            return $this->emptyPaginator($perPage);
        }

        $query = Financement::on($connection)->where('id_agent', $agentId);

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search, $connection) {
                $q->where('Numero_financement', 'like', '%' . $search . '%')
                    ->orWhere('motif', 'like', '%' . $search . '%');
                if ($this->hasColumn($connection, 'financement', 'code_financement')) {
                    $q->orWhere('code_financement', 'like', '%' . $search . '%');
                }
            });
        }

        if (($filters['type_filter'] ?? '') === 'financement') {
            $query->where('montant', '>', 0);
        } elseif (($filters['type_filter'] ?? '') === 'remboursement') {
            $query->where('montant', '<', 0);
        }

        if (! empty($filters['date_debut'])) {
            $query->whereDate('date_financement', '>=', $filters['date_debut']);
        }

        if (! empty($filters['date_fin'])) {
            $query->whereDate('date_financement', '<=', $filters['date_fin']);
        }

        return $query
            ->orderByDesc('date_financement')
            ->orderByDesc('Numero_financement')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function create(int $agentId, float $montant, string $motif): Financement
    {
        return $this->createAt($agentId, $montant, $motif, now());
    }

    public function createAt(int $agentId, float $montant, string $motif, \DateTimeInterface|string $dateFinancement): Financement
    {
        $connection = $this->financementConnection();
        if ($connection === null || ! $this->hasFinancementOnConnection($connection)) {
            throw new \RuntimeException('Table financement indisponible.');
        }

        return DB::connection($connection)->transaction(function () use ($connection, $agentId, $montant, $motif, $dateFinancement) {
            Financement::on($connection)
                ->orderByDesc('Numero_financement')
                ->lockForUpdate()
                ->first();

            $nextNumero = ((int) Financement::on($connection)->max('Numero_financement')) + 1;
            $payload = [
                'Numero_financement' => $nextNumero,
                'id_agent' => $agentId,
                'montant' => $montant,
                'motif' => $motif,
                'date_financement' => $dateFinancement,
            ];

            if ($this->hasColumn($connection, 'financement', 'code_financement')) {
                $payload['code_financement'] = $this->generateCodeFinancement($connection, $agentId);
            }

            return Financement::on($connection)->create($payload);
        });
    }

    public function generateCodeFinancement(string $connection, int $agentId): string
    {
        $agent = $this->findAgent($agentId);
        $initials = $this->agentInitials((string) ($agent['nom_complet'] ?? ''));
        $prefix = 'FIN-'.$initials;

        $existing = Financement::on($connection)
            ->where('code_financement', 'like', $prefix.'-%')
            ->pluck('code_financement');

        $maxSeq = 0;
        foreach ($existing as $code) {
            if (preg_match('/-(\d{4})$/', (string) $code, $m)) {
                $maxSeq = max($maxSeq, (int) $m[1]);
            }
        }

        return $prefix.'-'.sprintf('%04d', $maxSeq + 1);
    }

    private function agentInitials(string $nomComplet): string
    {
        $parts = array_values(array_filter(preg_split('/\s+/u', trim($nomComplet)) ?: []));
        $nom = $parts[0] ?? '';
        $prenom = $parts[1] ?? '';

        $letter = function (string $word): string {
            if ($word === '') {
                return '';
            }
            $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $word);
            $src = is_string($ascii) && $ascii !== '' ? $ascii : $word;

            return strtoupper(substr($src, 0, 1));
        };

        $initials = $letter($nom).$letter($prenom);

        return $initials !== '' ? $initials : 'XX';
    }

    /**
     * Crée un financement pour une avance agent financier (idempotent par paiement).
     */
    public function enregistrerFinancementDepuisAvance(PaiementAgent $paiement): ?Financement
    {
        if ($paiement->id_bordereau !== null) {
            return null;
        }

        // Déjà crédité côté Unipalm lors du paiement d'une demande d'avance API.
        $paiementId = (int) $paiement->id;
        if (
            DemandeAvance::query()
                ->where('paiement_agent_id', $paiementId)
                ->where('source', DemandeAvance::SOURCE_API)
                ->exists()
        ) {
            return null;
        }

        $connection = $this->financementConnection();
        if ($connection === null || ! $this->hasFinancementOnConnection($connection)) {
            return null;
        }

        $ref = $this->referenceAvancePaiement($paiementId);
        $exists = Financement::on($connection)
            ->where('id_agent', (int) $paiement->id_agent)
            ->where('motif', 'like', '%' . $ref . '%')
            ->exists();

        if ($exists) {
            return null;
        }

        $motifParts = ['Avance agent financier'];
        if ($paiement->numero_recu) {
            $motifParts[] = 'Reçu ' . $paiement->numero_recu;
        }
        $commentaire = trim((string) ($paiement->commentaire ?? ''));
        if ($commentaire !== '' && strcasecmp($commentaire, 'Avance') !== 0) {
            $motifParts[] = $commentaire;
        }
        $motifParts[] = $ref;

        return $this->createAt(
            (int) $paiement->id_agent,
            (float) $paiement->montant,
            implode(' — ', $motifParts),
            $paiement->date_paiement ?? now(),
        );
    }

    /**
     * @param  list<int>  $agentIds
     */
    public function synchroniserAvancesAgents(array $agentIds): int
    {
        $agentIds = array_values(array_unique(array_filter(array_map('intval', $agentIds))));
        if ($agentIds === []) {
            return 0;
        }

        $count = 0;
        $paiements = PaiementAgent::query()
            ->whereIn('id_agent', $agentIds)
            ->whereNull('id_bordereau')
            ->orderBy('id')
            ->get();

        foreach ($paiements as $paiement) {
            if ($this->enregistrerFinancementDepuisAvance($paiement) !== null) {
                $count++;
            }
        }

        return $count;
    }

    private function referenceAvancePaiement(int $paiementId): string
    {
        return 'AVANCE-PAIEMENT-' . $paiementId;
    }

    private function referencePaiementBordereau(int $paiementId): string
    {
        return 'PAIEMENT-BORDEREAU-' . $paiementId;
    }

    /**
     * Déduit le paiement bordereau du solde financement (remboursement négatif).
     *
     * @return int Montant effectivement déduit du financement
     */
    public function deduireFinancementPourPaiementBordereau(PaiementAgent $paiement, BordereauAgent $bordereau): int
    {
        if ($paiement->id_bordereau === null) {
            return 0;
        }

        $connection = $this->financementConnection();
        if ($connection === null || ! $this->hasFinancementOnConnection($connection)) {
            return 0;
        }

        $ref = $this->referencePaiementBordereau((int) $paiement->id);
        if (Financement::on($connection)->where('motif', 'like', '%' . $ref . '%')->exists()) {
            return 0;
        }

        $this->synchroniserAvancesAgents([(int) $paiement->id_agent]);
        $solde = (int) round($this->statsForAgent((int) $paiement->id_agent)['solde_financement'] ?? 0);
        if ($solde <= 0) {
            return 0;
        }

        $montantPaye = (int) round((float) $paiement->montant);
        $aDeduire = min($montantPaye, $solde);
        if ($aDeduire <= 0) {
            return 0;
        }

        $this->createAt(
            (int) $paiement->id_agent,
            -$aDeduire,
            'Paiement bordereau ' . $bordereau->numero . ' — ' . $ref,
            $paiement->date_paiement ?? now(),
        );

        return $aDeduire;
    }

    public function soldeFinancementAgent(int $agentId): int
    {
        $this->synchroniserAvancesAgents([$agentId]);

        return (int) round($this->statsForAgent($agentId)['solde_financement'] ?? 0);
    }

    /**
     * @param  list<int>  $agentIds
     * @return array<int, int>
     */
    public function soldesFinancementByAgentIds(array $agentIds): array
    {
        $stats = $this->financementStatsForAgentIds($agentIds, []);
        $soldes = [];
        foreach ($stats as $id => $row) {
            $soldes[(int) $id] = (int) round($row['solde'] ?? 0);
        }

        return $soldes;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return list<array{id_agent: int, nom_complet: string, numero_agent: string|null}>
     */
    public function listAgentsForSelect(array $filters = []): array
    {
        $params = $this->chefListParams($filters);
        if ($params === null) {
            return [];
        }

        $agents = [];
        foreach ($this->mesAgentsService->fetchAllAgents($params) as $agent) {
            $id = (int) ($agent['id_agent'] ?? 0);
            if ($id <= 0) {
                continue;
            }

            $nom = trim((string) ($agent['nom_complet'] ?? ''));
            if ($nom === '') {
                $nom = trim(($agent['nom_agent'] ?? '') . ' ' . ($agent['prenom_agent'] ?? ''));
            }

            $agents[] = [
                'id_agent' => $id,
                'nom_complet' => $nom !== '' ? $nom : ('Agent #' . $id),
                'numero_agent' => $agent['numero_agent'] ?? null,
            ];
        }

        usort($agents, fn ($a, $b) => strcasecmp($a['nom_complet'], $b['nom_complet']));

        return $agents;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function agentAccessible(int $idAgent, array $filters): bool
    {
        if ($idAgent <= 0) {
            return false;
        }

        $ids = $this->chefAgentIds($filters);
        if ($ids === null) {
            return false;
        }

        return in_array($idAgent, $ids, true);
    }

    /**
     * @return array{id_agent: int, nom_complet: string, numero_agent: string|null}|null
     */
    public function findAgent(int $idAgent): ?array
    {
        if ($idAgent <= 0) {
            return null;
        }

        $fromApi = $this->mesAgentsService->findAgentById($idAgent);
        if ($fromApi) {
            $nom = trim((string) ($fromApi['nom_complet'] ?? ''));
            if ($nom === '') {
                $nom = trim(($fromApi['nom_agent'] ?? '') . ' ' . ($fromApi['prenom_agent'] ?? ''));
            }

            return [
                'id_agent' => $idAgent,
                'nom_complet' => $nom ?: ('Agent #' . $idAgent),
                'numero_agent' => $fromApi['numero_agent'] ?? null,
            ];
        }

        $connection = $this->agentsConnection();
        if ($connection === null) {
            return null;
        }

        $agentNameExpr = $this->agentNameExpression('a');
        $row = DB::connection($connection)->selectOne(
            "SELECT a.id_agent, a.numero_agent, {$agentNameExpr} AS nom_complet
            FROM agents a
            WHERE a.id_agent = ?
            LIMIT 1",
            [$idAgent]
        );

        if (! $row) {
            return null;
        }

        return [
            'id_agent' => (int) $row->id_agent,
            'nom_complet' => trim((string) ($row->nom_complet ?? '')) ?: ('Agent #' . $idAgent),
            'numero_agent' => $row->numero_agent ?? null,
        ];
    }

    public function financementConnection(): ?string
    {
        foreach ($this->candidateConnections() as $connection) {
            if ($this->hasFinancementOnConnection($connection)) {
                return $connection;
            }
        }

        return null;
    }

    private function agentsConnection(): ?string
    {
        return $this->databaseResolver->connection()
            ?? $this->databaseResolver->connectionForAuth()
            ?? (Schema::hasTable('agents') ? (string) config('database.default') : null);
    }

    /**
     * @return list<string>
     */
    private function candidateConnections(): array
    {
        $connections = array_filter([
            $this->databaseResolver->connection(),
            $this->databaseResolver->connectionForAuth(),
            (string) config('database.default'),
        ]);

        return array_values(array_unique($connections));
    }

    private function hasFinancementOnConnection(string $connection): bool
    {
        try {
            return Schema::connection($connection)->hasTable('financement');
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function hasColumn(string $connection, string $table, string $column): bool
    {
        try {
            return Schema::connection($connection)->hasColumn($table, $column);
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function agentNameExpression(string $alias): string
    {
        $connection = $this->agentsConnection() ?? (string) config('database.default');

        if ($this->hasColumn($connection, 'agents', 'nom_complet')) {
            return "COALESCE(NULLIF(TRIM({$alias}.nom_complet), ''), {$alias}.numero_agent, 'Agent')";
        }

        return "TRIM(CONCAT(COALESCE({$alias}.nom, ''), ' ', COALESCE({$alias}.prenom, '')))";
    }

    private function agentActiveWhere(string $alias): string
    {
        $connection = $this->agentsConnection() ?? (string) config('database.default');

        if ($this->hasColumn($connection, 'agents', 'date_suppression')) {
            return "{$alias}.date_suppression IS NULL";
        }

        return '1=1';
    }

    private function agentGroupByColumns(string $alias): string
    {
        $connection = $this->agentsConnection() ?? (string) config('database.default');
        $columns = ["{$alias}.id_agent"];

        if ($this->hasColumn($connection, 'agents', 'numero_agent')) {
            $columns[] = "{$alias}.numero_agent";
        }
        if ($this->hasColumn($connection, 'agents', 'nom_complet')) {
            $columns[] = "{$alias}.nom_complet";
        }
        if ($this->hasColumn($connection, 'agents', 'nom')) {
            $columns[] = "{$alias}.nom";
        }
        if ($this->hasColumn($connection, 'agents', 'prenom')) {
            $columns[] = "{$alias}.prenom";
        }

        return implode(', ', $columns);
    }

    private function emptyPaginator(int $perPage): LengthAwarePaginator
    {
        return new Paginator([], 0, $perPage, 1, [
            'path' => request()->url(),
            'query' => request()->query(),
        ]);
    }

    /**
     * @param  list<int>  $agentIds
     * @param  array<string, mixed>  $filters
     * @return array<int, array{nombre: int, montant_initial: float, montant_rembourse: float, solde: float, has_activity: bool}>
     */
    private function financementStatsForAgentIds(array $agentIds, array $filters): array
    {
        $stats = [];
        foreach ($agentIds as $id) {
            $stats[$id] = $this->emptyFinancementStats();
        }

        if ($agentIds === []) {
            return $stats;
        }

        $this->synchroniserAvancesAgents($agentIds);

        $connection = $this->financementConnection();
        if ($connection === null || ! $this->hasFinancementOnConnection($connection)) {
            return $stats;
        }

        $query = DB::connection($connection)->table('financement')->whereIn('id_agent', $agentIds);

        if (! empty($filters['date_debut']) && ! empty($filters['date_fin'])) {
            $query->whereBetween(DB::raw('DATE(date_financement)'), [
                $filters['date_debut'],
                $filters['date_fin'],
            ]);
        } elseif (! empty($filters['date_debut'])) {
            $query->whereDate('date_financement', '>=', $filters['date_debut']);
        } elseif (! empty($filters['date_fin'])) {
            $query->whereDate('date_financement', '<=', $filters['date_fin']);
        }

        foreach ($query->get() as $row) {
            $id = (int) $row->id_agent;
            if (! isset($stats[$id])) {
                continue;
            }

            $montant = (float) $row->montant;
            $stats[$id]['nombre']++;
            $stats[$id]['has_activity'] = true;
            if ($montant > 0) {
                $stats[$id]['montant_initial'] += $montant;
            } else {
                $stats[$id]['montant_rembourse'] += abs($montant);
            }
            $stats[$id]['solde'] += $montant;
        }

        foreach ($stats as $id => $row) {
            $stats[$id]['solde'] = max(0, $row['solde']);
        }

        return $stats;
    }

    /**
     * @return array{nombre: int, montant_initial: float, montant_rembourse: float, solde: float, has_activity: bool}
     */
    private function emptyFinancementStats(): array
    {
        return [
            'nombre' => 0,
            'montant_initial' => 0.0,
            'montant_rembourse' => 0.0,
            'solde' => 0.0,
            'has_activity' => false,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function dateFiltersActive(array $filters): bool
    {
        return ! empty($filters['date_debut']) || ! empty($filters['date_fin']);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{token?: string, id_chef?: int}|null
     */
    private function chefListParams(array $filters): ?array
    {
        $token = trim((string) ($filters['token'] ?? ''));
        $idChef = (int) ($filters['id_chef'] ?? 0);

        if ($token === '' && $idChef <= 0) {
            return null;
        }

        $params = [];
        if ($token !== '') {
            $params['token'] = $token;
        }
        if ($idChef > 0) {
            $params['id_chef'] = $idChef;
        }

        return $params;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return list<int>|null
     */
    private function chefAgentIds(array $filters): ?array
    {
        $params = $this->chefListParams($filters);
        if ($params === null) {
            return null;
        }

        $ids = [];
        foreach ($this->mesAgentsService->fetchAllAgents($params) as $agent) {
            $id = (int) ($agent['id_agent'] ?? 0);
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }
}
