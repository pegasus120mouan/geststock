<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\CaisseService;
use App\Services\ChefEquipeSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CaisseController extends Controller
{
    public function __construct(
        private readonly CaisseService $caisseService,
    ) {}

    public function index(Request $request): View
    {
        $filters = [
            'type' => (string) $request->query('type', 'all'),
            'origine' => (string) $request->query('origine', 'all'),
            'search' => trim((string) $request->query('search', '')),
            'date_debut' => $request->query('date_debut'),
            'date_fin' => $request->query('date_fin'),
        ];

        return view('caisse.index', [
            'stats' => $this->caisseService->stats(),
            'mouvements' => $this->caisseService->paginatedMouvements($filters),
            'filters' => $filters,
        ]);
    }

    public function storeApprovisionnement(Request $request, ChefEquipeSession $chefSession): RedirectResponse
    {
        $request->merge([
            'montant' => preg_replace('/\s+/u', '', (string) $request->input('montant', '')),
        ]);

        $validated = $request->validate([
            'montant' => ['required', 'numeric', 'min:1'],
            'source' => ['nullable', 'string', 'max:150'],
            'motifs' => ['nullable', 'string', 'max:255'],
        ]);

        $user = $this->resolveUser($request, $chefSession);

        $this->caisseService->createApprovisionnement(
            (float) $validated['montant'],
            trim((string) ($validated['source'] ?? '')),
            $user,
            $validated['motifs'] ?? null,
        );

        return redirect()
            ->route('caisse.index')
            ->with(
                'success',
                'Approvisionnement de '.number_format((float) $validated['montant'], 0, ',', ' ')
                .' FCFA enregistré sur la caisse.'
            );
    }

    private function resolveUser(Request $request, ChefEquipeSession $chefSession): ?User
    {
        $user = $request->user();
        if ($user instanceof User) {
            return $user;
        }

        $chef = $chefSession->chef($request);
        if (! $chef) {
            return null;
        }

        $idChef = (int) ($chef['id_chef'] ?? 0);
        $login = trim((string) ($chef['login'] ?? ''));

        $userQuery = User::query();
        if ($idChef > 0) {
            $userQuery->where('id_chef', $idChef);
        }
        if ($login !== '') {
            $userQuery->when(
                $idChef > 0,
                fn ($q) => $q->orWhere('login', $login),
                fn ($q) => $q->where('login', $login),
            );
        }

        $existing = $userQuery->first();
        if ($existing) {
            return $existing;
        }

        if ($idChef <= 0 && $login === '') {
            return null;
        }

        $loginToUse = $login !== '' ? $login : 'chef-'.$idChef;
        if (User::query()->where('login', $loginToUse)->exists()) {
            $loginToUse = 'chef-'.$idChef.'-'.Str::lower(Str::random(4));
        }

        return User::create([
            'name' => (string) ($chef['nom'] ?? 'Chef'),
            'prenom' => $chef['prenoms'] ?? null,
            'login' => $loginToUse,
            'id_chef' => $idChef > 0 ? $idChef : null,
            'chef_equipe_token' => (string) ($chef['token'] ?? ''),
            'password' => Hash::make(Str::random(32)),
            'role' => 'agent',
        ]);
    }
}
