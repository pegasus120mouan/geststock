<?php

namespace App\Http\Controllers;

use App\Services\ChefEquipeContext;
use App\Services\SoldeChefEquipeService;
use Illuminate\Http\Request;

class SoldeChefEquipeController extends Controller
{
    public function index(Request $request, SoldeChefEquipeService $service, ChefEquipeContext $chefContext)
    {
        $token = $chefContext->resolveToken($request);
        $chef = $chefContext->resolveChef($request);
        $solde = null;
        $apiError = null;

        if ($token !== '') {
            $solde = $service->getSoldeByToken($token);
            if (!$solde) {
                $apiError = 'Aucun solde trouvé pour ce token. Vérifiez le token ou l\'API.';
            }
        }

        return view('solde_chef_equipe.index', [
            'token' => $token,
            'chef' => $chef,
            'solde' => $solde,
            'apiError' => $apiError,
        ]);
    }

    public function updateToken(Request $request, SoldeChefEquipeService $service, ChefEquipeContext $chefContext)
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'max:50'],
        ]);

        $token = trim($validated['token']);
        $request->session()->put('chef_equipe_token', $token);

        $chef = $chefContext->findChefByToken($token);
        if ($chef) {
            $request->session()->put('chef_equipe_id', $chef['id_chef']);
        }

        $user = $request->user();
        if ($user) {
            $user->chef_equipe_token = $token;
            if ($chef) {
                $user->id_chef = $chef['id_chef'];
            }
            $user->save();
        }

        $solde = $service->getSoldeByToken($token);
        if (!$solde) {
            return redirect()
                ->route('solde_chef_equipe.index')
                ->withInput()
                ->with('warning', 'Token enregistré, mais l\'API n\'a retourné aucun solde pour ce token.');
        }

        return redirect()
            ->route('solde_chef_equipe.index')
            ->with('success', 'Token enregistré. Solde chargé avec succès.');
    }

    public function show(Request $request, SoldeChefEquipeService $service, ChefEquipeContext $chefContext)
    {
        $token = $chefContext->resolveToken($request);

        if ($token === '') {
            return response()->json([
                'success' => false,
                'error' => 'Token chef d\'équipe manquant.',
            ], 422);
        }

        $solde = $service->getSoldeByToken($token);

        if (!$solde) {
            return response()->json([
                'success' => false,
                'error' => 'Solde introuvable pour ce token.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'solde' => $solde,
        ]);
    }
}
