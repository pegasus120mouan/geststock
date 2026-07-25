<?php

namespace App\Services;

use App\Models\ParticulierAgentPrix;
use App\Models\PrixAgent;
use App\Models\Ticket;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class TicketPrixService
{
    public function __construct(
        private MontantAgentFicheService $montantAgentFicheService
    ) {}

    public function typePrixPourMatricule(?string $matricule, ?int $vehiculeId = null): string
    {
        return $this->montantAgentFicheService->typePrixPourMatricule($matricule, $vehiculeId);
    }

    public function nomTypeTransporteurPourMatricule(?string $matricule): ?string
    {
        if ($matricule === null || $matricule === '') {
            return null;
        }

        return $this->typePrixPourMatricule($matricule) === 'pgf'
            ? 'Camion PGF'
            : 'Autre Camion';
    }

    /**
     * Prix unitaire agent pour un ticket.
     *
     * 1. Agent local (particulier sans lien API mes_agents)
     *    → particulier_agent_prix (/particuliers/prix-unitaire/{id})
     * 2. Agent API mes_agents (id_agent sur ticket, lien id_agent sur particulier, ou fiche de sortie)
     *    → prix_agents (/agents/{id_agent})
     */
    public function prixUnitairePourTicket(
        Ticket $ticket,
        ?int $produitId = null,
        ?string $dateReference = null,
        ?Collection $particulierPrixRecords = null,
        ?int $idAgentApiContext = null,
        ?string $nomUsine = null,
    ): ?float {
        $idUsine = (int) $ticket->id_usine;
        $nomUsine = $nomUsine !== null && trim($nomUsine) !== '' ? trim($nomUsine) : null;

        if ($idUsine <= 0 && $nomUsine === null) {
            return null;
        }

        $date = $dateReference ?? $ticket->date_ticket?->format('Y-m-d');
        $typeVehicule = $this->typePrixPourMatricule(
            $ticket->matricule_vehicule,
            (int) ($ticket->vehicule_id ?? 0) ?: null
        );
        $nomTypeTransporteur = $this->nomTypeTransporteurPourMatricule($ticket->matricule_vehicule);

        if ($ticket->particulier_agent_id) {
            $particulier = $ticket->relationLoaded('particulierAgent')
                ? $ticket->particulierAgent
                : $ticket->particulierAgent()->first();

            if ($particulier && $particulier->id_agent) {
                return $this->prixUnitairePrixAgent(
                    (int) $particulier->id_agent,
                    $idUsine > 0 ? $idUsine : null,
                    $produitId,
                    $date,
                    $typeVehicule,
                    $nomUsine
                );
            }

            $records = $particulierPrixRecords
                ?? ParticulierAgentPrix::where('particulier_agent_id', $ticket->particulier_agent_id)->get();

            return $this->prixUnitaireParticulierAgent(
                $records,
                (int) $ticket->particulier_agent_id,
                $idUsine,
                $date,
                $nomTypeTransporteur,
                $produitId
            );
        }

        $idAgentApi = (int) ($ticket->id_agent ?: $idAgentApiContext ?: 0);
        if ($idAgentApi > 0) {
            return $this->prixUnitairePrixAgent(
                $idAgentApi,
                $idUsine > 0 ? $idUsine : null,
                $produitId,
                $date,
                $typeVehicule,
                $nomUsine
            );
        }

        return null;
    }

    public function montantPourTicket(
        Ticket $ticket,
        ?int $produitId = null,
        ?string $dateReference = null,
        ?Collection $particulierPrixRecords = null,
        ?int $idAgentApiContext = null,
        ?string $nomUsine = null,
    ): ?float {
        $pu = $this->prixUnitairePourTicket(
            $ticket,
            $produitId,
            $dateReference,
            $particulierPrixRecords,
            $idAgentApiContext,
            $nomUsine
        );
        $poids = (float) ($ticket->poids ?? 0);

        if ($pu === null || $poids <= 0) {
            return null;
        }

        return $pu * $poids;
    }

    public function prixUnitaireParticulierAgent(
        Collection $prixRecords,
        int $particulierAgentId,
        int $idUsine,
        ?string $dateTicket,
        ?string $typeTransporteur = null,
        ?int $produitId = null
    ): ?float {
        if ($particulierAgentId <= 0 || $idUsine <= 0) {
            return null;
        }

        $date = $dateTicket
            ? Carbon::parse($dateTicket)->startOfDay()
            : now()->startOfDay();

        $candidats = $prixRecords
            ->where('particulier_agent_id', $particulierAgentId)
            ->where('id_usine', $idUsine)
            ->filter(function (ParticulierAgentPrix $prix) use ($date) {
                if ($prix->date_debut && $prix->date_debut->gt($date)) {
                    return false;
                }
                if ($prix->date_fin && $prix->date_fin->lt($date)) {
                    return false;
                }

                return true;
            });

        if ($typeTransporteur !== null && $typeTransporteur !== '') {
            $parType = $candidats->filter(
                fn (ParticulierAgentPrix $prix) => trim((string) ($prix->type_transporteur ?? '')) === $typeTransporteur
            );
            if ($parType->isNotEmpty()) {
                $candidats = $parType;
            }
        }

        if ($produitId) {
            $parProduit = $candidats->filter(fn (ParticulierAgentPrix $prix) => (int) $prix->produit_id === $produitId);
            if ($parProduit->isNotEmpty()) {
                $candidats = $parProduit;
            } else {
                $sansProduit = $candidats->filter(fn (ParticulierAgentPrix $prix) => empty($prix->produit_id));
                if ($sansProduit->isNotEmpty()) {
                    $candidats = $sansProduit;
                }
            }
        }

        $match = $candidats
            ->sortByDesc(fn (ParticulierAgentPrix $prix) => $prix->date_debut ?? $prix->created_at)
            ->first();

        return $match ? (float) $match->prix : null;
    }

    public function prixUnitairePrixAgent(
        int $idAgentApi,
        ?int $idUsine,
        ?int $produitId,
        ?string $dateTicket,
        string $type = 'autre_camion',
        ?string $nomUsine = null,
    ): ?float {
        if ($idAgentApi <= 0) {
            return null;
        }

        $nomUsine = $nomUsine !== null && trim($nomUsine) !== '' ? trim($nomUsine) : null;
        if (($idUsine === null || $idUsine <= 0) && $nomUsine === null) {
            return null;
        }

        $types = $type === 'autre_camion' ? ['autre_camion', 'transporteur'] : [$type];

        foreach ($types as $typeLookup) {
            $prix = $this->chercherPrixAgent(
                $idAgentApi,
                $idUsine,
                $nomUsine,
                $produitId,
                $dateTicket,
                $typeLookup
            );
            if ($prix !== null) {
                return $prix;
            }
        }

        return null;
    }

    private function chercherPrixAgent(
        int $idAgentApi,
        ?int $idUsine,
        ?string $nomUsine,
        ?int $produitId,
        ?string $dateTicket,
        string $type,
    ): ?float {
        $date = $dateTicket ? Carbon::parse($dateTicket)->startOfDay() : now()->startOfDay();

        $baseQuery = PrixAgent::query()
            ->where('id_agent', $idAgentApi)
            ->where('type', $type)
            ->where(fn ($q) => $q->whereNull('date_debut')->orWhereDate('date_debut', '<=', $date))
            ->where(fn ($q) => $q->whereNull('date_fin')->orWhereDate('date_fin', '>=', $date));

        if ($nomUsine !== null) {
            $baseQuery->where('nom_usine', $nomUsine);
        } elseif ($idUsine !== null && $idUsine > 0) {
            $baseQuery->where('id_usine', $idUsine);
        }

        if ($produitId) {
            $match = (clone $baseQuery)->where('produit_id', $produitId)->orderByDesc('date_debut')->first();
            if ($match) {
                return (float) $match->prix;
            }

            $match = (clone $baseQuery)->whereNull('produit_id')->orderByDesc('date_debut')->first();
            if ($match) {
                return (float) $match->prix;
            }
        }

        $match = $baseQuery->orderByDesc('date_debut')->first();

        return $match ? (float) $match->prix : null;
    }
}
