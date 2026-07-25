<?php

namespace App\Services;

use App\Models\ParticulierAgent;
use App\Models\ParticulierAgentPrix;
use App\Models\Produit;
use App\Models\Ticket;
use App\Models\Usine;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

class MontantParticulierReportingService
{
    public function __construct(
        private TicketPrixService $ticketPrixService
    ) {}

    /**
     * @return array{
     *   produit_id: int|null,
     *   usine: string|null,
     *   date_debut: string|null,
     *   date_fin: string|null,
     *   particulier_agent_id: int|null
     * }
     */
    public function filtresDepuisRequest(Request $request): array
    {
        $produitId = $request->filled('produit_id') ? (int) $request->input('produit_id') : null;

        return [
            'produit_id' => $produitId > 0 ? $produitId : null,
            'usine' => $request->filled('usine') ? trim((string) $request->input('usine')) : null,
            'date_debut' => $request->filled('date_debut') ? (string) $request->input('date_debut') : null,
            'date_fin' => $request->filled('date_fin') ? (string) $request->input('date_fin') : null,
            'particulier_agent_id' => $request->filled('particulier_agent_id')
                ? (int) $request->input('particulier_agent_id')
                : null,
        ];
    }

    /**
     * Agents locaux uniquement (sans lien API mes_agents).
     */
    public function queryAgentsLocaux(): Builder
    {
        return ParticulierAgent::query()
            ->whereNull('id_agent')
            ->with('groupe')
            ->orderBy('nom')
            ->orderBy('prenoms');
    }

