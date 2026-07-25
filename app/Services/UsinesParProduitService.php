<?php

namespace App\Services;

use App\Models\FicheSortie;
use App\Models\Produit;
use App\Models\Usine;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

class UsinesParProduitService
{
    /**
     * Liste pour les selects JS : produit_id => [{id_usine, nom, code, source}, ...]
     *
     * @return array<int|string, list<array{id_usine: int|string, nom: string, code: string, source: string}>>
     */
    public function usinesParProduitPourSelect(): array
    {
        $produits = Produit::orderBy('nom')->get();
        $apiUsines = $this->fetchApiUsinesEnrichies();

        $result = [];
        foreach ($produits as $produit) {
            $result[$produit->id] = $this->fusionnerUsinesPourProduit((int) $produit->id, $apiUsines);
        }

        return $result;
    }

    /**
     * @return list<array{id_usine: int|string, nom: string, code: string, source: string}>
     */
    public function usinesPourProduitId(int $produitId): array
    {
        return $this->fusionnerUsinesPourProduit($produitId, $this->fetchApiUsinesEnrichies());
    }

    public function usineAppartientAuProduit(int $produitId, int|string $idUsine, string $nomUsine): bool
    {
        $nom = trim($nomUsine);
        if ($nom === '') {
            return false;
        }

        foreach ($this->usinesPourProduitId($produitId) as $usine) {
            if (trim((string) $usine['nom']) !== $nom) {
                continue;
            }
            if ($idUsine === '' || $idUsine === 'all') {
                return true;
            }

            if ((string) $usine['id_usine'] === (string) $idUsine) {
                return true;
            }
        }

        return false;
    }

    /**
     * Produit associé à une usine (local, API enrichie, ou historique fiches).
     *
     * @return array{produit_id: int, nom: string}|null
     */
    public function produitPourUsine(?int $idUsine, ?string $nomUsine = null): ?array
    {
        if ($idUsine !== null && $idUsine > 0) {
            $locale = Usine::query()
                ->where('id_usine', $idUsine)
                ->with('produit')
                ->first();

            if ($locale?->produit_id && $locale->produit) {
                return [
                    'produit_id' => (int) $locale->produit_id,
                    'nom' => (string) $locale->produit->nom,
                ];
            }

            if (($nomUsine === null || trim($nomUsine) === '') && $locale) {
                $nomUsine = (string) $locale->nom_usine;
            }
        }

        $nom = trim((string) $nomUsine);
        $key = $nom !== '' ? mb_strtolower($nom, 'UTF-8') : '';

        if ($key !== '' && Schema::hasColumn('usines', 'produit_id')) {
            $localeParNom = Usine::query()
                ->whereRaw('LOWER(TRIM(nom_usine)) = ?', [$key])
                ->with('produit')
                ->first();

            if ($localeParNom?->produit_id && $localeParNom->produit) {
                return [
                    'produit_id' => (int) $localeParNom->produit_id,
                    'nom' => (string) $localeParNom->produit->nom,
                ];
            }
        }

        foreach ($this->fetchApiUsinesEnrichies() as $usine) {
            if (! is_array($usine)) {
                continue;
            }

            $match = false;
            if ($idUsine !== null && $idUsine > 0 && (int) ($usine['id_usine'] ?? 0) === $idUsine) {
                $match = true;
            } elseif ($key !== '' && mb_strtolower(trim((string) ($usine['nom_usine'] ?? '')), 'UTF-8') === $key) {
                $match = true;
            }

            if (! $match) {
                continue;
            }

            $produits = $usine['produits'] ?? [];
            if ($produits !== []) {
                $premier = $produits[0];

                return [
                    'produit_id' => (int) ($premier['id'] ?? 0),
                    'nom' => (string) ($premier['nom'] ?? ''),
                ];
            }
        }

        if ($key !== '') {
            $fiche = FicheSortie::query()
                ->whereNotNull('produit_id')
                ->whereRaw('LOWER(TRIM(usine)) = ?', [$key])
                ->orderByDesc('id')
                ->first(['produit_id', 'nom_produit']);

            if ($fiche && (int) $fiche->produit_id > 0) {
                return [
                    'produit_id' => (int) $fiche->produit_id,
                    'nom' => trim((string) ($fiche->nom_produit ?? '')) ?: (Produit::find($fiche->produit_id)?->nom ?? ''),
                ];
            }
        }

        return null;
    }

