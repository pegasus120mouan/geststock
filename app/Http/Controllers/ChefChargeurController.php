<?php

namespace App\Http\Controllers;

use App\Models\ChefChargeur;
use App\Models\ChefChargeurPrix;
use Illuminate\Http\Request;

class ChefChargeurController extends Controller
{
    public function index()
    {
        $chefChargeurs = ChefChargeur::orderBy('nom')->paginate(20);
        return view('chef_chargeurs.index', compact('chefChargeurs'));
    }

    public function create()
    {
        return view('chef_chargeurs.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nom' => ['required', 'string', 'max:100'],
            'prenoms' => ['required', 'string', 'max:150'],
            'contact' => ['nullable', 'string', 'max:50'],
            'prix_unitaire' => ['nullable', 'integer', 'min:0'],
            'date_debut' => ['nullable', 'date'],
            'date_fin' => ['nullable', 'date'],
        ]);

        ChefChargeur::create($validated);

        return redirect()->route('chef_chargeurs.index')->with('success', 'Chef des chargeurs créé avec succès.');
    }

    public function edit(ChefChargeur $chefChargeur)
    {
        return view('chef_chargeurs.edit', compact('chefChargeur'));
    }

    public function update(Request $request, ChefChargeur $chefChargeur)
    {
        $validated = $request->validate([
            'nom' => ['required', 'string', 'max:100'],
            'prenoms' => ['required', 'string', 'max:150'],
            'contact' => ['nullable', 'string', 'max:50'],
            'prix_unitaire' => ['nullable', 'integer', 'min:0'],
            'date_debut' => ['nullable', 'date'],
            'date_fin' => ['nullable', 'date'],
        ]);

        $chefChargeur->update($validated);

        return redirect()->route('chef_chargeurs.index')->with('success', 'Chef des chargeurs modifié avec succès.');
    }

    public function destroy(ChefChargeur $chefChargeur)
    {
        $chefChargeur->delete();
        return redirect()->route('chef_chargeurs.index')->with('success', 'Chef des chargeurs supprimé avec succès.');
    }

    public function show(ChefChargeur $chefChargeur)
    {
        $chefChargeur->load(['chargeurs', 'prixPeriodes']);

        return view('chef_chargeurs.show', [
            'chef' => $chefChargeur,
        ]);
    }

    public function storePrix(Request $request, ChefChargeur $chefChargeur)
    {
        $validated = $request->validate([
            'prix_unitaire' => ['required', 'integer', 'min:0'],
            'date_debut' => ['required', 'date'],
            'date_fin' => ['nullable', 'date'],
        ]);

        $dateDebut = $validated['date_debut'];
        $dateFin = $validated['date_fin'] ?? null;

        $chevauchement = $chefChargeur->prixPeriodes()
            ->where(function ($query) use ($dateDebut, $dateFin) {
                $query->where(function ($q) use ($dateDebut, $dateFin) {
                    $q->where('date_debut', '<=', $dateDebut)
                      ->where(function ($q2) use ($dateDebut) {
                          $q2->whereNull('date_fin')
                             ->orWhere('date_fin', '>=', $dateDebut);
                      });
                })->orWhere(function ($q) use ($dateDebut, $dateFin) {
                    if ($dateFin) {
                        $q->where('date_debut', '<=', $dateFin)
                          ->where('date_debut', '>=', $dateDebut);
                    } else {
                        $q->where('date_debut', '>=', $dateDebut);
                    }
                });
            })
            ->exists();

        if ($chevauchement) {
            return redirect()->route('chef_chargeurs.show', $chefChargeur)
                ->with('error', 'Cette période chevauche une période existante. Veuillez choisir des dates différentes.');
        }

        $chefChargeur->prixPeriodes()->create($validated);

        return redirect()->route('chef_chargeurs.show', $chefChargeur)->with('success', 'Prix ajouté avec succès.');
    }

    public function updatePrix(Request $request, ChefChargeur $chefChargeur, ChefChargeurPrix $prix)
    {
        $validated = $request->validate([
            'prix_unitaire' => ['required', 'integer', 'min:0'],
            'date_debut' => ['required', 'date'],
            'date_fin' => ['nullable', 'date'],
        ]);

        $dateDebut = $validated['date_debut'];
        $dateFin = $validated['date_fin'] ?? null;

        $chevauchement = $chefChargeur->prixPeriodes()
            ->where('id', '!=', $prix->id)
            ->where(function ($query) use ($dateDebut, $dateFin) {
                $query->where(function ($q) use ($dateDebut, $dateFin) {
                    $q->where('date_debut', '<=', $dateDebut)
                      ->where(function ($q2) use ($dateDebut) {
                          $q2->whereNull('date_fin')
                             ->orWhere('date_fin', '>=', $dateDebut);
                      });
                })->orWhere(function ($q) use ($dateDebut, $dateFin) {
                    if ($dateFin) {
                        $q->where('date_debut', '<=', $dateFin)
                          ->where('date_debut', '>=', $dateDebut);
                    } else {
                        $q->where('date_debut', '>=', $dateDebut);
                    }
                });
            })
            ->exists();

        if ($chevauchement) {
            return redirect()->route('chef_chargeurs.show', $chefChargeur)
                ->with('error', 'Cette période chevauche une période existante. Veuillez choisir des dates différentes.');
        }

        $prix->update($validated);

        return redirect()->route('chef_chargeurs.show', $chefChargeur)->with('success', 'Prix modifié avec succès.');
    }

    public function destroyPrix(ChefChargeur $chefChargeur, ChefChargeurPrix $prix)
    {
        $prix->delete();

        return redirect()->route('chef_chargeurs.show', $chefChargeur)->with('success', 'Prix supprimé avec succès.');
    }
}
