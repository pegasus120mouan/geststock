<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

class SoldeChefEquipeService
{
    public function __construct(
        private CamionsDatabaseResolver $databaseResolver,
    ) {}

    public function getSoldeByToken(string $token): ?array
    {
        $token = trim($token);
        if ($token === '') {
            return null;
        }

        if ($this->databaseResolver->usesApi()) {
            $solde = $this->fetchFromExternalApi($token);
            if ($solde === null) {
                return null;
            }

            return $this->enrichWithSousGroupeBreakdown($solde, $token);
        }

        if ($this->databaseResolver->connection() !== null) {
            $fromDb = $this->fetchFromDatabase($token);
            if ($fromDb !== null) {
                return $fromDb;
            }
        }

        if ($this->databaseResolver->usesDatabaseOnly()) {
            return null;
        }

        $solde = $this->fetchFromExternalApi($token);
        if ($solde === null) {
            return null;
        }

        return $this->enrichWithSousGroupeBreakdown($solde, $token);
    }

    public function getSoldeForContext(ChefEquipeContext $context, ?\Illuminate\Http\Request $request = null): ?array
    {
        $token = $context->resolveToken($request);
        if ($token === '') {
            return null;
        }

        return $this->getSoldeByToken($token);
    }

    private function fetchFromExternalApi(string $token): ?array
    {
        $url = (string) config('services.external_auth.solde_chef_equipe_url', '');
        if ($url === '') {
            return null;
        }

        try {
            $response = Http::acceptJson()
                ->withoutVerifying()
                ->timeout((int) config('services.external_auth.timeout', 10))
                ->get($url, ['token' => $token]);

            if (! $response->successful()) {
                return null;
            }

            $solde = $response->json('solde');
            if (! is_array($solde)) {
                return null;
            }

            return $this->normalizeSolde($solde);
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function fetchFromDatabase(string $token): ?array
    {
        $connection = $this->databaseResolver->connection();
        if ($connection === null) {
            return null;
        }

        return $this->querySoldeFromConnection($connection, $token);
    }

    /**
     * Complète particuliers / professionnels depuis la base Unipalm locale
     * (nécessaire quand le solde total vient de l'API distante).
     */
    private function enrichWithSousGroupeBreakdown(array $solde, string $token): array
    {
        $hasBreakdown = ((float) ($solde['reste_particuliers'] ?? 0)) !== 0.0
            || ((float) ($solde['reste_professionnels'] ?? 0)) !== 0.0;

        if ($hasBreakdown) {
            return $solde;
        }

        $connection = $this->databaseResolver->connectionForAuth();
        if ($connection === null) {
            return $solde;
        }

        $fromDb = $this->querySoldeFromConnection($connection, $token);
        if ($fromDb === null) {
            return $solde;
        }

        $apiReste = (float) ($solde['reste_a_payer'] ?? 0);
        $localReste = (float) ($fromDb['reste_a_payer'] ?? 0);
        $localPart = (float) ($fromDb['reste_particuliers'] ?? 0);
        $localProf = (float) ($fromDb['reste_professionnels'] ?? 0);

        // Même source / même montant : valeurs absolues.
        if ($localReste > 0 && abs($localReste - $apiReste) < 1) {
            $solde['reste_particuliers'] = $localPart;
            $solde['reste_professionnels'] = $localProf;

            return $solde;
        }

        // Sources différentes : ventilation au prorata pour coller au solde API.
        if ($localReste > 0 && $apiReste != 0.0) {
            $part = round($apiReste * ($localPart / $localReste), 2);
            $solde['reste_particuliers'] = $part;
            $solde['reste_professionnels'] = round($apiReste - $part, 2);

            return $solde;
        }

        $solde['reste_particuliers'] = $localPart;
        $solde['reste_professionnels'] = $localProf;

        return $solde;
    }

    private function querySoldeFromConnection(string $connection, string $token): ?array
    {
        try {
            $hasSousGroupe = Schema::connection($connection)->hasColumn('agents', 'sous_groupe');

            $sousGroupeSelect = $hasSousGroupe
                ? ",
                    COALESCE(SUM(CASE WHEN LOWER(TRIM(COALESCE(a.sous_groupe, ''))) IN ('particulier', 'particuliers') THEN t.montant_paie ELSE 0 END), 0)
                        - COALESCE(SUM(CASE WHEN LOWER(TRIM(COALESCE(a.sous_groupe, ''))) IN ('particulier', 'particuliers') THEN COALESCE(t.montant_payer, 0) ELSE 0 END), 0)
                        AS reste_particuliers,
                    COALESCE(SUM(CASE WHEN LOWER(TRIM(COALESCE(a.sous_groupe, ''))) IN ('professionnel', 'professionnels') THEN t.montant_paie ELSE 0 END), 0)
                        - COALESCE(SUM(CASE WHEN LOWER(TRIM(COALESCE(a.sous_groupe, ''))) IN ('professionnel', 'professionnels') THEN COALESCE(t.montant_payer, 0) ELSE 0 END), 0)
                        AS reste_professionnels"
                : ',
                    0 AS reste_particuliers,
                    0 AS reste_professionnels';

            $row = DB::connection($connection)->selectOne(
                'SELECT 
                    ce.id_chef,
                    ce.nom,
                    ce.prenoms,
                    ce.token,
                    COALESCE(SUM(t.montant_paie), 0) AS total_montant,
                    COALESCE(SUM(t.montant_payer), 0) AS montant_paye,
                    COALESCE(SUM(t.montant_paie), 0) - COALESCE(SUM(t.montant_payer), 0) AS reste_a_payer'
                .$sousGroupeSelect.'
                FROM chef_equipe ce
                LEFT JOIN agents a ON a.id_chef = ce.id_chef AND a.date_suppression IS NULL
                LEFT JOIN tickets t ON t.id_agent = a.id_agent AND t.montant_paie IS NOT NULL
                WHERE ce.token = ?
                GROUP BY ce.id_chef, ce.nom, ce.prenoms, ce.token',
                [$token]
            );

            if (! $row) {
                return null;
            }

            return $this->normalizeSolde((array) $row);
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function normalizeSolde(array $solde): array
    {
        return [
            'id_chef' => (int) ($solde['id_chef'] ?? 0),
            'nom' => (string) ($solde['nom'] ?? ''),
            'prenoms' => (string) ($solde['prenoms'] ?? ''),
            'token' => (string) ($solde['token'] ?? ''),
            'total_montant' => (float) ($solde['total_montant'] ?? 0),
            'montant_paye' => (float) ($solde['montant_paye'] ?? 0),
            'reste_a_payer' => (float) ($solde['reste_a_payer'] ?? 0),
            'reste_particuliers' => (float) ($solde['reste_particuliers'] ?? 0),
            'reste_professionnels' => (float) ($solde['reste_professionnels'] ?? 0),
        ];
    }
}
