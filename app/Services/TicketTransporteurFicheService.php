<?php

namespace App\Services;

use App\Models\FicheSortie;
use App\Models\Ticket;
use App\Models\Transporteur;
use App\Models\TransporteurVehicule;
use App\Models\Usine;

class TicketTransporteurFicheService
{
    public function poidsEffectif(FicheSortie $fiche): float
    {
        $poids = (float) ($fiche->poids_pont ?? 0);
        if ($poids > 0) {
            return $poids;
        }

        $ticket = $this->ticketPourFiche($fiche);
        if ($ticket) {
            return (float) ($ticket->poids ?? 0);
        }

        return 0.0;
    }

    public function numeroTicketEffectif(FicheSortie $fiche): ?string
    {
        $numero = trim((string) ($fiche->numero_ticket ?? ''));
        if ($numero !== '') {
            return $numero;
        }

        $ticket = $this->ticketPourFiche($fiche);

        return $ticket && trim((string) $ticket->numero_ticket) !== ''
            ? trim((string) $ticket->numero_ticket)
            : null;
    }

    public function agentNomEffectif(FicheSortie $fiche): ?string
    {
        $nomFiche = trim((string) ($fiche->nom_agent ?? ''));
        if ($nomFiche !== '') {
            return $nomFiche;
        }

        $ticket = $this->ticketPourFiche($fiche);
        if (!$ticket || !(int) ($ticket->id_agent ?? 0)) {
            return null;
        }

        try {
            $agent = app(MesAgentsService::class)->findAgentById((int) $ticket->id_agent);
        } catch (\Throwable $e) {
            $agent = null;
        }

        return $agent ? trim((string) ($agent['nom_complet'] ?? '')) : null;
    }

    public function usineNomEffectif(FicheSortie $fiche): ?string
    {
        $usineFiche = trim((string) ($fiche->usine ?? ''));
        if ($usineFiche !== '') {
            return $usineFiche;
        }

        $ticket = $this->ticketPourFiche($fiche);
        if (!$ticket || !(int) ($ticket->id_usine ?? 0)) {
            return null;
        }

        $nomUsine = Usine::query()
            ->where('id_usine', (int) $ticket->id_usine)
            ->value('nom_usine');

        return $nomUsine ? trim((string) $nomUsine) : null;
    }

    /**
     * Complète la fiche avec le numéro et le poids du ticket lié si manquants.
     */
    public function synchroniserDonneesTicketSurFiche(FicheSortie $fiche): FicheSortie
    {
        $ticket = $this->ticketPourFiche($fiche);
        if (!$ticket) {
            return $fiche;
        }

        $updates = [];

        if (trim((string) ($fiche->numero_ticket ?? '')) === '' && trim((string) $ticket->numero_ticket) !== '') {
            $updates['numero_ticket'] = trim((string) $ticket->numero_ticket);
        }

        if (!(int) ($fiche->id_ticket ?? 0) && (int) ($ticket->id_ticket ?? 0)) {
            $updates['id_ticket'] = $ticket->id_ticket;
        }

        if ((float) ($fiche->poids_pont ?? 0) <= 0 && (float) ($ticket->poids ?? 0) > 0) {
            $updates['poids_pont'] = $ticket->poids;
        }

        $matriculeTicket = trim((string) ($ticket->matricule_vehicule ?? ''));
        if ($matriculeTicket !== '' && strcasecmp(trim((string) $fiche->matricule_vehicule), $matriculeTicket) !== 0) {
            $updates['matricule_vehicule'] = $matriculeTicket;
        }

        if ((int) ($ticket->vehicule_id ?? 0) > 0 && (int) $fiche->vehicule_id !== (int) $ticket->vehicule_id) {
            $updates['vehicule_id'] = (int) $ticket->vehicule_id;
        }

        if ($ticket->date_ticket) {
            $dateTicket = $ticket->date_ticket->format('Y-m-d');
            $dateChargement = $fiche->date_chargement?->format('Y-m-d');
            if ($dateChargement !== $dateTicket) {
                $updates['date_chargement'] = $dateTicket;
            }
            $dateDechargement = $fiche->date_dechargement?->format('Y-m-d');
            if ($ticket->estValide() && $dateDechargement !== $dateTicket) {
                $updates['date_dechargement'] = $dateTicket;
            }
        }

        if ((int) ($ticket->id_agent ?? 0) > 0) {
            $updates['id_agent'] = (int) $ticket->id_agent;
            $nomAgentFiche = trim((string) ($fiche->nom_agent ?? ''));
            $numeroAgentFiche = trim((string) ($fiche->numero_agent ?? ''));
            if ($nomAgentFiche === '' || $numeroAgentFiche === '') {
                try {
                    $agent = app(MesAgentsService::class)->findAgentById((int) $ticket->id_agent);
                } catch (\Throwable $e) {
                    $agent = null;
                }
                if ($agent) {
                    $nomAgent = trim((string) ($agent['nom_complet'] ?? ''));
                    if ($nomAgent !== '' && $nomAgentFiche !== $nomAgent) {
                        $updates['nom_agent'] = $nomAgent;
                    }
                    $numeroAgent = trim((string) ($agent['numero_agent'] ?? ''));
                    if ($numeroAgent !== '' && $numeroAgentFiche !== $numeroAgent) {
                        $updates['numero_agent'] = $numeroAgent;
                    }
                }
            }
        }

        if ((int) ($ticket->id_usine ?? 0) > 0) {
            $nomUsine = Usine::query()
                ->where('id_usine', (int) $ticket->id_usine)
                ->value('nom_usine');
            if ($nomUsine && trim((string) ($fiche->usine ?? '')) !== trim((string) $nomUsine)) {
                $updates['usine'] = trim((string) $nomUsine);
            }
        }

        if (!empty($updates)) {
            $fiche->update($updates);
            $fiche->refresh();
        }

        return $this->assurerTransporteurSurFiche($fiche);
    }

