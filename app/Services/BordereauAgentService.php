<?php

namespace App\Services;

use App\Models\BordereauAgent;
use App\Models\FicheSortie;
use App\Models\Ticket;
use Illuminate\Support\Facades\DB;

class BordereauAgentService
{
    public function __construct(
        private MontantAgentReportingService $reporting,
        private MontantAgentFicheService $montantAgentFiche
    ) {}

    public function lettresAgent(?string $numeroAgent, ?string $nomAgent = null): string
    {
        if ($numeroAgent) {
            $segments = explode('-', strtoupper(trim($numeroAgent)));
            $suffixe = (string) end($segments);
            $letters = preg_replace('/[^A-Z]/u', '', $suffixe) ?? '';

            if (mb_strlen($letters) >= 2) {
                return mb_substr($letters, 0, 2);
            }

            if ($letters !== '') {
                return str_pad($letters, 2, 'X');
            }
        }

        $letters = preg_replace('/[^A-Z]/u', '', mb_strtoupper(trim((string) $nomAgent), 'UTF-8')) ?? '';

        if (mb_strlen($letters) >= 2) {
            return mb_substr($letters, 0, 2);
        }

        if ($letters !== '') {
            return str_pad($letters, 2, 'X');
        }

        return 'XX';
    }

    public function genererNumero(?string $numeroAgent = null, ?string $nomAgent = null): string
    {
        $prefix = 'BORD-' . $this->lettresAgent($numeroAgent, $nomAgent);

        return DB::transaction(function () use ($prefix) {
            $numeros = BordereauAgent::query()
                ->where('numero', 'like', $prefix . '%')
                ->lockForUpdate()
                ->pluck('numero');

            $max = 0;
            foreach ($numeros as $numero) {
                if (preg_match('/^' . preg_quote($prefix, '/') . '(\d+)$/', (string) $numero, $matches)) {
                    $max = max($max, (int) $matches[1]);
                }
            }

            return $prefix . ($max + 1);
        });
    }

    public function exempleNumero(?string $numeroAgent, ?string $nomAgent = null): string
    {
        return 'BORD-' . $this->lettresAgent($numeroAgent, $nomAgent) . '1';
    }