    /**
     * @param  array<string, mixed>  $filtres
     */
    public function queryTickets(array $filtres = []): Builder
    {
        $query = Ticket::query()
            ->whereNotNull('particulier_agent_id')
            ->whereHas('particulierAgent', fn ($q) => $q->whereNull('id_agent'))
            ->with(['particulierAgent.groupe', 'ficheSortie']);

        if (!empty($filtres['particulier_agent_id'])) {
            $query->where('particulier_agent_id', (int) $filtres['particulier_agent_id']);
        }

        if (!empty($filtres['date_debut'])) {
            $query->whereDate('date_ticket', '>=', $filtres['date_debut']);
        }

        if (!empty($filtres['date_fin'])) {
            $query->whereDate('date_ticket', '<=', $filtres['date_fin']);
        }

        if (!empty($filtres['usine'])) {
            $ids = $this->idsUsinePourNom((string) $filtres['usine']);
            if ($ids !== []) {
                $query->whereIn('id_usine', $ids);
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        if (!empty($filtres['produit_id'])) {
            $query->whereHas('ficheSortie', fn ($q) => $q->where('produit_id', (int) $filtres['produit_id']));
        }

        return $query;
    }

    public function montantLigneTicket(Ticket $ticket, ?Collection $prixParticuliers = null): int
    {
        $produitId = $ticket->ficheSortie?->produit_id ? (int) $ticket->ficheSortie->produit_id : null;
        $pu = $this->ticketPrixService->prixUnitairePourTicket(
            $ticket,
            $produitId,
            $ticket->date_ticket?->format('Y-m-d'),
            $prixParticuliers
        );
        $poids = (float) ($ticket->poids ?? 0);

        return $pu !== null && $poids > 0 ? (int) round($pu * $poids) : 0;
    }

    /**
     * @param  array<string, mixed>  $filtres
     */
    public function calculerMontantDuAgent(int $particulierAgentId, array $filtres = [], ?Collection $prixParticuliers = null): float
    {
        $filtres['particulier_agent_id'] = $particulierAgentId;
        $tickets = $this->queryTickets($filtres)->get();
        $prixRecords = $prixParticuliers ?? $this->chargerPrixParticuliers($tickets->pluck('particulier_agent_id')->unique());

        $total = 0.0;
        foreach ($tickets as $ticket) {
            $total += $this->montantLigneTicket($ticket, $prixRecords);
        }

        return $total;
    }

    /**
     * @param  array<string, mixed>  $filtres
     * @return list<array{ticket: Ticket, montant: int, prix_unitaire: float|null, nom_usine: string, nom_produit: string}>
     */
    public function ticketsAvecMontant(array $filtres = []): array
    {
        $tickets = $this->queryTickets($filtres)->orderByDesc('date_ticket')->get();
        $prixRecords = $this->chargerPrixParticuliers($tickets->pluck('particulier_agent_id')->unique());
        $usinesById = $this->buildUsinesById();

        $result = [];
        foreach ($tickets as $ticket) {
            $produitId = $ticket->ficheSortie?->produit_id ? (int) $ticket->ficheSortie->produit_id : null;
            $pu = $this->ticketPrixService->prixUnitairePourTicket(
                $ticket,
                $produitId,
                $ticket->date_ticket?->format('Y-m-d'),
                $prixRecords
            );

            $result[] = [
                'ticket' => $ticket,
                'montant' => $this->montantLigneTicket($ticket, $prixRecords),
                'prix_unitaire' => $pu,
                'nom_usine' => $usinesById[(int) $ticket->id_usine] ?? ('Usine #' . $ticket->id_usine),
                'nom_produit' => $ticket->ficheSortie?->nom_produit ?: 'Sans produit',
            ];
        }

        return $result;
    }

    /**
     * @param  list<array{ticket: Ticket, montant: int, prix_unitaire: float|null, nom_usine: string, nom_produit: string}>  $ticketsAvecMontant
     */
    public function grouperParProduitEtUsine(array $ticketsAvecMontant): array
    {
        $parProduit = collect($ticketsAvecMontant)->groupBy(fn ($item) => $item['nom_produit'] ?: 'Sans produit');

        $groupes = [];
        foreach ($parProduit as $nomProduit => $itemsProduit) {
            $parUsine = $itemsProduit->groupBy(fn ($item) => $item['nom_usine'] ?: 'Sans usine');
            $usines = [];

            foreach ($parUsine as $nomUsine => $lignes) {
                $lignesArr = $lignes->values()->all();
                $usines[] = [
                    'usine' => $nomUsine,
                    'montant_total' => (int) $lignes->sum('montant'),
                    'poids_total' => (float) $lignes->sum(fn ($i) => (float) ($i['ticket']->poids ?? 0)),
                    'nb_tickets' => count($lignesArr),
                    'lignes' => $lignesArr,
                ];
            }

            usort($usines, fn ($a, $b) => strcasecmp($a['usine'], $b['usine']));

            $groupes[] = [
                'produit' => $nomProduit,
                'montant_total' => (int) $itemsProduit->sum('montant'),
                'poids_total' => (float) $itemsProduit->sum(fn ($i) => (float) ($i['ticket']->poids ?? 0)),
                'nb_tickets' => $itemsProduit->count(),
                'usines' => $usines,
            ];
        }

        usort($groupes, fn ($a, $b) => strcasecmp($a['produit'], $b['produit']));

        return $groupes;
    }

    /**
     * @return array{produits: \Illuminate\Database\Eloquent\Collection, usines: list<string>}
     */
    public function optionsFiltres(): array
    {
        $produits = Produit::orderBy('nom')->get();
        $usinesById = $this->buildUsinesById();
        $idsTickets = Ticket::query()
            ->whereNotNull('particulier_agent_id')
            ->whereHas('particulierAgent', fn ($q) => $q->whereNull('id_agent'))
            ->distinct()
            ->pluck('id_usine');

        $usines = [];
        foreach ($idsTickets as $id) {
            $nom = $usinesById[(int) $id] ?? null;
            if ($nom) {
                $usines[] = $nom;
            }
        }
        sort($usines);

        return [
            'produits' => $produits,
            'usines' => array_values(array_unique($usines)),
        ];
    }

    /**
     * @param  array<string, mixed>  $filtres
     */
    public function filtresPourUrl(array $filtres): array
    {
        return array_filter([
            'produit_id' => $filtres['produit_id'] ?? null,
            'usine' => $filtres['usine'] ?? null,
            'date_debut' => $filtres['date_debut'] ?? null,
            'date_fin' => $filtres['date_fin'] ?? null,
        ], fn ($v) => $v !== null && $v !== '');
    }

    public function filtresActifs(array $filtres): bool
    {
        return ($filtres['produit_id'] ?? null)
            || ($filtres['usine'] ?? null)
            || ($filtres['date_debut'] ?? null)
            || ($filtres['date_fin'] ?? null);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, int|string|null>  $agentIds
     */
    public function chargerPrixParticuliers(Collection $agentIds): Collection
    {
        $ids = $agentIds->filter()->unique()->values();

        return $ids->isEmpty()
            ? collect()
            : ParticulierAgentPrix::whereIn('particulier_agent_id', $ids)->get();
    }

    /**
     * @return array<int, string>
     */
    private function buildUsinesById(): array
    {
        $index = [];
        foreach (Usine::all() as $ul) {
            $index[(int) $ul->id_usine] = $ul->nom_usine;
        }

        try {
            $url = (string) config('services.external_auth.mes_usines_url');
            $timeout = (int) config('services.external_auth.timeout', 10);
            $resp = Http::acceptJson()->withoutVerifying()->timeout($timeout)->get($url);
            if ($resp->successful()) {
                foreach ($resp->json('usines') ?? [] as $u) {
                    $id = (int) ($u['id_usine'] ?? 0);
                    if ($id > 0 && !isset($index[$id])) {
                        $index[$id] = $u['nom_usine'] ?? '';
                    }
                }
            }
        } catch (\Throwable $e) {
        }

        return $index;
    }

    /**
     * @return list<int>
     */
    private function idsUsinePourNom(string $nomUsine): array
    {
        $nom = trim($nomUsine);
        if ($nom === '') {
            return [];
        }

        $ids = [];
        foreach ($this->buildUsinesById() as $id => $nom) {
            if (strcasecmp($nom, $nomUsine) === 0) {
                $ids[] = (int) $id;
            }
        }

        return $ids;
    }
}
