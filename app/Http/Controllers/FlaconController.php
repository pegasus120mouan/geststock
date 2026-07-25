<?php

namespace App\Http\Controllers;

use App\Models\Flacon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FlaconController extends Controller
{
    public function index(Request $request)
    {
        $flacons = Flacon::query()
            ->when($request->filled('q'), function ($query) use ($request) {
                $q = '%'.$request->string('q').'%';
                $query->where('nom', 'like', $q);
            })
            ->when($request->filled('statut'), fn ($q) => $q->where('statut', $request->string('statut')))
            ->orderBy('contenance_ml')
            ->paginate(15)
            ->withQueryString();

        return view('flacons.index', compact('flacons'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nom' => ['required', 'string', 'max:255'],
            'contenance_ml' => ['required', 'integer', 'min:1', 'unique:flacons,contenance_ml'],
            'statut' => ['required', 'in:actif,inactif'],
        ]);

        $validated['prix'] = 0;

        Flacon::query()->create($validated);

        return redirect()
            ->route('flacons.index')
            ->with('success', 'Flacon créé avec succès.');
    }

    public function edit(Flacon $flacon)
    {
        return view('flacons.edit', compact('flacon'));
    }

    public function update(Request $request, Flacon $flacon)
    {
        $validated = $request->validate([
            'nom' => ['required', 'string', 'max:255'],
            'contenance_ml' => [
                'required',
                'integer',
                'min:1',
                Rule::unique('flacons', 'contenance_ml')->ignore($flacon->id),
            ],
            'statut' => ['required', 'in:actif,inactif'],
        ]);

        $flacon->update($validated);

        return redirect()
            ->route('flacons.index')
            ->with('success', 'Flacon mis à jour.');
    }

    public function destroy(Flacon $flacon)
    {
        $flacon->delete();

        return redirect()
            ->route('flacons.index')
            ->with('success', 'Flacon supprimé.');
    }
}
