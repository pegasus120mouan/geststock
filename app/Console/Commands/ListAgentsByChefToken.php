<?php

namespace App\Console\Commands;

use App\Services\MesAgentsService;
use Illuminate\Console\Command;

class ListAgentsByChefToken extends Command
{
    protected $signature = 'agents:by-chef-token
                            {token : Token du chef d\'équipe (ex. C77C4305)}
                            {--id-chef= : Filtrer par id_chef si le token n\'est pas reconnu par l\'API}
                            {--search= : Recherche nom / numéro}
                            {--json : Sortie JSON brute}';

    protected $description = 'Récupère tous les agents d\'un chef d\'équipe via l\'API (toutes les pages)';

    public function handle(MesAgentsService $service): int
    {
        $token = trim((string) $this->argument('token'));
        $idChef = (int) $this->option('id-chef');
        $search = trim((string) $this->option('search'));

        $params = array_filter([
            'id_chef' => $idChef > 0 ? $idChef : null,
            'token' => $idChef > 0 ? null : $token,
            'search' => $search !== '' ? $search : null,
        ]);

        $agents = $service->fetchAllAgents($params);

        if ($agents === []) {
            $probe = $service->listAgents(array_merge($params, ['page' => 1]));
            if ($probe['error']) {
                $this->error($probe['error']);
            } else {
                $this->warn('Aucun agent trouvé pour ce token / id_chef.');
                $this->line('Vérifiez EXTERNAL_AUTH_MES_AGENTS_URL et que mes_agents.php accepte ?token=');
            }

            return self::FAILURE;
        }

        if ($this->option('json')) {
            $this->line(json_encode($agents, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        $chef = $agents[0]['chef_equipe']['nom_complet'] ?? '—';
        $this->info("Token : {$token} | Chef : {$chef} | Agents : " . count($agents));
        $this->newLine();

        $rows = array_map(fn (array $a) => [
            $a['numero_agent'] ?? '',
            $a['nom_complet'] ?? '',
            $a['contact'] ?? '',
            $a['chef_equipe']['nom_complet'] ?? '',
            $a['date_ajout'] ?? '',
        ], $agents);

        $this->table(['Numéro', 'Nom', 'Contact', 'Chef', 'Date ajout'], $rows);

        return self::SUCCESS;
    }
}