    /**
     * Aligne transporteur_id sur le compte lié au véhicule (une fiche = un transporteur).
     */
    public function assurerTransporteurSurFiche(FicheSortie $fiche): FicheSortie
    {
        $transporteur = app(TransporteurVehiculeService::class)->transporteurPourVehicule(
            $fiche->vehicule_id ? (int) $fiche->vehicule_id : null,
            $fiche->matricule_vehicule
        );

        if (!$transporteur) {
            return $fiche;
        }

        if ((int) $fiche->transporteur_id !== (int) $transporteur->id) {
            $fiche->update(['transporteur_id' => $transporteur->id]);
            $fiche->refresh();
        }

        return $fiche;
    }

    public function lierFicheAuTransporteur(FicheSortie $fiche, Transporteur $transporteur, ?Ticket $ticket = null): void
    {
        $data = [
            'transporteur_id' => $transporteur->id,
        ];

        if ($ticket) {
            $data = array_merge($data, $this->donneesTicketPourFiche($ticket));
        }

        $fiche->update($data);
    }

    /**
     * @param  array{nom_usine?: string, produit_id?: int|null, nom_produit?: string, nom_agent?: string, numero_agent?: string, id_agent?: int|null}  $context
     */
    public function creerFicheDepuisTicket(Ticket $ticket, Transporteur $transporteur, array $context): FicheSortie
    {
        $poids = (float) ($ticket->poids ?? 0);
        $date = $ticket->date_ticket?->format('Y-m-d') ?? now()->format('Y-m-d');
        $numeroFiche = $this->numeroFicheDepuisTicket($ticket);

        $donnees = array_merge([
            'numero_fiche' => $numeroFiche,
            'vehicule_id' => (int) ($ticket->vehicule_id ?? 0),
            'matricule_vehicule' => (string) $ticket->matricule_vehicule,
            'transporteur_id' => $transporteur->id,
            'id_pont' => 0,
            'nom_pont' => 'Usine',
            'code_pont' => '',
            'usine' => $context['nom_usine'] ?? null,
            'produit_id' => $context['produit_id'] ?? null,
            'nom_produit' => $context['nom_produit'] ?? '',
            'id_agent' => (int) ($context['id_agent'] ?? 0),
            'nom_agent' => $context['nom_agent'] ?? '',
            'numero_agent' => $context['numero_agent'] ?? '',
            'date_chargement' => $date,
            'poids_pont' => $poids > 0 ? $poids : null,
            'prix_unitaire_transport' => null,
        ], $this->donneesTicketPourFiche($ticket));

        $existante = FicheSortie::query()
            ->where('id_ticket', $ticket->id_ticket)
            ->orderByDesc('id')
            ->first();

        if (! $existante && $numeroFiche !== '') {
            $existante = FicheSortie::query()
                ->where('numero_fiche', $numeroFiche)
                ->first();
        }

        if ($existante) {
            $existante->update($donnees);

            return $existante->fresh();
        }

        return FicheSortie::create($donnees);
    }