    /**
     * @return list<int>
     */
    public function ticketIdsDejaBorderees(int $idAgent): array
    {
        return Ticket::query()
            ->where('id_agent', $idAgent)
            ->whereNotNull('bordereau_agent_id')
            ->pluck('id_ticket')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return list<int>
     */
    public function ficheIdsDejaBorderees(int $idAgent): array
    {
        return FicheSortie::query()
            ->where('id_agent', $idAgent)
            ->whereNotNull('bordereau_agent_id')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  list<array<string, mixed>>  $lignesData
     */
    public function assignerLignesAuBordereau(BordereauAgent $bordereau, array $lignesData): void
    {
        $ticketIds = collect($lignesData)->pluck('ticket_id')->filter()->map(fn ($id) => (int) $id)->unique()->values()->all();
        $ficheIds = collect($lignesData)->pluck('fiche_id')->filter()->map(fn ($id) => (int) $id)->unique()->values()->all();

        if ($ticketIds !== []) {
            Ticket::query()
                ->where('id_agent', $bordereau->id_agent)
                ->whereIn('id_ticket', $ticketIds)
                ->whereNull('bordereau_agent_id')
                ->update(['bordereau_agent_id' => $bordereau->id]);
        }

        if ($ficheIds !== []) {
            FicheSortie::query()
                ->where('id_agent', $bordereau->id_agent)
                ->whereIn('id', $ficheIds)
                ->whereNull('bordereau_agent_id')
                ->update(['bordereau_agent_id' => $bordereau->id]);
        }
    }

    /** @deprecated Utiliser assignerLignesAuBordereau */
    public function assignerFichesAuBordereau(BordereauAgent $bordereau, array $ficheIds): void
    {
        $ficheIds = array_values(array_unique(array_map('intval', $ficheIds)));
        if ($ficheIds === []) {
            return;
        }

        FicheSortie::query()
            ->where('id_agent', $bordereau->id_agent)
            ->whereIn('id', $ficheIds)
            ->whereNull('bordereau_agent_id')
            ->update(['bordereau_agent_id' => $bordereau->id]);
    }

    public function libererFichesDuBordereau(BordereauAgent $bordereau): void
    {
        FicheSortie::query()
            ->where('bordereau_agent_id', $bordereau->id)
            ->update(['bordereau_agent_id' => null]);

        Ticket::query()
            ->where('bordereau_agent_id', $bordereau->id)
            ->update(['bordereau_agent_id' => null]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function lignesEligibles(int $idAgent, string $dateDebut, string $dateFin): array
    {
        $lignes = $this->reporting->lignesAvecMontant([
            'id_agent' => $idAgent,
            'date_debut' => $dateDebut,
            'date_fin' => $dateFin,
            'sans_bordereau' => true,
        ]);

        return array_map(fn (array $item) => $this->serialiserLigne($item), $lignes);
    }

    /** @deprecated Utiliser lignesEligibles */
    public function fichesEligibles(int $idAgent, string $dateDebut, string $dateFin): array
    {
        return $this->lignesEligibles($idAgent, $dateDebut, $dateFin);
    }

    /**
     * @param  list<int>  $ticketIds
     * @return list<array<string, mixed>>
     */
    public function construireLignesData(int $idAgent, array $ticketIds): array
    {
        $ticketIds = array_values(array_unique(array_map('intval', $ticketIds)));
        if ($ticketIds === []) {
            return [];
        }

        $lignes = $this->reporting->lignesAvecMontant([
            'id_agent' => $idAgent,
            'sans_bordereau' => true,
        ]);

        $lignesByTicket = collect($lignes)->keyBy(fn (array $item) => (int) $item['ticket']->id_ticket);
        $result = [];

        foreach ($ticketIds as $ticketId) {
            $item = $lignesByTicket->get($ticketId);
            if (! $item) {
                continue;
            }
            $result[] = $this->serialiserLigne($item);
        }

        return $result;
    }

    /** @deprecated Utiliser construireLignesData */
    public function construireFichesData(int $idAgent, array $ficheIds): array
    {
        return $this->construireLignesData($idAgent, $ficheIds);
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function serialiserLigne(array $item): array
    {
        /** @var Ticket $ticket */
        $ticket = $item['ticket'];
        /** @var FicheSortie $fiche */
        $fiche = $item['fiche'];
        $poids = (float) ($item['poids_effectif'] ?? 0);
        $aFiche = (bool) ($item['a_fiche'] ?? false);

        return [
            'ticket_id' => (int) $ticket->id_ticket,
            'fiche_id' => $aFiche && (int) $fiche->id > 0 ? (int) $fiche->id : null,
            'a_fiche' => $aFiche,
            'numero_fiche' => $aFiche ? $fiche->numero_fiche : '—',
            'date_chargement' => ($fiche->date_chargement ?? $ticket->date_ticket)?->format('Y-m-d'),
            'date_dechargement' => ($fiche->date_dechargement ?? $ticket->date_ticket)?->format('Y-m-d'),
            'matricule_vehicule' => $fiche->matricule_vehicule ?: $ticket->matricule_vehicule,
            'nom_produit' => $fiche->nom_produit,
            'usine' => $fiche->usine,
            'numero_ticket' => $ticket->numero_ticket ?: $fiche->numero_ticket,
            'poids' => $poids,
            'prix_unitaire' => $item['prix_unitaire'] ?? null,
            'montant' => (int) ($item['montant'] ?? 0),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $fichesData
     * @return list<array{usine: string, lignes: list<array<string, mixed>>, montant_total: int, poids_total: float}>
     */
    public function grouperParUsine(array $fichesData): array
    {
        $parUsine = collect($fichesData)->groupBy(fn ($l) => $l['usine'] ?: 'Sans usine');
        $groupes = [];

        foreach ($parUsine as $usine => $lignes) {
            $lignesArr = $lignes->values()->all();
            $groupes[] = [
                'usine' => $usine,
                'lignes' => $lignesArr,
                'montant_total' => (int) collect($lignesArr)->sum('montant'),
                'poids_total' => (float) collect($lignesArr)->sum('poids'),
            ];
        }

        usort($groupes, fn ($a, $b) => strcasecmp($a['usine'], $b['usine']));

        return $groupes;
    }
}
