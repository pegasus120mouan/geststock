<?php

namespace App\Http\Controllers;

use App\Models\Flacon;
use App\Models\Produit;
use App\Models\StockMouvement;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $produits = Produit::query()->orderBy('nom')->get();

        $totalStockMl = (float) $produits->sum('stock_ml');
        $stockActifsMl = (float) $produits->where('statut', 'actif')->sum('stock_ml');
        $stockInactifsMl = (float) $produits->where('statut', '!=', 'actif')->sum('stock_ml');

        $totalEntrees = (float) StockMouvement::query()->where('type', 'entree')->sum('quantite_ml');
        $totalSorties = (float) StockMouvement::query()->where('type', 'sortie')->sum('quantite_ml');

        $nbParfums = $produits->count();
        $nbFlacons = Flacon::query()->count();
        $nbRuptures = $produits->where('statut', 'actif')->where('stock_ml', '<=', 0)->count();
        $nbMouvements = StockMouvement::query()->count();

        $seuilFaible = 500;
        $stockFaible = $produits
            ->where('statut', 'actif')
            ->filter(fn (Produit $p) => $p->stock_ml > 0 && $p->stock_ml < $seuilFaible);

        $mouvementsByProduit = StockMouvement::query()
            ->selectRaw("
                produit_id,
                SUM(CASE WHEN type = 'entree' THEN quantite_ml ELSE 0 END) as entrees,
                SUM(CASE WHEN type = 'sortie' THEN quantite_ml ELSE 0 END) as sorties
            ")
            ->groupBy('produit_id')
            ->get()
            ->keyBy('produit_id');

        $stockParParfum = $produits->map(function (Produit $produit) use ($mouvementsByProduit) {
            $stats = $mouvementsByProduit->get($produit->id);
            $entrees = (float) ($stats->entrees ?? 0);
            $sorties = (float) ($stats->sorties ?? 0);
            $disponible = (float) $produit->stock_ml;
            $utilisation = $entrees > 0
                ? (int) min(100, round(($sorties / $entrees) * 100))
                : ($disponible <= 0 ? 100 : 0);

            return (object) [
                'produit' => $produit,
                'entrees' => $entrees,
                'sorties' => $sorties,
                'disponible' => $disponible,
                'utilisation' => $utilisation,
            ];
        });

        return view('dashboard', [
            'totalStockMl' => $totalStockMl,
            'stockActifsMl' => $stockActifsMl,
            'stockInactifsMl' => $stockInactifsMl,
            'totalEntrees' => $totalEntrees,
            'totalSorties' => $totalSorties,
            'nbParfums' => $nbParfums,
            'nbFlacons' => $nbFlacons,
            'nbRuptures' => $nbRuptures,
            'nbMouvements' => $nbMouvements,
            'stockParParfum' => $stockParParfum,
            'stockFaible' => $stockFaible,
            'nbStockFaible' => $stockFaible->count(),
            'seuilFaible' => $seuilFaible,
        ]);
    }
}