    /**
     * @param  list<array<string, mixed>>  $apiUsines
     * @return list<array{id_usine: int|string, nom: string, code: string, source: string}>
     */
    private function fusionnerUsinesPourProduit(int $produitId, array $apiUsines): array
    {
        $parNom = [];

        $usinesApi = collect($apiUsines)
            ->filter(fn ($usine) => collect($usine['produits'] ?? [])->contains('id', $produitId));

        foreach ($usinesApi as $usine) {
            $nom = trim((string) ($usine['nom_usine'] ?? ''));
            if ($nom === '') {
                continue;
            }
            $key = mb_strtolower($nom, 'UTF-8');
            $parNom[$key] = [
                'id_usine' => $usine['id_usine'] ?? 0,
                'nom' => $nom,
                'code' => (string) ($usine['code_usine'] ?? ''),
                'source' => 'api',
            ];
        }

        if (Schema::hasColumn('usines', 'produit_id')) {
            Usine::query()
                ->where('produit_id', $produitId)
                ->orderBy('nom_usine')
                ->get()
                ->each(function (Usine $usine) use (&$parNom) {
                    $nom = trim((string) $usine->nom_usine);
                    if ($nom === '') {
                        return;
                    }
                    $key = mb_strtolower($nom, 'UTF-8');
                    if (!isset($parNom[$key])) {
                        $parNom[$key] = [
                            'id_usine' => (int) $usine->id_usine,
                            'nom' => $nom,
                            'code' => (string) ($usine->code_usine ?? ''),
                            'source' => 'local',
                        ];
                    }
                });
        }

        $liste = array_values($parNom);
        usort($liste, fn ($a, $b) => strcasecmp($a['nom'], $b['nom']));

        return $liste;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function fetchApiUsinesEnrichiesForLookup(): array
    {
        return $this->fetchApiUsinesEnrichies();
    }

    /**
     * Usines API enrichies avec produits (même logique que la page /usines).
     *
     * @return list<array<string, mixed>>
     */
    private function fetchApiUsinesEnrichies(): array
    {
        return Cache::remember('usines_api_enrichies', 3600, fn () => $this->doFetchApiUsinesEnrichies());
    }

    private function doFetchApiUsinesEnrichies(): array
    {
        $mesUsinesUrl = (string) config('services.external_auth.mes_usines_url', 'https://api.objetombrepegasus.online/api/camions/mes_usines.php');
        $timeout = (int) config('services.external_auth.timeout', 10);

        $produits = Produit::query()->orderBy('nom')->get();
        $produitPalmier = $produits->first(
            fn ($produit) => mb_strtolower((string) $produit->nom, 'UTF-8') === 'palmier'
        );

        $usineProduits = FicheSortie::query()
            ->whereNotNull('usine')
            ->where('usine', '<>', '')
            ->whereNotNull('produit_id')
            ->select('usine', 'produit_id', 'nom_produit')
            ->distinct()
            ->get()
            ->groupBy(fn ($fiche) => mb_strtolower(trim((string) $fiche->usine), 'UTF-8'));

        $usinesLocales = Schema::hasColumn('usines', 'produit_id')
            ? Usine::query()
                ->whereNotNull('produit_id')
                ->with('produit')
                ->get()
                ->keyBy(fn ($usine) => mb_strtolower(trim((string) $usine->nom_usine), 'UTF-8'))
            : collect();

        try {
            $response = Http::acceptJson()
                ->withoutVerifying()
                ->timeout($timeout)
                ->get($mesUsinesUrl, ['page' => 1]);
        } catch (\Throwable $e) {
            return [];
        }

        if (!$response->successful()) {
            return [];
        }

        $usines = $response->json('usines');
        if (!is_array($usines)) {
            $usines = [];
        }

        $pagination = $response->json('pagination') ?? [];
        $lastPage = (int) ($pagination['last_page'] ?? 1);

        for ($apiPage = 2; $apiPage <= $lastPage; $apiPage++) {
            try {
                $pageResponse = Http::acceptJson()
                    ->withoutVerifying()
                    ->timeout($timeout)
                    ->get($mesUsinesUrl, ['page' => $apiPage]);
            } catch (\Throwable $e) {
                break;
            }

            if (!$pageResponse->successful()) {
                break;
            }

            $pageUsines = $pageResponse->json('usines');
            if (is_array($pageUsines)) {
                $usines = array_merge($usines, $pageUsines);
            }
        }

        return collect($usines)
            ->map(function ($usine) use ($usineProduits, $produitPalmier, $usinesLocales) {
                if (!is_array($usine)) {
                    return $usine;
                }

                $nomUsine = (string) ($usine['nom_usine'] ?? '');
                $key = mb_strtolower(trim($nomUsine), 'UTF-8');
                $usineLocale = $usinesLocales->get($key);

                $produitsUsine = $usineLocale && $usineLocale->produit
                    ? collect([[
                        'id' => (int) $usineLocale->produit->id,
                        'nom' => $usineLocale->produit->nom,
                    ]])
                    : ($produitPalmier
                        ? collect([[
                            'id' => (int) $produitPalmier->id,
                            'nom' => $produitPalmier->nom,
                        ]])
                        : $usineProduits->get($key, collect())
                            ->map(fn ($fiche) => [
                                'id' => (int) $fiche->produit_id,
                                'nom' => $fiche->nom_produit,
                            ]));

                $usine['produits'] = $produitsUsine
                    ->unique('id')
                    ->values()
                    ->all();

                return $usine;
            })
            ->values()
            ->all();
    }
}
