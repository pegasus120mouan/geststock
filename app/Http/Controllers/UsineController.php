<?php

namespace App\Http\Controllers;

use App\Models\FicheSortie;
use App\Models\Produit;
use App\Models\Usine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

class UsineController extends Controller
{
    public function index(Request $request)
    {
        $mesUsinesUrl = (string) config('services.external_auth.mes_usines_url');
        $timeout = (int) config('services.external_auth.timeout', 10);

        $page = max(1, (int) $request->query('page', 1));
        $search = trim((string) $request->query('search', ''));
        $produitId = $request->filled('produit_id') ? (int) $request->query('produit_id') : null;
        $produits = Produit::query()->orderBy('nom')->get();
        $produitPalmier = $produits->first(fn ($produit) => mb_strtolower((string) $produit->nom, 'UTF-8') === 'palmier');
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

        $queryParams = ['page' => 1];
        if ($search !== '') {
            $queryParams['search'] = $search;
        }

        try {
            $response = Http::acceptJson()
                ->withoutVerifying()
                ->timeout($timeout)
                ->get($mesUsinesUrl, $queryParams);
        } catch (\Throwable $e) {
            return view('usines.index', [
                'usines' => [],
                'pagination' => null,
                'external_error' => "Impossible de joindre le service usines.",
                'produits' => $produits,
            ]);
        }

        if (!$response->successful()) {
            $message = (string) ($response->json('error') ?? 'Erreur API.');

            return view('usines.index', [
                'usines' => [],
                'pagination' => null,
                'external_error' => $message,
                'produits' => $produits,
            ]);
        }

        $usines = $response->json('usines');
        if (!is_array($usines)) {
            $usines = [];
        }

        $pagination = $response->json('pagination');
        $lastPage = (int) ($pagination['last_page'] ?? 1);

        for ($apiPage = 2; $apiPage <= $lastPage; $apiPage++) {
            $queryParams['page'] = $apiPage;

            try {
                $pageResponse = Http::acceptJson()
                    ->withoutVerifying()
                    ->timeout($timeout)
                    ->get($mesUsinesUrl, $queryParams);
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

        $usines = collect($usines)
            ->map(function ($usine) use ($usineProduits, $produitPalmier, $usinesLocales) {
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
            ->when($produitId, function ($collection) use ($produitId) {
                return $collection->filter(function ($usine) use ($produitId) {
                    return collect($usine['produits'] ?? [])->contains('id', $produitId);
                });
            })
            ->values()
            ->all();

        $produitsPourAffichage = $produitId
            ? $produits->where('id', $produitId)->values()
            : $produits;

        $usinesLocalesParProduit = Schema::hasColumn('usines', 'produit_id')
            ? Usine::query()
                ->whereNotNull('produit_id')
                ->orderBy('nom_usine')
                ->get()
                ->groupBy('produit_id')
            : collect();

        $usinesParProduit = $produitsPourAffichage
            ->map(function ($produit) use ($usines, $usinesLocalesParProduit) {
                $produitId = (int) $produit->id;

                $usinesApi = collect($usines)
                    ->filter(function ($usine) use ($produitId) {
                        return collect($usine['produits'] ?? [])->contains('id', $produitId);
                    })
                    ->values();

                $nomsApi = $usinesApi
                    ->map(fn ($usine) => mb_strtolower(trim((string) ($usine['nom_usine'] ?? '')), 'UTF-8'))
                    ->filter()
                    ->all();

                $usinesLocales = ($usinesLocalesParProduit->get($produitId) ?? collect())
                    ->filter(function (Usine $usine) use ($nomsApi) {
                        $key = mb_strtolower(trim((string) $usine->nom_usine), 'UTF-8');

                        return $key !== '' && !in_array($key, $nomsApi, true);
                    })
                    ->map(function (Usine $usine) use ($produit) {
                        return [
                            'id_usine' => (int) $usine->id_usine,
                            'nom_usine' => $usine->nom_usine,
                            'code_usine' => $usine->code_usine,
                            'source' => 'local',
                            'produits' => [[
                                'id' => (int) $produit->id,
                                'nom' => $produit->nom,
                            ]],
                        ];
                    });

                $usinesFusionnees = $usinesLocales
                    ->concat($usinesApi)
                    ->sortBy(fn ($usine) => mb_strtoupper((string) ($usine['nom_usine'] ?? ''), 'UTF-8'))
                    ->values()
                    ->all();

                return [
                    'id' => $produitId,
                    'nom' => $produit->nom,
                    'usines' => $usinesFusionnees,
                ];
            })
            ->values()
            ->all();

        $usinesNonClassees = collect($usines)
            ->filter(fn ($usine) => empty($usine['produits']))
            ->values()
            ->all();

        return view('usines.index', [
            'usines' => $usines,
            'usinesParProduit' => $usinesParProduit,
            'usinesNonClassees' => $usinesNonClassees,
            'pagination' => $pagination,
            'external_error' => null,
            'produits' => $produits,
        ]);
    }
}
