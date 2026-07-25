<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ChefEquipeContext
{
    public function __construct(
        private CamionsDatabaseResolver $databaseResolver,
    ) {}

    public function connection(): ?string
    {
        return $this->databaseResolver->connection();
    }

    /**
     * Token du chef d'équipe pour l'utilisateur / la requête courante.
     */
    public function resolveToken(?Request $request = null): string
    {
        if ($request) {
            $fromQuery = trim((string) $request->query('token', ''));
            if ($fromQuery !== '') {
                return $fromQuery;
            }

            $fromSession = trim((string) $request->session()->get('chef_equipe_token', ''));
            if ($fromSession !== '') {
                return $fromSession;
            }
        }

        $chefSession = app(ChefEquipeSession::class);
        if ($chefSession->check($request)) {
            $chef = $chefSession->chef($request);
            if ($chef && ($chef['token'] ?? '') !== '') {
                return (string) $chef['token'];
            }
        }

        $user = $request?->user() ?? Auth::user();
        if ($user instanceof User) {
            $idChef = (int) ($user->id_chef ?? 0);
            if ($idChef > 0) {
                $chef = $this->databaseResolver->findChefById($idChef);
                if ($chef && $chef['token'] !== '') {
                    return $chef['token'];
                }
            }

            $fromUser = trim((string) ($user->chef_equipe_token ?? ''));
            if ($fromUser !== '') {
                return $fromUser;
            }
        }

        return trim((string) config('services.external_auth.default_chef_equipe_token', ''));
    }

    public function resolveIdChef(?Request $request = null): ?int
    {
        $chefSession = app(ChefEquipeSession::class);
        if ($chefSession->check($request)) {
            $chef = $chefSession->chef($request);
            if ($chef) {
                return (int) $chef['id_chef'];
            }
        }

        $token = $this->resolveToken($request);
        if ($token !== '') {
            $chef = $this->databaseResolver->findChefByToken($token);
            if ($chef) {
                return $chef['id_chef'];
            }
        }

        $user = $request?->user() ?? Auth::user();
        if ($user instanceof User && (int) ($user->id_chef ?? 0) > 0) {
            return (int) $user->id_chef;
        }

        return null;
    }

    public function resolveChef(?Request $request = null): ?array
    {
        $chefSession = app(ChefEquipeSession::class);
        if ($chefSession->check($request)) {
            return $chefSession->chef($request);
        }

        $token = $this->resolveToken($request);
        if ($token !== '') {
            return $this->databaseResolver->findChefByToken($token);
        }

        $idChef = $this->resolveIdChef($request);
        if ($idChef) {
            return $this->databaseResolver->findChefById($idChef);
        }

        return null;
    }

    /**
     * @return array{token?: string, id_chef?: int}
     */
    public function apiQueryParams(?Request $request = null): array
    {
        $token = $this->resolveToken($request);
        if ($token !== '') {
            return ['token' => $token];
        }

        $idChef = $this->resolveIdChef($request);
        if ($idChef) {
            return ['id_chef' => $idChef];
        }

        return [];
    }

    public function findChefById(int $idChef): ?array
    {
        return $this->databaseResolver->findChefById($idChef);
    }

    public function findChefByToken(string $token): ?array
    {
        return $this->databaseResolver->findChefByToken($token);
    }

    /**
     * @return list<array{id_chef: int, nom: string, prenoms: string, nom_complet: string, token: string}>
     */
    public function listChefsEquipe(): array
    {
        return $this->databaseResolver->listChefsEquipe();
    }
}
