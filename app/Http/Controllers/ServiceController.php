<?php

namespace App\Http\Controllers;

use App\Models\Service;
use Illuminate\Http\Request;

class ServiceController extends Controller
{
    public function index()
    {
        $services = Service::withCount('fournisseurs')->orderBy('nom_service')->get();

        return view('services.index', compact('services'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nom_service' => ['required', 'string', 'max:255', 'unique:services,nom_service'],
        ]);

        Service::create($validated);

        return redirect()->back()->with('success', 'Service créé avec succès.');
    }

    public function update(Request $request, Service $service)
    {
        $validated = $request->validate([
            'nom_service' => ['required', 'string', 'max:255', 'unique:services,nom_service,' . $service->id],
        ]);

        $service->update($validated);

        return redirect()->back()->with('success', 'Service modifié avec succès.');
    }

    public function destroy(Service $service)
    {
        if ($service->fournisseurs()->count() > 0) {
            return redirect()->back()->with('error', 'Impossible de supprimer ce service car il est utilisé par des fournisseurs.');
        }

        $service->delete();

        return redirect()->back()->with('success', 'Service supprimé avec succès.');
    }
}
