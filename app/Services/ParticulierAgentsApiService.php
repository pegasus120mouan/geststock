<?php

namespace App\Services;

use App\Models\ParticulierAgent;
use App\Models\ParticulierGroupe;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class ParticulierAgentsApiService
{
    public function __construct(
        private MesAgentsService $mesAgentsService,
    ) {}

    public function fetchAll(Request $request): array
    {
        return $this->mesAgentsService->fetchAllAgents([], $request);
    }

    public function extraireMotCleGroupe(string $nomGroupe): string
    {
        $clean = preg_replace('/\b(agents?|particulier|groupes?)\b/ui', ' ', $nomGroupe);

        return strtoupper(trim(preg_replace('/\s+/', ' ', (string) $clean)));
    }

    public function groupeUtiliseAgentsApi(string $nomGroupe): bool
    {
        if (preg_match('/particulier/ui', $nomGroupe)) {
            return false;
        }

        if (preg_match('/agents/ui', $nomGroupe)) {
            return true;
        }

        return true;
    }

    public function filtrerPourGroupe(string $nomGroupe, array $agentsApi): array
    {
        $motCle = $this->extraireMotCleGroupe($nomGroupe);
        if ($motCle === '') {
            return $agentsApi;
        }

        $filtres = array_values(array_filter($agentsApi, function (array $a) use ($motCle) {
            $chef = strtoupper($a['chef_equipe']['nom'] ?? '');
            $numero = strtoupper($a['numero_agent'] ?? '');

            return $chef === $motCle || str_contains($numero, $motCle);
        }));

        usort($filtres, fn (array $a, array $b) => strcasecmp(
            $a['numero_agent'] ?? '',
            $b['numero_agent'] ?? ''
        ));

        return $filtres;
    }

    public function agentsParGroupePourSelect(Collection $groupes, array $agentsApi): array
    {
        $result = [];

        foreach ($groupes as $groupe) {
            if ($this->groupeUtiliseAgentsApi($groupe->nom_groupe)) {
                $agents = $this->filtrerPourGroupe($groupe->nom_groupe, $agentsApi);
                $result[$groupe->id] = [
                    'source' => 'api',
                    'agents' => array_map(function (array $a) {
                        $numero = trim((string) ($a['numero_agent'] ?? ''));
                        $nom = trim((string) ($a['nom_complet'] ?? ''));
                        $label = $numero !== '' && $nom !== ''
                            ? $numero . ' — ' . $nom
                            : ($nom !== '' ? $nom : $numero);

                        return [
                            'id' => 'api:' . (int) ($a['id_agent'] ?? 0),
                            'label' => $label,
                        ];
                    }, $agents),
                ];
            } else {
                $result[$groupe->id] = [
                    'source' => 'local',
                    'agents' => $groupe->agents
                        ->sortBy('numero_agent')
                        ->values()
                        ->map(function (ParticulierAgent $agent) {
                            $numero = trim((string) ($agent->numero_agent ?? ''));
                            $nom = trim($agent->nom . ' ' . $agent->prenoms);
                            $label = $numero !== '' && $nom !== ''
                                ? $numero . ' — ' . $nom
                                : ($nom !== '' ? $nom : $numero);

                            return [
                                'id' => 'local:' . $agent->id,
                                'label' => $label,
                            ];
                        })
                        ->all(),
                ];
            }
        }

        return $result;
    }

    public function resolveAgentForTicket(int $groupeId, string $agentRef, array $agentsApi): ParticulierAgent
    {
        if (!preg_match('/^(api|local):(\d+)$/', $agentRef, $matches)) {
            throw ValidationException::withMessages([
                'agent_ref' => 'Sélection d’agent invalide.',
            ]);
        }

        $type = $matches[1];
        $id = (int) $matches[2];
        $groupe = ParticulierGroupe::findOrFail($groupeId);

        if ($this->groupeUtiliseAgentsApi($groupe->nom_groupe)) {
            if ($type !== 'api') {
                throw ValidationException::withMessages([
                    'agent_ref' => 'Ce groupe utilise les agents de l’API.',
                ]);
            }

            return $this->resolveAgentForGroupe($groupeId, $id, $agentsApi);
        }

        if ($type !== 'local') {
            throw ValidationException::withMessages([
                'agent_ref' => 'Ce groupe utilise les agents enregistrés localement.',
            ]);
        }

        $agent = ParticulierAgent::query()
            ->where('id', $id)
            ->where('particulier_groupe_id', $groupeId)
            ->first();

        if (!$agent) {
            throw ValidationException::withMessages([
                'agent_ref' => 'Cet agent n’appartient pas au groupe sélectionné.',
            ]);
        }

        return $agent;
    }

    public function resolveAgentForGroupe(int $groupeId, int $idAgentApi, array $agentsApi): ParticulierAgent
    {
        $existing = ParticulierAgent::query()
            ->where('id_agent', $idAgentApi)
            ->with('groupe')
            ->first();

        if ($existing) {
            $existingGroupeId = (int) ($existing->particulier_groupe_id ?? 0);

            if ($existingGroupeId === $groupeId) {
                return $existing;
            }

            // Agent créé sans groupe (ex. sync prix unitaires) → rattacher au groupe demandé
            if ($existingGroupeId === 0) {
                $existing->update(['particulier_groupe_id' => $groupeId]);

                return $existing->fresh(['groupe']);
            }

            $groupeNom = $existing->groupe?->nom_groupe
                ?? ParticulierGroupe::find($existingGroupeId)?->nom_groupe
                ?? 'un autre groupe';

            throw ValidationException::withMessages([
                'agent_ref' => 'Cet agent est déjà affecté au groupe « ' . $groupeNom . ' ».',
            ]);
        }

        $groupe = ParticulierGroupe::findOrFail($groupeId);
        $apiAgent = collect($agentsApi)->first(fn (array $a) => (int) ($a['id_agent'] ?? 0) === $idAgentApi);

        if (!$apiAgent) {
            throw ValidationException::withMessages([
                'agent_ref' => 'Agent introuvable dans l’API.',
            ]);
        }

        if (empty($this->filtrerPourGroupe($groupe->nom_groupe, [$apiAgent]))) {
            throw ValidationException::withMessages([
                'agent_ref' => 'Cet agent n’appartient pas au groupe sélectionné.',
            ]);
        }

        $nomComplet = trim((string) ($apiAgent['nom_complet'] ?? ''));
        $nom = trim((string) ($apiAgent['nom'] ?? $nomComplet));
        $prenoms = trim((string) ($apiAgent['prenom'] ?? ''));

        if ($prenoms === '' && $nomComplet !== '' && $nom !== '' && str_starts_with($nomComplet, $nom)) {
            $prenoms = trim(substr($nomComplet, strlen($nom)));
        }

        try {
            return ParticulierAgent::create([
                'particulier_groupe_id' => $groupeId,
                'id_agent' => $idAgentApi,
                'numero_agent' => (string) ($apiAgent['numero_agent'] ?? 'AGT-' . $idAgentApi),
                'nom' => $nom !== '' ? $nom : $nomComplet,
                'prenoms' => $prenoms,
                'contact' => (string) ($apiAgent['contact'] ?? ''),
            ]);
        } catch (UniqueConstraintViolationException) {
            $existing = ParticulierAgent::query()->where('id_agent', $idAgentApi)->first();
            if (!$existing) {
                throw ValidationException::withMessages([
                    'agent_ref' => 'Cet agent est déjà enregistré dans un groupe particulier.',
                ]);
            }

            $existingGroupeId = (int) ($existing->particulier_groupe_id ?? 0);
            if ($existingGroupeId === $groupeId || $existingGroupeId === 0) {
                if ($existingGroupeId === 0) {
                    $existing->update(['particulier_groupe_id' => $groupeId]);
                }

                return $existing->fresh(['groupe']);
            }

            $groupeNom = $existing->groupe?->nom_groupe
                ?? ParticulierGroupe::find($existingGroupeId)?->nom_groupe
                ?? 'un autre groupe';

            throw ValidationException::withMessages([
                'agent_ref' => 'Cet agent est déjà affecté au groupe « ' . $groupeNom . ' ».',
            ]);
        }
    }
}
