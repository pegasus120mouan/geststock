<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class ChefEquipeAuthService
{
    public function __construct(
        private CamionsDatabaseResolver $databaseResolver,
    ) {}

    /**
     * @return array{id_chef: int, nom: string, prenoms: string, nom_complet: string, token: string, login: string}|null
     */
    public function attempt(string $login, string $password): ?array
    {
        $login = trim($login);
        if ($login === '' || $password === '') {
            return null;
        }

        if ($this->databaseResolver->connectionForAuth() !== null) {
            $chef = $this->attemptViaDatabase($login, $password);
            if ($chef !== null) {
                return $chef;
            }
        }

        return $this->attemptViaApi($login, $password);
    }

    /**
     * @return array{id_chef: int, nom: string, prenoms: string, nom_complet: string, token: string, login: string}|null
     */
    private function attemptViaDatabase(string $login, string $password): ?array
    {
        $connection = $this->databaseResolver->connectionForAuth();
        if ($connection === null) {
            return null;
        }

        try {
            $row = DB::connection($connection)->selectOne(
                'SELECT id_chef, nom, prenoms, token, login, password
                FROM chef_equipe
                WHERE LOWER(TRIM(login)) = LOWER(?)
                LIMIT 1',
                [$login]
            );

            if (!$row || trim((string) ($row->password ?? '')) === '') {
                return null;
            }

            if (hash('sha256', $password) !== (string) $row->password) {
                return null;
            }

            return $this->normalizeChef((array) $row);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * @return array{id_chef: int, nom: string, prenoms: string, nom_complet: string, token: string, login: string}|null
     */
    private function attemptViaApi(string $login, string $password): ?array
    {
        $url = (string) config('services.external_auth.chef_login_url', '');
        if ($url === '') {
            return null;
        }

        try {
            $response = Http::acceptJson()
                ->withoutVerifying()
                ->timeout((int) config('services.external_auth.timeout', 10))
                ->post($url, [
                    'login' => $login,
                    'password' => $password,
                ]);

            if (!$response->successful()) {
                return null;
            }

            $chef = $response->json('chef');
            if (!is_array($chef) || empty($chef['id_chef'])) {
                return null;
            }

            return $this->normalizeChef($chef);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{id_chef: int, nom: string, prenoms: string, nom_complet: string, token: string, login: string}
     */
    private function normalizeChef(array $row): array
    {
        $nom = trim((string) ($row['nom'] ?? ''));
        $prenoms = trim((string) ($row['prenoms'] ?? ''));

        return [
            'id_chef' => (int) ($row['id_chef'] ?? 0),
            'nom' => $nom,
            'prenoms' => $prenoms,
            'nom_complet' => trim($nom . ' ' . $prenoms),
            'token' => (string) ($row['token'] ?? ''),
            'login' => trim((string) ($row['login'] ?? '')),
        ];
    }
}