    /**
     * Lie le ticket à un transporteur sans renseigner le prix unitaire (saisie manuelle).
     *
     * @param  array{nom_usine?: string, produit_id?: int|null, nom_produit?: string, nom_agent?: string, numero_agent?: string, id_agent?: int|null}  $context
     */
    public function synchroniserTicketTransporteur(Ticket $ticket, ?FicheSortie $fiche, array $context): ?Transporteur
    {
        $transporteur = app(TransporteurVehiculeService::class)->transporteurPourVehicule(
            $ticket->vehicule_id ? (int) $ticket->vehicule_id : null,
            $ticket->matricule_vehicule
        );

        if (!$transporteur) {
            return null;
        }

        if (! $fiche) {
            $numeroFiche = $this->numeroFicheDepuisTicket($ticket);
            $fiche = FicheSortie::query()
                ->where('id_ticket', $ticket->id_ticket)
                ->orderByDesc('id')
                ->first();

            if (! $fiche && $numeroFiche !== '') {
                $fiche = FicheSortie::query()
                    ->where('numero_fiche', $numeroFiche)
                    ->first();
            }
        }

        if ($fiche) {
            $this->lierFicheAuTransporteur($fiche, $transporteur, $ticket);

            return $transporteur;
        }

        if ((float) ($ticket->poids ?? 0) > 0 || trim((string) $ticket->numero_ticket) !== '') {
            $this->creerFicheDepuisTicket($ticket, $transporteur, $context);
        }

        return $transporteur;
    }

    private function numeroFicheDepuisTicket(Ticket $ticket): string
    {
        $numero = trim((string) ($ticket->numero_ticket ?? ''));

        return 'TKT-' . preg_replace('/[^A-Za-z0-9\-_]/', '', $numero !== '' ? $numero : (string) $ticket->id_ticket);
    }

    /**
     * Rattache les fiches/tickets des camions de ce transporteur (ex. après ajout d'un camion).
     */
    public function reconcilierFichesPourTransporteur(Transporteur $transporteur): int
    {
        $links = TransporteurVehicule::query()
            ->where('transporteur_id', $transporteur->id)
            ->get(['vehicule_id', 'matricule_vehicule']);

        $matricules = $links->pluck('matricule_vehicule')
            ->map(fn ($m) => trim((string) $m))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $vehiculeIds = $links->pluck('vehicule_id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($matricules === [] && $vehiculeIds === []) {
            return 0;
        }

        $count = 0;

        $ticketsValides = Ticket::query()
            ->valide()
            ->where(function ($query) use ($matricules, $vehiculeIds) {
                if ($matricules !== []) {
                    $query->whereIn('matricule_vehicule', $matricules);
                }
                if ($vehiculeIds !== []) {
                    $method = $matricules !== [] ? 'orWhereIn' : 'whereIn';
                    $query->{$method}('vehicule_id', $vehiculeIds);
                }
            })
            ->get();

        $fichesExistantesParTicket = FicheSortie::query()
            ->whereIn('id_ticket', $ticketsValides->pluck('id_ticket')->filter()->all())
            ->get()
            ->keyBy('id_ticket');

        foreach ($ticketsValides as $ticket) {
            $ficheExistante = $fichesExistantesParTicket->get($ticket->id_ticket);
            if ($ficheExistante) {
                $this->synchroniserDonneesTicketSurFiche($ficheExistante);
                $count++;

                continue;
            }

            if ($this->synchroniserTicketTransporteur($ticket, null, $this->contextDepuisTicket($ticket))) {
                $count++;
            }
        }

        $ticketIds = $ticketsValides->pluck('id_ticket');

        $fiches = FicheSortie::query()
            ->where(function ($query) use ($matricules, $vehiculeIds, $ticketIds) {
                $hasClause = false;

                if ($matricules !== []) {
                    $query->whereIn('matricule_vehicule', $matricules);
                    $hasClause = true;
                }

                if ($vehiculeIds !== []) {
                    $hasClause
                        ? $query->orWhereIn('vehicule_id', $vehiculeIds)
                        : $query->whereIn('vehicule_id', $vehiculeIds);
                    $hasClause = true;
                }

                if ($ticketIds->isNotEmpty()) {
                    $hasClause
                        ? $query->orWhereIn('id_ticket', $ticketIds)
                        : $query->whereIn('id_ticket', $ticketIds);
                }
            })
            ->get();

        $ticketIdsTraites = $ticketsValides->pluck('id_ticket')->filter()->map(fn ($id) => (int) $id)->all();

        foreach ($fiches as $fiche) {
            if ($fiche->id_ticket && in_array((int) $fiche->id_ticket, $ticketIdsTraites, true)) {
                continue;
            }

            $this->synchroniserDonneesTicketSurFiche($fiche);
            $count++;
        }

        return $count;
    }

