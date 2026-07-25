<?php

namespace App\Services;

use Illuminate\Http\Request;

class ChefEquipeSession
{
    public const SESSION_KEY = 'chef_equipe';

    public const AUTH_KEY = 'chef_equipe_auth';

    /**
     * @param  array{id_chef: int, nom: string, prenoms: string, nom_complet: string, token: string, login: string}  $chef
     */
    public function login(Request $request, array $chef): void
    {
        $request->session()->put(self::AUTH_KEY, true);
        $request->session()->put(self::SESSION_KEY, $chef);
        $request->session()->put('chef_equipe_id', (int) $chef['id_chef']);
        $request->session()->put('chef_equipe_token', (string) ($chef['token'] ?? ''));
    }

    public function logout(Request $request): void
    {
        $request->session()->forget([
            self::AUTH_KEY,
            self::SESSION_KEY,
            'chef_equipe_id',
            'chef_equipe_token',
        ]);
    }

    public function check(?Request $request = null): bool
    {
        $request ??= request();

        return (bool) $request->session()->get(self::AUTH_KEY, false);
    }

    /**
     * @return array{id_chef: int, nom: string, prenoms: string, nom_complet: string, token: string, login: string}|null
     */
    public function chef(?Request $request = null): ?array
    {
        $request ??= request();
        $chef = $request->session()->get(self::SESSION_KEY);

        return is_array($chef) ? $chef : null;
    }
}
