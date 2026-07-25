<?php

namespace App\Services;

use App\Models\FicheSortie;
use Illuminate\Support\Collection;

class FicheSortieTicketCorrespondanceService
{
    /**
     * @param  array<string, mixed>  $ticket
     */
    public function correspond(array $ticket, FicheSortie $fiche): bool
    {
        return $this->raisonNonCorrespondance($ticket, $fiche) === null;
    }

    /**
     * @param  array<string, mixed>  $ticket
     */
    public function raisonNonCorrespondance(array $ticket, FicheSortie $fiche): ?string
    {
        if (! $this->vehiculesCorrespondent($ticket, $fiche)) {
            return 'Le véhicule de la fiche ne correspond pas au ticket.';
        }

        if (! $this->agentsCorrespondent($ticket, $fiche)) {
            return 'L\'agent de la fiche ne correspond pas au ticket.';
        }

        if (! $this->pontsCorrespondent($ticket, $fiche)) {
            return 'Le pont de la fiche ne correspond pas au ticket.';
        }

        if (! $this->usinesCorrespondent($ticket, $fiche)) {
            return 'L\'usine de la fiche ne correspond pas au ticket.';
        }

        return null;
    }

    /**
     * @param  Collection<int, FicheSortie>  $fiches
     * @param  array<string, mixed>  $ticket
     * @return Collection<int, FicheSortie>
     */
    public function filtrer(Collection $fiches, array $ticket): Collection
    {
        return $fiches
            ->filter(fn (FicheSortie $fiche) => $this->correspond($ticket, $fiche))
            ->values();
    }

    /**
     * @param  array<string, mixed>  $ticket
     * @return array<string, mixed>
     */
    public function ticketDepuisApi(array $ticket): array
    {
        return [
            'vehicule_id' => (int) ($ticket['vehicule_id'] ?? 0),
            'matricule_vehicule' => (string) ($ticket['matricule_vehicule'] ?? ''),
            'id_agent' => (int) ($ticket['id_agent'] ?? 0),
            'id_pont' => (int) ($ticket['id_pont'] ?? 0),
            'nom_pont' => (string) ($ticket['nom_pont'] ?? ''),
            'nom_usine' => (string) ($ticket['nom_usine'] ?? ''),
        ];
    }

    /**
     * @param  array<string, mixed>  $ticket
     */
    private function vehiculesCorrespondent(array $ticket, FicheSortie $fiche): bool
    {
        $ticketVehiculeId = (int) ($ticket['vehicule_id'] ?? 0);
        $ficheVehiculeId = (int) ($fiche->vehicule_id ?? 0);

        if ($ticketVehiculeId > 0 && $ficheVehiculeId > 0 && $ticketVehiculeId === $ficheVehiculeId) {
            return true;
        }

        $ticketMatricule = $this->normaliserMatricule((string) ($ticket['matricule_vehicule'] ?? ''));
        $ficheMatricule = $this->normaliserMatricule((string) ($fiche->matricule_vehicule ?? ''));

        return $ticketMatricule !== '' && $ticketMatricule === $ficheMatricule;
    }

    /**
     * @param  array<string, mixed>  $ticket
     */
    private function agentsCorrespondent(array $ticket, FicheSortie $fiche): bool
    {
        $ticketAgentId = (int) ($ticket['id_agent'] ?? 0);
        $ficheAgentId = (int) ($fiche->id_agent ?? 0);

        return $ticketAgentId > 0 && $ficheAgentId > 0 && $ticketAgentId === $ficheAgentId;
    }

    /**
     * @param  array<string, mixed>  $ticket
     */
    private function pontsCorrespondent(array $ticket, FicheSortie $fiche): bool
    {
        $ticketPontId = (int) ($ticket['id_pont'] ?? 0);
        $fichePontId = (int) ($fiche->id_pont ?? 0);

        if ($ticketPontId > 0 && $fichePontId > 0) {
            return $ticketPontId === $fichePontId;
        }

        $ticketPont = $this->normaliserTexte((string) ($ticket['nom_pont'] ?? ''));
        $fichePont = $this->normaliserTexte((string) ($fiche->nom_pont ?? ''));

        return $ticketPont !== '' && $fichePont !== '' && $ticketPont === $fichePont;
    }

    /**
     * @param  array<string, mixed>  $ticket
     */
    private function usinesCorrespondent(array $ticket, FicheSortie $fiche): bool
    {
        $ticketUsine = $this->normaliserTexte((string) ($ticket['nom_usine'] ?? ''));
        $ficheUsine = $this->normaliserTexte((string) ($fiche->usine ?? ''));

        return $ticketUsine !== '' && $ficheUsine !== '' && $ticketUsine === $ficheUsine;
    }

    private function normaliserMatricule(string $value): string
    {
        return strtoupper(preg_replace('/\s+/', '', trim($value)) ?? '');
    }

    private function normaliserTexte(string $value): string
    {
        return mb_strtolower(trim($value), 'UTF-8');
    }
}
