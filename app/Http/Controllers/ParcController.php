<?php

namespace App\Http\Controllers;

use App\Models\Parc;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class ParcController extends Controller
{
    public function index()
    {
        $parcs = Parc::orderBy('created_at', 'desc')->get();
        
        // Récupérer les ponts pour le formulaire
        $ponts = [];
        try {
            $mesPontsUrl = (string) config('services.external_auth.mes_ponts_url');
            $timeout = (int) config('services.external_auth.timeout', 10);
            $response = Http::acceptJson()->withoutVerifying()->timeout($timeout)->get($mesPontsUrl);
            if ($response->successful()) {
                $ponts = $response->json('ponts') ?? [];
            }
        } catch (\Throwable $e) {}

        return view('parcs.index', compact('parcs', 'ponts'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nom' => ['required', 'string', 'max:255'],
            'id_pont' => ['required', 'integer'],
            'adresse' => ['nullable', 'string', 'max:255'],
            'telephone' => ['nullable', 'string', 'max:50'],
            'responsable' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        // Récupérer les infos du pont
        $pont = [];
        try {
            $mesPontsUrl = (string) config('services.external_auth.mes_ponts_url');
            $timeout = (int) config('services.external_auth.timeout', 10);
            $response = Http::acceptJson()->withoutVerifying()->timeout($timeout)->get($mesPontsUrl);
            if ($response->successful()) {
                $ponts = $response->json('ponts') ?? [];
                foreach ($ponts as $p) {
                    if (($p['id_pont'] ?? 0) == $validated['id_pont']) {
                        $pont = $p;
                        break;
                    }
                }
            }
        } catch (\Throwable $e) {}

        $codePont = $pont['code_pont'] ?? 'PONT';
        $code = Parc::generateCode($codePont);

        Parc::create([
            'nom' => $validated['nom'],
            'code' => $code,
            'id_pont' => $validated['id_pont'],
            'nom_pont' => $pont['nom_pont'] ?? '',
            'code_pont' => $codePont,
            'adresse' => $validated['adresse'] ?? null,
            'telephone' => $validated['telephone'] ?? null,
            'responsable' => $validated['responsable'] ?? null,
            'description' => $validated['description'] ?? null,
            'statut' => 'actif',
        ]);

        return redirect()->route('parcs.index')->with('success', 'Parc créé avec succès. Code: ' . $code);
    }

    public function update(Request $request, int $id)
    {
        $parc = Parc::findOrFail($id);

        $validated = $request->validate([
            'nom' => ['required', 'string', 'max:255'],
            'id_pont' => ['required', 'integer'],
            'adresse' => ['nullable', 'string', 'max:255'],
            'telephone' => ['nullable', 'string', 'max:50'],
            'responsable' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'statut' => ['required', 'in:actif,inactif'],
        ]);

        // Récupérer les infos du pont si changé
        if ($parc->id_pont != $validated['id_pont']) {
            $pont = [];
            try {
                $mesPontsUrl = (string) config('services.external_auth.mes_ponts_url');
                $timeout = (int) config('services.external_auth.timeout', 10);
                $response = Http::acceptJson()->withoutVerifying()->timeout($timeout)->get($mesPontsUrl);
                if ($response->successful()) {
                    $ponts = $response->json('ponts') ?? [];
                    foreach ($ponts as $p) {
                        if (($p['id_pont'] ?? 0) == $validated['id_pont']) {
                            $pont = $p;
                            break;
                        }
                    }
                }
            } catch (\Throwable $e) {}

            $parc->id_pont = $validated['id_pont'];
            $parc->nom_pont = $pont['nom_pont'] ?? '';
            $parc->code_pont = $pont['code_pont'] ?? '';
        }

        $parc->nom = $validated['nom'];
        $parc->adresse = $validated['adresse'] ?? null;
        $parc->telephone = $validated['telephone'] ?? null;
        $parc->responsable = $validated['responsable'] ?? null;
        $parc->description = $validated['description'] ?? null;
        $parc->statut = $validated['statut'];
        $parc->save();

        return redirect()->route('parcs.index')->with('success', 'Parc modifié avec succès.');
    }

    public function destroy(int $id)
    {
        $parc = Parc::findOrFail($id);
        $parc->delete();

        return redirect()->route('parcs.index')->with('success', 'Parc supprimé avec succès.');
    }
}
