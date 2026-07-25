<?php

namespace App\Services;

use App\Models\BordereauTransporteur;
use App\Models\Depense;
use App\Models\FicheSortie;
use Illuminate\Support\Facades\DB;

class BordereauTransporteurService
{
    public function __construct(
        private TicketTransporteurFicheService $ticketFicheService,
    ) {}

    public function lettresTransporteur(?string $code): string
    {
        $code = strtoupper(trim((string) $code));
        if ($code === '') {
            return 'XX';
        }

        $prefix = explode('-', $code)[0] ?? $code;
        $letters = preg_replace('/[^A-Z]/u', '', $prefix) ?? '';

        if (mb_strlen($letters) >= 2) {
            return mb_substr($letters, 0, 3);
        }

        return str_pad($letters, 2, 'X');
    }

    public function genererNumero(?string $code = null): string
    {
        $prefix = 'BTR-' . $this->lettresTransporteur($code);

        return DB::transaction(function () use ($prefix) {
            $numeros = BordereauTransporteur::query()
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

    public function exempleNumero(?string $code): string
    {
        return 'BTR-' . $this->lettresTransporteur($code) . '1';
    }

    /**
     * @return list<int>
     */
    public function ficheIdsDejaBorderees(int $transporteurId): array
    {
        return FicheSortie::query()
            ->where('transporteur_id', $transporteurId)
            ->whereNotNull('bordereau_transporteur_id')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    public function assignerFichesAuBordereau(BordereauTransporteur $bordereau, array $ficheIds): void
    {
        $ficheIds = array_values(array_unique(array_map('intval', $ficheIds)));
        if ($ficheIds === []) {
            return;
        }

        FicheSortie::query()
            ->where('transporteur_id', $bordereau->transporteur_id)
            ->whereIn('id', $ficheIds)
            ->whereNull('bordereau_transporteur_id')
            ->update(['bordereau_transporteur_id' => $bordereau->id]);
    }

    public function libererFichesDuBordereau(BordereauTransporteur $bordereau): void
    {
        FicheSortie::query()
            ->where('bordereau_transporteur_id', $bordereau->id)
            ->update(['bordereau_transporteur_id' => null]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function fichesEligibles(int $transporteurId, string $dateDebut, string $dateFin): array
    {
        $fiches = FicheSortie::query()
            ->where('transporteur_id', $transporteurId)
            ->whereNull('bordereau_transporteur_id')
            ->whereNotNull('prix_unitaire_transport')
            ->where('prix_unitaire_transport', '>', 0)
            ->whereDate('date_chargement', '>=', $dateDebut)
            ->whereDate('date_chargement', '<=', $dateFin)
            ->orderBy('date_chargement')
            ->get();

        $lignes = [];
        foreach ($fiches as $fiche) {
            $montant = $this->montantLigneFiche($fiche);
            if ($montant <= 0) {
                continue;
            }

            $lignes[] = $this->serialiserLigneFiche($fiche, $montant);
        }

        return $lignes;
    }

    /**
     * @param  list<int>  $ficheIds
     * @return list<array<string, mixed>>
     */
    public function construireFichesData(int $transporteurId, array $ficheIds): array
    {
        $ficheIds = array_values(array_unique(array_map('intval', $ficheIds)));
        if ($ficheIds === []) {
            return [];
        }

        $fiches = FicheSortie::query()
            ->where('transporteur_id', $transporteurId)
            ->whereIn('id', $ficheIds)
            ->whereNull('bordereau_transporteur_id')
            ->whereNotNull('prix_unitaire_transport')
            ->where('prix_unitaire_transport', '>', 0)
            ->get()
            ->keyBy('id');

        $lignes = [];
        foreach ($ficheIds as $ficheId) {
            $fiche = $fiches->get($ficheId);
            if (! $fiche) {
                continue;
            }

            $montant = $this->montantLigneFiche($fiche);
            if ($montant <= 0) {
                continue;
            }

            $lignes[] = $this->serialiserLigneFiche($fiche, $montant);
        }

        return $lignes;
    }

    /**
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

    public function montantLigneFiche(FicheSortie $fiche): int
    {
        $poids = $this->ticketFicheService->poidsEffectif($fiche);
        $pu = (float) ($fiche->prix_unitaire_transport ?? 0);

        if ($poids <= 0 || $pu <= 0) {
            return 0;
        }

        return (int) round($poids * $pu);
    }

    public function avanceFiche(FicheSortie $fiche): float
    {
        $depenses = Depense::query()
            ->where('matricule_vehicule', $fiche->matricule_vehicule)
            ->whereDate('date_depense', '>=', $fiche->date_chargement)
            ->whereDate('date_depense', '<=', $fiche->date_dechargement ?? $fiche->date_chargement)
            ->sum('montant');

        return (float) (($fiche->carburant ?? 0) + ($fiche->frais_route ?? 0) + $depenses);
    }

    /**
     * @return array<string, mixed>
     */
    private function serialiserLigneFiche(FicheSortie $fiche, int $montant): array
    {
        $poids = $this->ticketFicheService->poidsEffectif($fiche);

        return [
            'fiche_id' => (int) $fiche->id,
            'numero_fiche' => $fiche->numero_fiche,
            'date_chargement' => $fiche->date_chargement?->format('Y-m-d'),
            'date_dechargement' => $fiche->date_dechargement?->format('Y-m-d'),
            'matricule_vehicule' => $fiche->matricule_vehicule,
            'nom_produit' => $fiche->nom_produit,
            'usine' => $this->ticketFicheService->usineNomEffectif($fiche) ?: $fiche->usine,
            'numero_ticket' => $this->ticketFicheService->numeroTicketEffectif($fiche),
            'nom_agent' => $this->ticketFicheService->agentNomEffectif($fiche) ?: $fiche->nom_agent,
            'poids' => $poids,
            'prix_unitaire' => (float) ($fiche->prix_unitaire_transport ?? 0),
            'montant' => $montant,
            'avance' => (int) round($this->avanceFiche($fiche)),
        ];
    }
}
