<?php

namespace App\Http\Controllers;

use App\Models\Fournisseur;
use App\Models\Service;
use Illuminate\Http\Request;

class FournisseurController extends Controller
{
    public function index()
    {
        $fournisseurs = Fournisseur::with('service')->orderBy('nom')->get();
        $services = Service::orderBy('nom_service')->get();

        return view('fournisseurs.index', compact('fournisseurs', 'services'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nom' => ['required', 'string', 'max:255'],
            'service_id' => ['required', 'integer', 'exists:services,id'],
        ]);

        Fournisseur::create($validated);

        return redirect()->back()->with('success', 'Fournisseur créé avec succès.');
    }

    public function update(Request $request, Fournisseur $fournisseur)
    {
        $validated = $request->validate([
            'nom' => ['required', 'string', 'max:255'],
            'service_id' => ['required', 'integer', 'exists:services,id'],
        ]);

        $fournisseur->update($validated);

        return redirect()->back()->with('success', 'Fournisseur modifié avec succès.');
    }

    public function destroy(Fournisseur $fournisseur)
    {
        $fournisseur->delete();

        return redirect()->back()->with('success', 'Fournisseur supprimé avec succès.');
    }
}
