<?php

namespace App\Http\Controllers;

use App\Models\Flacon;
use App\Models\PrixUnitaire;
use App\Models\Produit;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PrixUnitaireController extends Controller
{
    public function index(Request $request)
    {
        $prixUnitaires = PrixUnitaire::query()
            ->with(['flacon'])
            ->withCount('produits')
            ->when($request->filled('q'), function ($query) use ($request) {
                $q = '%'.$request->string('q').'%';
                $query->where(function ($inner) use ($q) {
                    $inner->where('reference', 'like', $q)
                        ->orWhereHas('flacon', fn ($f) => $f->where('nom', 'like', $q))
                        ->orWhereHas('produits', fn ($p) => $p->where('nom', 'like', $q));
                });
            })
            ->when($request->filled('flacon_id'), fn ($q) => $q->where('flacon_id', $request->integer('flacon_id')))
            ->when($request->filled('categorie'), fn ($q) => $q->where('categorie', $request->string('categorie')))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('prix-unitaires.index', [
            'prixUnitaires' => $prixUnitaires,
            'flacons' => Flacon::query()->actif()->orderBy('contenance_ml')->get(['id', 'nom', 'contenance_ml']),
            'categories' => PrixUnitaire::categories(),
            'produits' => Produit::query()->where('statut', 'actif')->orderBy('nom')->get(['id', 'nom']),
        ]);
    }

    public function show(PrixUnitaire $prixUnitaire)
    {
        $prixUnitaire->load(['flacon', 'produits' => fn ($q) => $q->orderBy('nom')]);

        $dejaAssociesIds = $prixUnitaire->produits->pluck('id')->all();

        $dejaTarifesIds = Produit::query()
            ->whereHas('prixUnitaires', function ($q) use ($prixUnitaire) {
                $q->where('flacon_id', $prixUnitaire->flacon_id)
                    ->where('categorie', $prixUnitaire->categorie);
            })
            ->pluck('id')
            ->all();

        $produits = Produit::query()
            ->where('statut', 'actif')
            ->orderBy('nom')
            ->get(['id', 'nom', 'stock_ml', 'statut'])
            ->map(function (Produit $produit) use ($dejaAssociesIds, $dejaTarifesIds) {
                $bloque = in_array($produit->id, $dejaTarifesIds, true);

                return (object) [
                    'id' => $produit->id,
                    'nom' => $produit->nom,
                    'stock_ml' => $produit->stock_ml,
                    'deja_associe' => in_array($produit->id, $dejaAssociesIds, true),
                    'deja_tarife' => $bloque,
                    'selectionnable' => ! $bloque,
                ];
            });

        return view('prix-unitaires.show', compact('prixUnitaire', 'produits'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'reference' => ['nullable', 'string', 'max:50', 'unique:prix_unitaires,reference'],
            'flacon_id' => ['required', 'integer', Rule::exists('flacons', 'id')],
            'categorie' => ['required', Rule::in(array_keys(PrixUnitaire::categories()))],
            'prix' => ['required', 'numeric', 'min:0'],
            'produit_ids' => ['nullable', 'array'],
            'produit_ids.*' => ['integer', Rule::exists('produits', 'id')],
        ]);

        $prixUnitaire = PrixUnitaire::query()->create([
            'reference' => $validated['reference'] ?: PrixUnitaire::generateReference(),
            'flacon_id' => $validated['flacon_id'],
            'categorie' => $validated['categorie'],
            'prix' => round((float) $validated['prix'], 2),
        ]);

        if (! empty($validated['produit_ids'])) {
            $prixUnitaire->produits()->sync($validated['produit_ids']);
        }

        return redirect()
            ->route('prix-unitaires.show', $prixUnitaire)
            ->with('success', 'Prix unitaire créé.');
    }

    public function update(Request $request, PrixUnitaire $prixUnitaire)
    {
        $validated = $request->validate([
            'reference' => ['required', 'string', 'max:50', Rule::unique('prix_unitaires', 'reference')->ignore($prixUnitaire->id)],
            'flacon_id' => ['required', 'integer', Rule::exists('flacons', 'id')],
            'categorie' => ['required', Rule::in(array_keys(PrixUnitaire::categories()))],
            'prix' => ['required', 'numeric', 'min:0'],
        ]);

        $prixUnitaire->update([
            'reference' => $validated['reference'],
            'flacon_id' => $validated['flacon_id'],
            'categorie' => $validated['categorie'],
            'prix' => round((float) $validated['prix'], 2),
        ]);

        return redirect()
            ->route('prix-unitaires.show', $prixUnitaire)
            ->with('success', 'Prix unitaire mis à jour.');
    }

    public function attachProduit(Request $request, PrixUnitaire $prixUnitaire)
    {
        $validated = $request->validate([
            'produit_ids' => ['required', 'array', 'min:1'],
            'produit_ids.*' => ['integer', Rule::exists('produits', 'id')],
        ]);

        $dejaTarifesIds = Produit::query()
            ->whereIn('id', $validated['produit_ids'])
            ->whereHas('prixUnitaires', function ($q) use ($prixUnitaire) {
                $q->where('flacon_id', $prixUnitaire->flacon_id)
                    ->where('categorie', $prixUnitaire->categorie);
            })
            ->pluck('id')
            ->all();

        $aAssocier = collect($validated['produit_ids'])
            ->map(fn ($id) => (int) $id)
            ->reject(fn ($id) => in_array($id, $dejaTarifesIds, true))
            ->unique()
            ->values()
            ->all();

        if ($aAssocier === []) {
            return redirect()
                ->route('prix-unitaires.show', $prixUnitaire)
                ->withErrors(['produit_ids' => 'Aucun parfum sélectionné n’est disponible (déjà tarifé pour cette contenance et catégorie).']);
        }

        $prixUnitaire->produits()->syncWithoutDetaching($aAssocier);

        return redirect()
            ->route('prix-unitaires.show', $prixUnitaire)
            ->with('success', count($aAssocier).' parfum(s) associé(s) à ce prix unitaire.');
    }

    public function detachProduit(PrixUnitaire $prixUnitaire, Produit $produit)
    {
        $prixUnitaire->produits()->detach($produit->id);

        return redirect()
            ->route('prix-unitaires.show', $prixUnitaire)
            ->with('success', 'Parfum retiré de ce prix unitaire.');
    }

    public function destroy(PrixUnitaire $prixUnitaire)
    {
        $prixUnitaire->produits()->detach();
        $prixUnitaire->delete();

        return redirect()
            ->route('prix-unitaires.index')
            ->with('success', 'Prix unitaire supprimé.');
    }
}
