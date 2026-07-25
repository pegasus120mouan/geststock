<?php

namespace App\Http\Controllers;

use App\Enums\VolumeUnit;
use App\Models\Produit;
use App\Models\StockMouvement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StockController extends Controller
{
    public function entrees(Request $request)
    {
        $mouvements = StockMouvement::query()
            ->with(['produit', 'user'])
            ->where('type', 'entree')
            ->when($request->filled('q'), function ($query) use ($request) {
                $q = '%'.$request->string('q').'%';
                $query->whereHas('produit', fn ($p) => $p->where('nom', 'like', $q));
            })
            ->when($request->filled('produit_id'), fn ($q) => $q->where('produit_id', $request->integer('produit_id')))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('stock.entrees', [
            'mouvements' => $mouvements,
            'produits' => Produit::query()->where('statut', 'actif')->orderBy('nom')->get(),
        ]);
    }

    public function storeEntree(Request $request)
    {
        $validated = $request->validate([
            'produit_id' => ['required', 'exists:produits,id'],
            'quantite' => ['required', 'numeric', 'gt:0'],
            'commentaire' => ['nullable', 'string', 'max:500'],
        ]);

        DB::transaction(function () use ($validated, $request) {
            $produit = Produit::query()->lockForUpdate()->findOrFail($validated['produit_id']);
            $quantiteMl = round((float) $validated['quantite'], 2);
            $stockAvant = (float) $produit->stock_ml;
            $stockApres = round($stockAvant + $quantiteMl, 2);

            $produit->update(['stock_ml' => $stockApres]);

            StockMouvement::query()->create([
                'produit_id' => $produit->id,
                'user_id' => $request->user()->id,
                'type' => 'entree',
                'quantite' => $quantiteMl,
                'unite' => VolumeUnit::Ml,
                'quantite_ml' => $quantiteMl,
                'stock_avant' => $stockAvant,
                'stock_apres' => $stockApres,
                'commentaire' => $validated['commentaire'] ?? null,
            ]);
        });

        return redirect()
            ->route('stock.entrees')
            ->with('success', 'Entrée de stock enregistrée.');
    }

    public function sorties(Request $request)
    {
        $mouvements = StockMouvement::query()
            ->with(['produit', 'user'])
            ->where('type', 'sortie')
            ->when($request->filled('q'), function ($query) use ($request) {
                $q = '%'.$request->string('q').'%';
                $query->whereHas('produit', fn ($p) => $p->where('nom', 'like', $q));
            })
            ->when($request->filled('produit_id'), fn ($q) => $q->where('produit_id', $request->integer('produit_id')))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('stock.sorties', [
            'mouvements' => $mouvements,
            'produits' => Produit::query()->orderBy('nom')->get(),
        ]);
    }
}
