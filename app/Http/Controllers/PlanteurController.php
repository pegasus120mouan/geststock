<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class PlanteurController extends Controller
{
    public function index(Request $request)
    {
        $planteurs = [];
        $total = 0;
        $error = null;

        try {
            $response = Http::acceptJson()
                ->withoutVerifying()
                ->timeout(15)
                ->get('https://api.objetombrepegasus.online/api/planteur/actions/planteurs.php');

            if ($response->successful()) {
                $data = $response->json();
                if ($data['success'] ?? false) {
                    $planteurs = $data['data']['planteurs'] ?? [];
                    $total = $data['data']['total'] ?? count($planteurs);
                }
            }
        } catch (\Throwable $e) {
            $error = 'Impossible de récupérer les planteurs depuis l\'API.';
        }

        return view('planteurs.index', compact('planteurs', 'total', 'error'));
    }

    public function show($id)
    {
        $planteur = null;
        $error = null;

        try {
            $response = Http::acceptJson()
                ->withoutVerifying()
                ->timeout(15)
                ->get('https://api.objetombrepegasus.online/api/planteur/actions/planteurs.php', [
                    'id' => $id
                ]);

            if ($response->successful()) {
                $data = $response->json();
                if ($data['success'] ?? false) {
                    $planteur = $data['data'] ?? null;
                }
            }
        } catch (\Throwable $e) {
            $error = 'Impossible de récupérer les détails du planteur.';
        }

        return view('planteurs.show', compact('planteur', 'error'));
    }

    public function update(Request $request, $id)
    {
        try {
            $response = Http::acceptJson()
                ->withoutVerifying()
                ->timeout(15)
                ->put('https://api.objetombrepegasus.online/api/planteur/actions/planteurs.php', [
                    'id' => $id,
                    'nom_prenoms' => $request->input('nom_prenoms'),
                    'telephone' => $request->input('telephone'),
                    'date_naissance' => $request->input('date_naissance'),
                    'lieu_naissance' => $request->input('lieu_naissance'),
                    'piece_identite' => $request->input('piece_identite'),
                    'situation_matrimoniale' => $request->input('situation_matrimoniale'),
                    'nombre_enfants' => $request->input('nombre_enfants'),
                ]);

            if ($response->successful() && ($response->json()['success'] ?? false)) {
                return redirect()->route('planteurs.index')
                    ->with('success', 'Planteur modifié avec succès.');
            }

            return redirect()->route('planteurs.index')
                ->with('error', 'Erreur lors de la modification du planteur.');
        } catch (\Throwable $e) {
            return redirect()->route('planteurs.index')
                ->with('error', 'Impossible de modifier le planteur.');
        }
    }

    public function destroy($id)
    {
        try {
            $response = Http::acceptJson()
                ->withoutVerifying()
                ->timeout(15)
                ->delete('https://api.objetombrepegasus.online/api/planteur/actions/planteurs.php', [
                    'id' => $id
                ]);

            if ($response->successful() && ($response->json()['success'] ?? false)) {
                return redirect()->route('planteurs.index')
                    ->with('success', 'Planteur supprimé avec succès.');
            }

            return redirect()->route('planteurs.index')
                ->with('error', 'Erreur lors de la suppression du planteur.');
        } catch (\Throwable $e) {
            return redirect()->route('planteurs.index')
                ->with('error', 'Impossible de supprimer le planteur.');
        }
    }
}
