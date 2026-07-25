<?php

namespace App\Services;

use App\Models\FicheSortie;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class FichesSortieStatsService
{
    public function __construct(
        private ChefEquipeContext $chefContext,
        private MesAgentsService $mesAgentsService,
        private CamionsDatabaseResolver $databaseResolver,
    ) {}

    /**
     * @return array{total: int, en_attente: int, dechargees: int}
     */
    public function stats(?Request $request = null): array
    {
        $request ??= request();
        $idChef = $this->chefContext->resolveIdChef($request);
        $cacheKey = 'fiches_sortie_stats:' . ($idChef ?? 'guest');

        return Cache::remember($cacheKey, 60, function () use ($request, $idChef) {
            $agentIds = $this->agentIdsPourStats($idChef, $request);

            if ($agentIds === []) {
                return [
                    'total' => 0,
                    'en_attente' => 0,
                    'dechargees' => 0,
                ];
            }

            $row = FicheSortie::query()
                ->whereIn('id_agent', $agentIds)
                ->selectRaw('COUNT(*) as total')
                ->selectRaw('SUM(CASE WHEN date_dechargement IS NULL THEN 1 ELSE 0 END) as en_attente')
                ->selectRaw('SUM(CASE WHEN date_dechargement IS NOT NULL THEN 1 ELSE 0 END) as dechargees')
                ->first();

            return [
                'total' => (int) ($row->total ?? 0),
                'en_attente' => (int) ($row->en_attente ?? 0),
                'dechargees' => (int) ($row->dechargees ?? 0),
            ];
        });
    }

    /**
     * @return list<int>
     */
    private function agentIdsPourStats(?int $idChef, Request $request): array
    {
        if ($idChef !== null && $idChef > 0) {
            $connection = $this->databaseResolver->connection();
            if ($connection !== null) {
                try {
                    $rows = DB::connection($connection)->select(
                        'SELECT id_agent FROM agents WHERE id_chef = ? AND date_suppression IS NULL',
                        [$idChef]
                    );

                    $ids = array_values(array_unique(array_map(
                        static fn ($row) => (int) ($row->id_agent ?? 0),
                        $rows
                    )));

                    return array_values(array_filter($ids, static fn (int $id) => $id > 0));
                } catch (\Throwable $e) {
                }
            }
        }

        return $this->mesAgentsService->chefAgentIds($request);
    }
}
