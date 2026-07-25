<?php

namespace App\Http\Controllers;

use App\Models\Chargeur;
use App\Models\ChefChargeur;
use Illuminate\Http\Request;

class ChargeurController extends Controller
{
    public function index()
    {
        $chargeurs = Chargeur::with('chefChargeur')->orderBy('nom')->paginate(20);
        $chefChargeurs = ChefChargeur::orderBy('nom')->get();

        return view('chargeurs.index', compact('chargeurs', 'chefChargeurs'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nom' => ['required', 'string', 'max:100'],
            'prenoms' => ['required', 'string', 'max:150'],
            'contact' => ['nullable', 'string', 'max:50'],
            'id_chef_chargeur' => ['nullable', 'integer', 'exists:chef_chargeurs,id'],
        ]);

        Chargeur::create($validated);

        return redirect()->route('chargeurs.index')->with('success', 'Chargeur créé avec succès.');
    }

    public function update(Request $request, Chargeur $chargeur)
    {
        $validated = $request->validate([
            'nom' => ['required', 'string', 'max:100'],
            'prenoms' => ['required', 'string', 'max:150'],
            'contact' => ['nullable', 'string', 'max:50'],
            'id_chef_chargeur' => ['nullable', 'integer', 'exists:chef_chargeurs,id'],
        ]);

        $chargeur->update($validated);

        return redirect()->route('chargeurs.index')->with('success', 'Chargeur modifié avec succès.');
    }

    public function destroy(Chargeur $chargeur)
    {
        $chargeur->delete();

        return redirect()->route('chargeurs.index')->with('success', 'Chargeur supprimé avec succès.');
    }
}
