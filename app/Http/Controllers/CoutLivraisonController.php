<?php

namespace App\Http\Controllers;

use App\Models\Commune;
use App\Models\CoutLivraison;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CoutLivraisonController extends Controller
{
    public function index(Request $request)
    {
        $couts = CoutLivraison::query()
            ->with('commune')
            ->when($request->filled('q'), function ($query) use ($request) {
                $q = '%'.$request->string('q').'%';
                $query->whereHas('commune', fn ($c) => $c->where('nom', 'like', $q));
            })
            ->when($request->filled('statut'), fn ($q) => $q->where('statut', $request->string('statut')))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $communes = Commune::query()
            ->actif()
            ->orderBy('nom')
            ->get(['id', 'nom']);

        $communesDisponibles = Commune::query()
            ->actif()
            ->whereDoesntHave('coutLivraison')
            ->orderBy('nom')
            ->get(['id', 'nom']);

        return view('couts-livraison.index', compact('couts', 'communes', 'communesDisponibles'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'commune_id' => ['required', 'integer', Rule::exists('communes', 'id'), 'unique:couts_livraison,commune_id'],
            'montant' => ['required', 'numeric', 'min:0'],
            'statut' => ['required', 'in:actif,inactif'],
        ], [
            'commune_id.unique' => 'Un coût de livraison existe déjà pour cette commune.',
        ]);

        CoutLivraison::query()->create([
            'commune_id' => $validated['commune_id'],
            'montant' => round((float) $validated['montant'], 2),
            'statut' => $validated['statut'],
        ]);

        return redirect()
            ->route('couts-livraison.index')
            ->with('success', 'Coût de livraison créé.');
    }

    public function update(Request $request, CoutLivraison $couts_livraison)
    {
        $validated = $request->validate([
            'commune_id' => [
                'required',
                'integer',
                Rule::exists('communes', 'id'),
                Rule::unique('couts_livraison', 'commune_id')->ignore($couts_livraison->id),
            ],
            'montant' => ['required', 'numeric', 'min:0'],
            'statut' => ['required', 'in:actif,inactif'],
        ], [
            'commune_id.unique' => 'Un coût de livraison existe déjà pour cette commune.',
        ]);

        $couts_livraison->update([
            'commune_id' => $validated['commune_id'],
            'montant' => round((float) $validated['montant'], 2),
            'statut' => $validated['statut'],
        ]);

        return redirect()
            ->route('couts-livraison.index')
            ->with('success', 'Coût de livraison mis à jour.');
    }

    public function destroy(CoutLivraison $couts_livraison)
    {
        $couts_livraison->delete();

        return redirect()
            ->route('couts-livraison.index')
            ->with('success', 'Coût de livraison supprimé.');
    }
}
