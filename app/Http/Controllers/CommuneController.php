<?php

namespace App\Http\Controllers;

use App\Models\Commune;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CommuneController extends Controller
{
    public function index(Request $request)
    {
        $communes = Commune::query()
            ->with('coutLivraison')
            ->when($request->filled('q'), function ($query) use ($request) {
                $q = '%'.$request->string('q').'%';
                $query->where('nom', 'like', $q);
            })
            ->when($request->filled('statut'), fn ($q) => $q->where('statut', $request->string('statut')))
            ->orderBy('nom')
            ->paginate(20)
            ->withQueryString();

        return view('communes.index', compact('communes'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nom' => ['required', 'string', 'max:255', 'unique:communes,nom'],
            'statut' => ['required', 'in:actif,inactif'],
        ]);

        Commune::query()->create($validated);

        return redirect()
            ->route('communes.index')
            ->with('success', 'Commune créée avec succès.');
    }

    public function update(Request $request, Commune $commune)
    {
        $validated = $request->validate([
            'nom' => ['required', 'string', 'max:255', Rule::unique('communes', 'nom')->ignore($commune->id)],
            'statut' => ['required', 'in:actif,inactif'],
        ]);

        $commune->update($validated);

        return redirect()
            ->route('communes.index')
            ->with('success', 'Commune mise à jour.');
    }

    public function destroy(Commune $commune)
    {
        $commune->delete();

        return redirect()
            ->route('communes.index')
            ->with('success', 'Commune supprimée.');
    }
}
