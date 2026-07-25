<?php

namespace App\Services;

use App\Models\FicheSortie;
use App\Models\Parc;
use App\Models\PontEtat;
use App\Models\SortieStockPgf;
use App\Models\Stock;
use App\Models\StockPgf;
use InvalidArgumentException;

class FicheSortieDechargementService
{
    public function pontEstGerable(int $idPont): bool
    {
        return (bool) PontEtat::query()->where('id_pont', $idPont)->value('gerable');
    }

    /**
     * Enregistre le déchargement d'une fiche (ticket, poids usine) et déduit le stock si le pont est gérable.
     *
     * @throws InvalidArgumentException
     */
    public function decharger(
        FicheSortie $fiche,
        string $numeroTicket,
        string $dateDechargement,
        float $poidsDecharge,
        ?int $idTicket = null,
        ?string $usine = null,
        ?int $parcIdForce = null,
    ): void {
        if ($fiche->date_dechargement !== null) {
            throw new InvalidArgumentException('Cette fiche est déjà déchargée.');
        }

        $numeroTicket = trim($numeroTicket);
        if ($numeroTicket === '') {
            throw new InvalidArgumentException('Le numéro de ticket est obligatoire.');
        }

        if (FicheSortie::query()
            ->where('numero_ticket', $numeroTicket)
            ->where('id', '!=', $fiche->id)
            ->exists()) {
            throw new InvalidArgumentException('Ce numéro de ticket existe déjà sur une autre fiche de sortie.');
        }

        if ($poidsDecharge <= 0) {
            throw new InvalidArgumentException('Le poids du ticket doit être supérieur à 0.');
        }

        $pontGerable = $this->pontEstGerable((int) $fiche->id_pont);
        $stockOuvert = null;
        $parc = null;

        if ($pontGerable) {
            $parcId = $parcIdForce ?? $fiche->parc_id;
            if (!$parcId && $fiche->stock_id) {
                $parcId = Stock::query()->where('id', $fiche->stock_id)->value('parc_id');
            }
            if (!$parcId && $fiche->id_pont && $fiche->produit_id) {
                $stockAuto = $this->trouverStockActifPourPontEtProduit(
                    (int) $fiche->id_pont,
                    (int) $fiche->produit_id
                );
                if ($stockAuto) {
                    $parcId = $stockAuto->parc_id;
                }
            }
            if (!$parcId) {
                throw new InvalidArgumentException(
                    'Parc manquant : aucun parc avec stock ouvert pour ce pont et ce produit.'
                );
            }

            $parc = Parc::find($parcId);
            if (!$parc || (int) $parc->id_pont !== (int) $fiche->id_pont) {
                throw new InvalidArgumentException('Parc invalide pour ce pont.');
            }

            $stockOuvert = $this->resoudreStockPourDechargement($fiche, $parc->id);
            if (!$stockOuvert) {
                throw new InvalidArgumentException(
                    'Aucun stock ouvert pour ce parc avec le produit « ' . ($fiche->nom_produit ?? '-') . ' ».'
                );
            }
        }

        $montantAgent = app(MontantAgentFicheService::class)->calculerMontantPourFiche($fiche, $poidsDecharge);

        $updateData = [
            'date_dechargement' => $dateDechargement,
            'numero_ticket' => $numeroTicket,
            'poids_pont' => $poidsDecharge,
            'montant_agent' => $montantAgent !== null ? round($montantAgent, 2) : null,
            'stock_id' => $stockOuvert?->id,
            'parc_id' => $parc?->id,
            'nom_parc' => $parc?->nom,
        ];

        if ($idTicket !== null) {
            $updateData['id_ticket'] = $idTicket;
        }
        if ($usine !== null && trim($usine) !== '') {
            $updateData['usine'] = trim($usine);
        }

        $fiche->update($updateData);

        if ($pontGerable && $fiche->id_pont && $poidsDecharge > 0) {
            $stockActif = StockPgf::query()
                ->where('statut', 'actif')
                ->orderByDesc('created_at')
                ->first();

            if ($stockActif) {
                SortieStockPgf::create([
                    'stock_pgf_id' => $stockActif->id,
                    'fiche_sortie_id' => $fiche->id,
                    'id_pont' => $fiche->id_pont,
                    'nom_pont' => $fiche->nom_pont,
                    'code_pont' => $fiche->code_pont,
                    'quantite' => $poidsDecharge,
                    'date_sortie' => $dateDechargement,
                    'commentaire' => 'Sortie automatique - Fiche de sortie #' . $fiche->id
                        . ' - Véhicule: ' . $fiche->matricule_vehicule,
                ]);
            }
        }
    }

    private function resoudreStockPourDechargement(FicheSortie $fiche, int $parcId): ?Stock
    {
        if ($fiche->stock_id) {
            $stockLie = Stock::query()
                ->where('id', $fiche->stock_id)
                ->where('parc_id', $parcId)
                ->where('type', 'entree')
                ->where('statut', 'ouvert')
                ->first();

            if ($stockLie) {
                return $stockLie;
            }
        }

        $query = Stock::query()
            ->where('parc_id', $parcId)
            ->where('type', 'entree')
            ->where('statut', 'ouvert');

        if ($fiche->produit_id) {
            $stockProduit = (clone $query)->where('produit_id', $fiche->produit_id)->first();
            if ($stockProduit) {
                return $stockProduit;
            }
        }

        return $query->orderBy('id')->first();
    }

    private function queryStockOuvertPontProduit(int $idPont, int $produitId)
    {
        return Stock::query()
            ->where('id_pont', $idPont)
            ->where('produit_id', $produitId)
            ->where('type', 'entree')
            ->where('statut', 'ouvert')
            ->whereHas('parc', function ($query) use ($idPont) {
                $query->where('id_pont', $idPont)->where('statut', 'actif');
            });
    }

    private function stockEstActif(Stock $stock): bool
    {
        return ($stock->etat ?? 'actif') === 'actif';
    }

    private function trouverStockActifPourPontEtProduit(int $idPont, int $produitId): ?Stock
    {
        return $this->queryStockOuvertPontProduit($idPont, $produitId)
            ->orderBy('id')
            ->get()
            ->first(fn (Stock $stock) => $this->stockEstActif($stock));
    }
}
