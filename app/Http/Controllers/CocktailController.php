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

    public function storeFromCommande(Request $request)
    {
        $nom = trim((string) $request->input('nom', ''));
        $request->merge([
            'nom' => $nom,
            'lignes' => $this->lignesParfumSeulement($request->input('lignes', [])),
        ]);

        $validated = $request->validate([
            'nom' => ['required', 'string', 'max:255'],
            'lignes' => ['required', 'array', 'min:2'],
            'lignes.*.produit_id' => ['required', 'integer', Rule::exists('produits', 'id')],
        ], [
            'nom.required' => 'Indiquez le nom du cocktail.',
            'lignes.required' => 'Ajoutez au moins deux parfums au cocktail.',
            'lignes.min' => 'Un cocktail doit associer au moins deux parfums.',
            'lignes.*.produit_id.required' => 'Sélectionnez un parfum.',
        ]);

        $this->assurerDeuxParfumsDistincts($validated['lignes']);

        $existant = Cocktail::query()
            ->with(['lignes.produit'])
            ->whereRaw('LOWER(TRIM(nom)) = ?', [mb_strtolower($validated['nom'])])
            ->first();

        if ($existant) {
            return response()->json($existant->toCatalogArray());
        }

        $cocktail = DB::transaction(function () use ($validated) {
            $cocktail = Cocktail::query()->create([
                'nom' => $validated['nom'],
                'statut' => 'actif',
            ]);

            $this->syncLignes($cocktail, $validated['lignes']);

            return $cocktail->load(['lignes.produit']);
        });

        return response()->json($cocktail->toCatalogArray(), 201);
    }

    private function validateCocktail(Request $request): array
    {
        $request->merge([
            'lignes' => $this->lignesParfumSeulement($request->input('lignes', [])),
        ]);

        $validated = $request->validate([
            'nom' => ['required', 'string', 'max:255'],
            'statut' => ['required', 'in:actif,inactif'],
            'lignes' => ['required', 'array', 'min:2'],
            'lignes.*.produit_id' => ['required', 'integer', Rule::exists('produits', 'id')],
        ], [
            'lignes.required' => 'Ajoutez au moins deux parfums au cocktail.',
            'lignes.min' => 'Un cocktail doit associer au moins deux parfums.',
            'lignes.*.produit_id.required' => 'Sélectionnez un parfum.',
        ]);

        $this->assurerDeuxParfumsDistincts($validated['lignes']);

        return $validated;
    }

    /**
     * @param  mixed  $lignes
     * @return array<int, array<string, mixed>>
     */
    private function lignesParfumSeulement(mixed $lignes): array
    {
        return collect(is_array($lignes) ? $lignes : [])
            ->filter(fn ($ligne) => filled($ligne['produit_id'] ?? null))
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $lignes
     */
    private function assurerDeuxParfumsDistincts(array $lignes): void
    {
        $distincts = collect($lignes)->pluck('produit_id')->filter()->unique()->count();

        if ($distincts < 2) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'lignes' => 'Un cocktail doit associer au moins deux parfums différents.',
            ]);
        }
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
                'quantite_ml' => null,
            ]);
        }
    }
}