    /**
     * @return array{nom_usine?: string|null, produit_id?: int|null, nom_produit?: string, nom_agent?: string, numero_agent?: string, id_agent?: int|null}
     */
    private function contextDepuisTicket(Ticket $ticket): array
    {
        $context = [
            'nom_usine' => null,
            'produit_id' => null,
            'nom_produit' => '',
            'id_agent' => (int) ($ticket->id_agent ?? 0) ?: null,
            'nom_agent' => '',
            'numero_agent' => '',
        ];

        if ((int) ($ticket->id_usine ?? 0) > 0) {
            $nomUsine = Usine::query()
                ->where('id_usine', (int) $ticket->id_usine)
                ->value('nom_usine');
            if ($nomUsine) {
                $context['nom_usine'] = trim((string) $nomUsine);
            }
        }

        if ((int) ($ticket->id_agent ?? 0) > 0) {
            try {
                $agent = app(MesAgentsService::class)->findAgentById((int) $ticket->id_agent);
            } catch (\Throwable $e) {
                $agent = null;
            }
            if ($agent) {
                $context['nom_agent'] = trim((string) ($agent['nom_complet'] ?? ''));
                $context['numero_agent'] = trim((string) ($agent['numero_agent'] ?? ''));
            }
        }

        return $context;
    }

    private function ticketPourFiche(FicheSortie $fiche): ?Ticket
    {
        if ($fiche->id_ticket) {
            $ticket = Ticket::query()->where('id_ticket', $fiche->id_ticket)->first();
            if ($ticket) {
                return $ticket;
            }
        }

        $numero = trim((string) ($fiche->numero_ticket ?? ''));
        if ($numero !== '') {
            return Ticket::query()->where('numero_ticket', $numero)->first();
        }

        if ($fiche->matricule_vehicule && $fiche->date_chargement) {
            return Ticket::query()
                ->where('matricule_vehicule', $fiche->matricule_vehicule)
                ->whereDate('date_ticket', $fiche->date_chargement)
                ->whereHas('validation')
                ->orderByDesc('id_ticket')
                ->first();
        }

        return null;
    }

    private function donneesTicketPourFiche(Ticket $ticket): array
    {
        $data = [];

        if ($ticket->id_ticket) {
            $data['id_ticket'] = $ticket->id_ticket;
        }

        $numero = trim((string) ($ticket->numero_ticket ?? ''));
        if ($numero !== '') {
            $data['numero_ticket'] = $numero;
        }

        $poids = (float) ($ticket->poids ?? 0);
        if ($poids > 0) {
            $data['poids_pont'] = $poids;
        }

        if ($ticket->estValide() && $ticket->date_ticket) {
            $data['date_dechargement'] = $ticket->date_ticket->format('Y-m-d');
        }

        $matricule = trim((string) ($ticket->matricule_vehicule ?? ''));
        if ($matricule !== '') {
            $data['matricule_vehicule'] = $matricule;
        }

        if ((int) ($ticket->vehicule_id ?? 0) > 0) {
            $data['vehicule_id'] = (int) $ticket->vehicule_id;
        }

        if ((int) ($ticket->id_agent ?? 0) > 0) {
            $data['id_agent'] = (int) $ticket->id_agent;
            try {
                $agent = app(MesAgentsService::class)->findAgentById((int) $ticket->id_agent);
            } catch (\Throwable $e) {
                $agent = null;
            }
            if ($agent) {
                $nomAgent = trim((string) ($agent['nom_complet'] ?? ''));
                if ($nomAgent !== '') {
                    $data['nom_agent'] = $nomAgent;
                }
                $numeroAgent = trim((string) ($agent['numero_agent'] ?? ''));
                if ($numeroAgent !== '') {
                    $data['numero_agent'] = $numeroAgent;
                }
            }
        }

        if ((int) ($ticket->id_usine ?? 0) > 0) {
            $nomUsine = Usine::query()
                ->where('id_usine', (int) $ticket->id_usine)
                ->value('nom_usine');
            if ($nomUsine) {
                $data['usine'] = trim((string) $nomUsine);
            }
        }

        return $data;
    }
}
