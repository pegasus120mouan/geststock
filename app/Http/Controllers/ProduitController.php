<?php

namespace App\Http\Controllers;

use App\Models\Produit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProduitController extends Controller
{
    public function index(Request $request)
    {
        $query = Produit::query()->orderBy('nom');

        if ($request->filled('q')) {
            $query->where('nom', 'like', '%'.$request->string('q').'%');
        }

        if ($request->filled('statut')) {
            $query->where('statut', $request->string('statut'));
        }

        return view('produits.index', [
            'produits' => $query->paginate(15)->withQueryString(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nom' => ['required', 'string', 'max:255'],
            'image' => ['nullable', 'image', 'max:2048'],
            'statut' => ['required', 'in:actif,inactif'],
        ]);

        if ($request->hasFile('image')) {
            $validated['image'] = $request->file('image')->store('produits', 'public');
        }

        $validated['stock_ml'] = 0;
        $validated['prix_achat_ml'] = 0;

        Produit::query()->create($validated);

        return redirect()
            ->route('produits.index')
            ->with('success', 'Parfum créé avec succès.');
    }

    public function edit(Produit $produit)
    {
        return view('produits.edit', compact('produit'));
    }

    public function update(Request $request, Produit $produit)
    {
        $validated = $request->validate([
            'nom' => ['required', 'string', 'max:255'],
            'image' => ['nullable', 'image', 'max:2048'],
            'statut' => ['required', 'in:actif,inactif'],
        ]);

        if ($request->hasFile('image')) {
            if ($produit->image && str_contains($produit->image, '/')) {
                Storage::disk('public')->delete($produit->image);
            }
            $validated['image'] = $request->file('image')->store('produits', 'public');
        }

        $produit->update($validated);

        return redirect()
            ->route('produits.index')
            ->with('success', 'Parfum mis à jour.');
    }

    public function destroy(Produit $produit)
    {
        if ($produit->image && str_contains($produit->image, '/')) {
            Storage::disk('public')->delete($produit->image);
        }

        $produit->delete();

        return redirect()
            ->route('produits.index')
            ->with('success', 'Parfum supprimé.');
    }
}
