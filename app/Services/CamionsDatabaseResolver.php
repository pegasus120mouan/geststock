<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CamionsDatabaseResolver
{
    private ?string $resolvedConnection = null;

    private bool $resolved = false;

    /**
     * Connexion vers la base agents / chef_equipe / tickets.
     * 1. EXTERNAL_CAMIONS_DB_CONNECTION si configurée
     * 2. Connexion Laravel par défaut si les tables existent
     */
    public function connection(): ?string
    {
        if ($this->resolved) {
            return $this->resolvedConnection;
        }

        $this->resolved = true;

        if ($this->dataSource() === 'api') {
            return $this->resolvedConnection = null;
        }

        return $this->resolvedConnection = $this->resolveCamionsConnection();
    }

    /**
     * Connexion pour l'authentification chef_equipe (indépendante de CAMIONS_DATA_SOURCE).
     */
    public function connectionForAuth(): ?string
    {
        return $this->resolveCamionsConnection();
    }

    private function resolveCamionsConnection(): ?string
    {
        $explicit = trim((string) config('database.default_external_camions', ''));
        if ($explicit !== '' && config("database.connections.{$explicit}")) {
            if ($this->hasCamionsTables($explicit)) {
                return $explicit;
            }
        }

        $default = (string) config('database.default', '');
        if ($default !== '' && $this->hasCamionsTables($default)) {
            return $default;
        }

        return null;
    }

    public function isAvailable(): bool
    {
        return $this->connection() !== null;
    }

    public function usesApi(): bool
    {
        return $this->dataSource() === 'api';
    }

    public function usesDatabaseOnly(): bool
    {
        return $this->dataSource() === 'database';
    }

    private function dataSource(): string
    {
        $value = strtolower(trim((string) config('services.external_auth.camions_data_source', 'auto')));

        return in_array($value, ['api', 'database', 'auto'], true) ? $value : 'auto';
    }

  /**
     * @return list<array{id_chef: int, nom: string, prenoms: string, nom_complet: string, token: string}>
     */
    public function listChefsEquipe(): array
    {
        $connection = $this->connection();
        if ($connection === null) {
            return [];
        }

        try {
            return DB::connection($connection)
                ->table('chef_equipe')
                ->select(['id_chef', 'nom', 'prenoms', 'token'])
                ->orderBy('nom')
                ->orderBy('prenoms')
                ->get()
                ->map(function ($chef) {
                    $nom = trim((string) $chef->nom);
                    $prenoms = trim((string) $chef->prenoms);

                    return [
                        'id_chef' => (int) $chef->id_chef,
                        'nom' => $nom,
                        'prenoms' => $prenoms,
                        'nom_complet' => trim($nom . ' ' . $prenoms),
                        'token' => (string) ($chef->token ?? ''),
                    ];
                })
                ->values()
                ->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    public function findChefById(int $idChef): ?array
    {
        if ($idChef <= 0) {
            return null;
        }

        $connection = $this->connection();
        if ($connection === null) {
            return null;
        }

        try {
            $chef = DB::connection($connection)
                ->table('chef_equipe')
                ->where('id_chef', $idChef)
                ->first();

            if (!$chef) {
                return null;
            }

            $nom = trim((string) $chef->nom);
            $prenoms = trim((string) $chef->prenoms);

            return [
                'id_chef' => (int) $chef->id_chef,
                'nom' => $nom,
                'prenoms' => $prenoms,
                'nom_complet' => trim($nom . ' ' . $prenoms),
                'token' => (string) ($chef->token ?? ''),
            ];
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function findChefByToken(string $token): ?array
    {
        $token = trim($token);
        if ($token === '') {
            return null;
        }

        $connection = $this->connection();
        if ($connection === null) {
            return null;
        }

        try {
            $chef = DB::connection($connection)
                ->table('chef_equipe')
                ->where('token', $token)
                ->first();

            if (!$chef) {
                return null;
            }

            $nom = trim((string) $chef->nom);
            $prenoms = trim((string) $chef->prenoms);

            return [
                'id_chef' => (int) $chef->id_chef,
                'nom' => $nom,
                'prenoms' => $prenoms,
                'nom_complet' => trim($nom . ' ' . $prenoms),
                'token' => $token,
            ];
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function hasCamionsTables(string $connection): bool
    {
        try {
            return Schema::connection($connection)->hasTable('chef_equipe')
                && Schema::connection($connection)->hasTable('agents');
        } catch (\Throwable $e) {
            return false;
        }
    }
}
