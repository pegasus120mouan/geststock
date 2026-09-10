<?php

namespace App\Http\Controllers;

use App\Models\Flacon;
use App\Models\PrixUnitaire;
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

    public function show(Produit $produit)
    {
        $flacons = Flacon::query()->actif()->orderBy('contenance_ml')->get();

        $prix = $produit->prixUnitaires()
            ->with('flacon')
            ->get()
            ->groupBy('flacon_id');

        $lignes = $flacons->map(function (Flacon $flacon) use ($prix) {
            $parCategorie = ($prix->get($flacon->id) ?? collect())->keyBy('categorie');

            return (object) [
                'flacon' => $flacon,
                'prix_detail' => (float) ($parCategorie->get(PrixUnitaire::CATEGORIE_DETAIL)?->prix ?? 0),
                'prix_en_gros' => (float) ($parCategorie->get(PrixUnitaire::CATEGORIE_EN_GROS)?->prix ?? 0),
                'ref_detail' => $parCategorie->get(PrixUnitaire::CATEGORIE_DETAIL)?->reference,
                'ref_en_gros' => $parCategorie->get(PrixUnitaire::CATEGORIE_EN_GROS)?->reference,
            ];
        });

        $mouvements = $produit->stockMouvements()
            ->with('user')
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $onglet = request('onglet', 'prix');

        return view('produits.show', compact('produit', 'lignes', 'mouvements', 'onglet'));
    }

    public function updateSeuil(Request $request, Produit $produit)
    {
        $validated = $request->validate([
            'seuil_alerte_ml' => ['nullable', 'numeric', 'min:0'],
        ]);

        $seuil = $validated['seuil_alerte_ml'] ?? null;
        $produit->update([
            'seuil_alerte_ml' => ($seuil === null || $seuil === '') ? null : round((float) $seuil, 2),
        ]);

        return redirect()
            ->route('produits.show', ['produit' => $produit, 'onglet' => 'seuil'])
            ->with('success', 'Seuil d’alerte enregistré.');
    }

    public function storePrix(Request $request, Produit $produit)
    {
        $validated = $request->validate([
            'flacon_id' => ['required', 'integer', 'exists:flacons,id'],
            'detail' => ['nullable', 'numeric', 'min:0'],
            'en_gros' => ['nullable', 'numeric', 'min:0'],
        ]);

        if (
            ($validated['detail'] === null || $validated['detail'] === '')
            && ($validated['en_gros'] === null || $validated['en_gros'] === '')
        ) {
            return redirect()
                ->route('produits.show', ['produit' => $produit, 'onglet' => 'prix', 'create_prix' => 1])
                ->withInput()
                ->withErrors(['detail' => 'Saisissez au moins un prix (détail ou en gros).']);
        }

        $flaconId = (int) $validated['flacon_id'];
        $this->upsertPrix($produit->id, $flaconId, PrixUnitaire::CATEGORIE_DETAIL, $validated['detail'] ?? null);
        $this->upsertPrix($produit->id, $flaconId, PrixUnitaire::CATEGORIE_EN_GROS, $validated['en_gros'] ?? null);

        return redirect()
            ->route('produits.show', ['produit' => $produit, 'onglet' => 'prix'])
            ->with('success', 'Prix unitaire enregistré.');
    }

    public function updatePrix(Request $request, Produit $produit)
    {
        $validated = $request->validate([
            'prix' => ['required', 'array'],
            'prix.*.detail' => ['nullable', 'numeric', 'min:0'],
            'prix.*.en_gros' => ['nullable', 'numeric', 'min:0'],
        ]);

        $flaconIds = Flacon::query()->actif()->pluck('id')->all();

        foreach ($validated['prix'] as $flaconId => $montants) {
            $flaconId = (int) $flaconId;

            if (! in_array($flaconId, $flaconIds, true)) {
                continue;
            }

            $this->upsertPrix($produit->id, $flaconId, PrixUnitaire::CATEGORIE_DETAIL, $montants['detail'] ?? null);
            $this->upsertPrix($produit->id, $flaconId, PrixUnitaire::CATEGORIE_EN_GROS, $montants['en_gros'] ?? null);
        }

        return redirect()
            ->route('produits.show', ['produit' => $produit, 'onglet' => 'prix'])
            ->with('success', 'Prix unitaires enregistrés.');
    }

    private function upsertPrix(int $produitId, int $flaconId, string $categorie, mixed $prix): void
    {
        $produit = Produit::query()->findOrFail($produitId);

        $existants = $produit->prixUnitaires()
            ->where('flacon_id', $flaconId)
            ->where('categorie', $categorie)
            ->get();

        if ($prix === null || $prix === '') {
            if ($existants->isNotEmpty()) {
                $produit->prixUnitaires()->detach($existants->pluck('id'));
            }

            return;
        }

        $tarif = PrixUnitaire::trouverOuCreer($flaconId, $categorie, (float) $prix);

        if ($existants->isNotEmpty()) {
            $produit->prixUnitaires()->detach($existants->pluck('id'));
        }

        $produit->prixUnitaires()->syncWithoutDetaching([$tarif->id]);
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
