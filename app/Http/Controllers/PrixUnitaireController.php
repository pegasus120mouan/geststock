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
            ->with(['produit', 'flacon'])
            ->when($request->filled('q'), function ($query) use ($request) {
                $q = '%'.$request->string('q').'%';
                $query->where(function ($inner) use ($q) {
                    $inner->whereHas('produit', fn ($p) => $p->where('nom', 'like', $q))
                        ->orWhereHas('flacon', fn ($f) => $f->where('nom', 'like', $q));
                });
            })
            ->when($request->filled('produit_id'), fn ($q) => $q->where('produit_id', $request->integer('produit_id')))
            ->when($request->filled('flacon_id'), fn ($q) => $q->where('flacon_id', $request->integer('flacon_id')))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('prix-unitaires.index', [
            'prixUnitaires' => $prixUnitaires,
            'produits' => Produit::query()->where('statut', 'actif')->orderBy('nom')->get(['id', 'nom']),
            'flacons' => Flacon::query()->actif()->orderBy('contenance_ml')->get(['id', 'nom', 'contenance_ml']),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'produit_id' => ['required', 'integer', Rule::exists('produits', 'id')],
            'flacon_id' => [
                'required',
                'integer',
                Rule::exists('flacons', 'id'),
                Rule::unique('prix_unitaires')->where(fn ($q) => $q->where('produit_id', $request->integer('produit_id'))),
            ],
            'prix' => ['required', 'numeric', 'min:0'],
        ], [
            'flacon_id.unique' => 'Un prix existe déjà pour ce parfum et cette contenance.',
        ]);

        PrixUnitaire::query()->create($validated);

        return redirect()
            ->route('prix-unitaires.index')
            ->with('success', 'Prix unitaire enregistré.');
    }

    public function update(Request $request, PrixUnitaire $prixUnitaire)
    {
        $validated = $request->validate([
            'produit_id' => ['required', 'integer', Rule::exists('produits', 'id')],
            'flacon_id' => [
                'required',
                'integer',
                Rule::exists('flacons', 'id'),
                Rule::unique('prix_unitaires')
                    ->where(fn ($q) => $q->where('produit_id', $request->integer('produit_id')))
                    ->ignore($prixUnitaire->id),
            ],
            'prix' => ['required', 'numeric', 'min:0'],
        ], [
            'flacon_id.unique' => 'Un prix existe déjà pour ce parfum et cette contenance.',
        ]);

        $prixUnitaire->update($validated);

        return redirect()
            ->route('prix-unitaires.index')
            ->with('success', 'Prix unitaire mis à jour.');
    }

    public function destroy(PrixUnitaire $prixUnitaire)
    {
        $prixUnitaire->delete();

        return redirect()
            ->route('prix-unitaires.index')
            ->with('success', 'Prix unitaire supprimé.');
    }
}
