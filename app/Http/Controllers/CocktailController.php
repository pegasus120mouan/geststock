<?php

namespace App\Http\Controllers;

use App\Models\Cocktail;
use App\Models\Produit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CocktailController extends Controller
{
    public function index(Request $request)
    {
        $cocktails = Cocktail::query()
            ->withCount('lignes')
            ->with('lignes')
            ->when($request->filled('q'), function ($query) use ($request) {
                $q = '%'.$request->string('q').'%';
                $query->where('nom', 'like', $q);
            })
            ->when($request->filled('statut'), fn ($q) => $q->where('statut', $request->string('statut')))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $produits = Produit::query()
            ->where('statut', 'actif')
            ->orderBy('nom')
            ->get(['id', 'nom']);

        return view('cocktails.index', compact('cocktails', 'produits'));
    }

    public function create()
    {
        return redirect()->route('cocktails.index', ['create' => 1]);
    }

    public function store(Request $request)
    {
        $validated = $this->validateCocktail($request);

        DB::transaction(function () use ($validated) {
            $cocktail = Cocktail::query()->create([
                'nom' => $validated['nom'],
                'statut' => $validated['statut'],
            ]);

            $this->syncLignes($cocktail, $validated['lignes']);
        });

        return redirect()
            ->route('cocktails.index')
            ->with('success', 'Cocktail créé avec succès.');
    }

    public function show(Cocktail $cocktail)
    {
        $cocktail->load(['lignes.produit']);

        return view('cocktails.show', compact('cocktail'));
    }

    public function edit(Cocktail $cocktail)
    {
        return redirect()->route('cocktails.index', ['edit' => $cocktail->id]);
    }

    public function update(Request $request, Cocktail $cocktail)
    {
        $validated = $this->validateCocktail($request);

        DB::transaction(function () use ($validated, $cocktail) {
            $cocktail->update([
                'nom' => $validated['nom'],
                'statut' => $validated['statut'],
            ]);

            $cocktail->lignes()->delete();
            $this->syncLignes($cocktail, $validated['lignes']);
        });

        return redirect()
            ->route('cocktails.index')
            ->with('success', 'Cocktail mis à jour.');
    }

    public function destroy(Cocktail $cocktail)
    {
        $cocktail->delete();

        return redirect()
            ->route('cocktails.index')
            ->with('success', 'Cocktail supprimé.');
    }

    private function validateCocktail(Request $request): array
    {
        return $request->validate([
            'nom' => ['required', 'string', 'max:255'],
            'statut' => ['required', 'in:actif,inactif'],
            'lignes' => ['required', 'array', 'min:1'],
            'lignes.*.produit_id' => ['required', 'integer', Rule::exists('produits', 'id')],
            'lignes.*.quantite_ml' => ['required', 'numeric', 'gt:0'],
        ], [
            'lignes.required' => 'Ajoutez au moins un parfum au cocktail.',
            'lignes.*.produit_id.required' => 'Sélectionnez un parfum.',
            'lignes.*.quantite_ml.required' => 'Indiquez la quantité.',
            'lignes.*.quantite_ml.gt' => 'La quantité doit être supérieure à 0.',
        ]);
    }

    private function syncLignes(Cocktail $cocktail, array $lignes): void
    {
        $seen = [];

        foreach ($lignes as $ligne) {
            $produitId = (int) $ligne['produit_id'];

            if (isset($seen[$produitId])) {
                continue;
            }

            $seen[$produitId] = true;

            $cocktail->lignes()->create([
                'produit_id' => $produitId,
                'quantite_ml' => round((float) $ligne['quantite_ml'], 2),
            ]);
        }
    }
}
