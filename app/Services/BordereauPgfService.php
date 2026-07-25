<?php

namespace App\Services;

use App\Models\BordereauPgf;
use App\Models\Ticket;
use Illuminate\Support\Facades\DB;

class BordereauPgfService
{
    public function genererNumero(): string
    {
        $prefix = 'BORD-PG';

        return DB::transaction(function () use ($prefix) {
            $numeros = BordereauPgf::query()
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

    public function exempleNumero(): string
    {
        return 'BORD-PG1';
    }

    /**
     * @return list<int>
     */
    public function ticketIdsDejaBorderees(): array
    {
        return Ticket::query()
            ->whereNotNull('bordereau_pgf_id')
            ->pluck('id_ticket')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  list<array<string, mixed>>  $tickets
     * @return list<array<string, mixed>>
     */
    public function lignesEligiblesDepuisTickets(array $tickets): array
    {
        $deja = array_flip($this->ticketIdsDejaBorderees());
        $lignes = [];

        foreach ($tickets as $ticket) {
            $ticketId = (int) ($ticket['id_ticket'] ?? 0);
            $montant = $ticket['montant'] ?? null;
            if ($ticketId <= 0 || $montant === null || (float) $montant <= 0) {
                continue;
            }
            if (isset($deja[$ticketId])) {
                continue;
            }
            if (! empty($ticket['bordereau_pgf_id'])) {
                continue;
            }

            $lignes[] = $this->serialiserLigne($ticket);
        }

        return $lignes;
    }

    /**
     * @param  list<int>  $ticketIds
     * @param  list<array<string, mixed>>  $tickets
     * @return list<array<string, mixed>>
     */
    public function construireLignesData(array $ticketIds, array $tickets): array
    {
        $ticketIds = array_values(array_unique(array_map('intval', $ticketIds)));
        if ($ticketIds === []) {
            return [];
        }

        $eligibles = collect($this->lignesEligiblesDepuisTickets($tickets))
            ->keyBy(fn (array $item) => (int) $item['ticket_id']);

        $result = [];
        foreach ($ticketIds as $ticketId) {
            $item = $eligibles->get($ticketId);
            if ($item) {
                $result[] = $item;
            }
        }

        return $result;
    }

    /**
     * @param  list<array<string, mixed>>  $lignesData
     */
    public function assignerLignesAuBordereau(BordereauPgf $bordereau, array $lignesData): void
    {
        $ticketIds = collect($lignesData)
            ->pluck('ticket_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if ($ticketIds === []) {
            return;
        }

        Ticket::query()
            ->whereIn('id_ticket', $ticketIds)
            ->whereNull('bordereau_pgf_id')
            ->update(['bordereau_pgf_id' => $bordereau->id]);
    }

    public function libererTicketsDuBordereau(BordereauPgf $bordereau): void
    {
        Ticket::query()
            ->where('bordereau_pgf_id', $bordereau->id)
            ->update(['bordereau_pgf_id' => null]);
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

    /**
     * @param  array<string, mixed>  $ticket
     * @return array<string, mixed>
     */
    private function serialiserLigne(array $ticket): array
    {
        $date = $ticket['date_ticket'] ?? null;
        $dateStr = null;
        if ($date instanceof \DateTimeInterface) {
            $dateStr = $date->format('Y-m-d');
        } elseif (is_string($date) && $date !== '') {
            $dateStr = $date;
        }

        return [
            'ticket_id' => (int) ($ticket['id_ticket'] ?? 0),
            'fiche_id' => null,
            'a_fiche' => false,
            'numero_fiche' => '—',
            'date_chargement' => $dateStr,
            'date_dechargement' => $dateStr,
            'matricule_vehicule' => $ticket['matricule_vehicule'] ?? '—',
            'nom_produit' => '—',
            'usine' => $ticket['nom_usine'] ?? '—',
            'numero_ticket' => $ticket['numero_ticket'] ?? '—',
            'poids' => (float) ($ticket['poids'] ?? 0),
            'prix_unitaire' => $ticket['prix_unitaire'] ?? null,
            'montant' => (int) round((float) ($ticket['montant'] ?? 0)),
        ];
    }
}
