<?php

namespace App\Http\Controllers;

use App\Models\PontEtat;
use App\Models\Stock;
use App\Models\Usine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class PontController extends Controller
{
    private function etatEffectifPont(int $idPont, ?array $pontApi = null): string
    {
        $etatLocal = PontEtat::query()->where('id_pont', $idPont)->value('etat');
        if ($etatLocal) {
            return $etatLocal;
        }

        if ($pontApi) {
            return PontEtat::etatDepuisApi($pontApi['statut'] ?? 'Actif');
        }

        return 'actif';
    }

    private function pontAccepteEntreesStock(int $idPont, ?array $pontApi = null): bool
    {
        return in_array($this->etatEffectifPont($idPont, $pontApi), ['actif', 'inactif'], true);
    }

    private function fetchPontFromApi(int $idPont): ?array
    {
        $mesPontsUrl = (string) config('services.external_auth.mes_ponts_url');
        $timeout = (int) config('services.external_auth.timeout', 10);

        try {
            $response = Http::acceptJson()
                ->withoutVerifying()
                ->timeout($timeout)
                ->get($mesPontsUrl);

            if ($response->successful()) {
                foreach ($response->json('ponts') ?? [] as $p) {
                    if ((int) ($p['id_pont'] ?? 0) === $idPont) {
                        return $p;
                    }
                }
            }
        } catch (\Throwable $e) {
            // Ignorer
        }

        return null;
    }

    public function toggleGerable(Request $request, int $id_pont)
    {
        $pontEtat = PontEtat::firstOrCreate(
            ['id_pont' => $id_pont],
            [
                'nom_pont' => $request->input('nom_pont'),
                'code_pont' => $request->input('code_pont'),
                'etat' => 'actif',
                'gerable' => false,
            ]
        );

        $pontEtat->update(['gerable' => !$pontEtat->gerable]);

        return back()->with('success', 'Statut gérable mis à jour.');
    }

    public function updatePontEtat(Request $request, int $id_pont)
    {
        $validated = $request->validate([
            'etat' => ['required', 'in:actif,inactif,ferme'],
            'nom_pont' => ['nullable', 'string', 'max:255'],
            'code_pont' => ['nullable', 'string', 'max:100'],
        ]);

        PontEtat::updateOrCreate(
            ['id_pont' => $id_pont],
            [
                'nom_pont' => $validated['nom_pont'] ?? null,
                'code_pont' => $validated['code_pont'] ?? null,
                'etat' => $validated['etat'],
            ]
        );

        return back()->with('success', 'Statut du pont mis à jour.');
    }

    public function index(Request $request)
    {
        $mesPontsUrl = (string) config('services.external_auth.mes_ponts_url');
        $timeout = (int) config('services.external_auth.timeout', 10);

        try {
            $response = Http::acceptJson()
                ->withoutVerifying()
                ->timeout($timeout)
                ->get($mesPontsUrl);
        } catch (\Throwable $e) {
            return view('ponts.index', [
                'ponts' => [],
                'external_error' => "Impossible de joindre le service ponts.",
                'search' => trim((string) $request->query('q', '')),
                'pontNoms' => [],
            ]);
        }

        if (!$response->successful()) {
            $message = (string) ($response->json('error') ?? 'Erreur API.');

            return view('ponts.index', [
                'ponts' => [],
                'external_error' => $message,
                'search' => trim((string) $request->query('q', '')),
                'pontNoms' => [],
            ]);
        }

        $ponts = $response->json('ponts');
        if (!is_array($ponts)) {
            $ponts = [];
        }

        $etatsParPont = PontEtat::query()->pluck('etat', 'id_pont')->toArray();
        $gerableParPont = PontEtat::query()->pluck('gerable', 'id_pont')->toArray();

        // Calculer le stock disponible et le solde pour chaque pont
        foreach ($ponts as &$pont) {
            $idPont = (int) ($pont['id_pont'] ?? 0);
            $etatPont = $etatsParPont[$idPont] ?? PontEtat::etatDepuisApi($pont['statut'] ?? 'Actif');
            $pont['etat_pont'] = $etatPont;
            $pont['gerable'] = (bool) ($gerableParPont[$idPont] ?? false);
            $pont['peut_entrer_stock'] = in_array($etatPont, ['actif', 'inactif'], true);
            
            // Trouver TOUS les stocks ouverts pour ce pont (un par parc)
            $stocksOuverts = Stock::where('id_pont', $idPont)
                ->where('type', 'entree')
                ->where('statut', 'ouvert')
                ->get();
            
            $totalStockDisponible = 0;
            
            foreach ($stocksOuverts as $stockOuvert) {
                $entrees = $stockOuvert->total_entrees;
                
                // Sorties liées à ce stock spécifique (via stock_id)
                $sorties = \App\Models\FicheSortie::where('stock_id', $stockOuvert->id)
                    ->whereNotNull('date_dechargement')
                    ->whereNotNull('poids_pont')
                    ->sum('poids_pont');
                
                $totalStockDisponible += max(0, $entrees - $sorties);
            }
            
            $pont['stock_disponible'] = $totalStockDisponible;

            // Calculer le solde (total approvisionnements - total dépenses stocks - total dépenses entrées - dépenses pont)
            $totalApprovisionnements = \App\Models\Approvisionnement::where('pont_id', $idPont)->sum('montant');
            $totalDepensesStocks = Stock::where('id_pont', $idPont)->where('type', 'entree')->sum('montant_total');
            $totalDepensesEntrees = \App\Models\EntreeStock::whereHas('stock', function($q) use ($idPont) {
                $q->where('id_pont', $idPont);
            })->sum('montant_total');
            $totalDepensesPont = \App\Models\DepensePont::where('id_pont', $idPont)->sum('montant');
            $pont['solde'] = $totalApprovisionnements - $totalDepensesStocks - $totalDepensesEntrees - $totalDepensesPont;
        }
        unset($pont);

        $pontNoms = collect($ponts)
            ->pluck('nom_pont')
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();

        $search = trim((string) $request->query('q', ''));
        if ($search !== '') {
            $needle = mb_strtolower($search);
            $ponts = array_values(array_filter($ponts, function ($pont) use ($needle) {
                $nomPont = mb_strtolower((string) ($pont['nom_pont'] ?? ''));

                return str_contains($nomPont, $needle);
            }));
        }

        return view('ponts.index', [
            'ponts' => $ponts,
            'external_error' => null,
            'search' => $search,
            'pontNoms' => $pontNoms,
        ]);
    }

    public function stock(Request $request, int $id_pont)
    {
        $mesPontsUrl = (string) config('services.external_auth.mes_ponts_url');
        $timeout = (int) config('services.external_auth.timeout', 10);

        // Récupérer les infos du pont
        $pont = null;
        try {
            $response = Http::acceptJson()
                ->withoutVerifying()
                ->timeout($timeout)
                ->get($mesPontsUrl);
            
            if ($response->successful()) {
                $ponts = $response->json('ponts') ?? [];
                foreach ($ponts as $p) {
                    if (($p['id_pont'] ?? 0) == $id_pont) {
                        $pont = $p;
                        break;
                    }
                }
            }
        } catch (\Throwable $e) {
            return view('ponts.stock', [
                'pont' => null,
                'stocks' => [],
                'external_error' => "Impossible de joindre le service ponts.",
            ]);
        }

        if (!$pont) {
            return redirect()->route('ponts.index')->withErrors(['error' => 'Pont non trouvé.']);
        }

        $etatPont = $this->etatEffectifPont($id_pont, $pont);
        $pont['etat_pont'] = $etatPont;
        $peutEntrerStock = $this->pontAccepteEntreesStock($id_pont, $pont);

        // Récupérer les stocks du pont depuis la base locale
        $stocks = Stock::where('id_pont', $id_pont)
            ->orderBy('date_mouvement', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        // Calculer les totaux
        $totalEntrees = Stock::where('id_pont', $id_pont)->where('type', 'entree')->sum('quantite');
        $totalSortiesManuelles = Stock::where('id_pont', $id_pont)->where('type', 'sortie')->sum('quantite');
        
        // Calculer les sorties réelles du pont (poids_pont des fiches de sortie déchargées)
        $totalSortiesFiches = \App\Models\FicheSortie::where('id_pont', $id_pont)
            ->whereNotNull('date_dechargement')
            ->whereNotNull('poids_pont')
            ->sum('poids_pont');
        
        // Total des sorties = sorties manuelles + sorties des fiches déchargées
        $totalSorties = $totalSortiesManuelles + $totalSortiesFiches;
        
        // Stock disponible = entrées - sorties totales
        $stockTotal = $totalEntrees;
        $stockDisponible = $totalEntrees - $totalSorties;
        
        $nbMouvements = $stocks->count();
        
        // Récupérer les fiches de sortie déchargées pour ce pont
        $fichesDechargees = \App\Models\FicheSortie::where('id_pont', $id_pont)
            ->whereNotNull('date_dechargement')
            ->whereNotNull('poids_pont')
            ->orderBy('date_dechargement', 'desc')
            ->get();

        // Résoudre les IDs numériques d'usine en noms
        $usinesById = [];
        foreach (Usine::all() as $ul) {
            $usinesById[(string) $ul->id_usine] = $ul->nom_usine;
        }
        try {
            $usinesUrl = (string) config('services.external_auth.mes_usines_url');
            $resp = Http::acceptJson()->withoutVerifying()->timeout($timeout)->get($usinesUrl);
            if ($resp->successful()) {
                foreach ($resp->json('usines') ?? [] as $u) {
                    $key = (string) ($u['id_usine'] ?? '');
                    if ($key !== '' && !isset($usinesById[$key])) {
                        $usinesById[$key] = $u['nom_usine'] ?? '';
                    }
                }
            }
        } catch (\Throwable $e) {}

        foreach ($fichesDechargees as $fiche) {
            if (is_numeric($fiche->usine) && isset($usinesById[(string) $fiche->usine])) {
                $fiche->usine = $usinesById[(string) $fiche->usine];
            }
        }

        // Calculer le solde (total approvisionnements - total dépenses stocks - total dépenses entrées - dépenses pont)
        $totalApprovisionnements = \App\Models\Approvisionnement::where('pont_id', $id_pont)->sum('montant');
        $totalDepensesStocks = Stock::where('id_pont', $id_pont)->where('type', 'entree')->sum('montant_total');
        $totalDepensesEntrees = \App\Models\EntreeStock::whereHas('stock', function($q) use ($id_pont) {
            $q->where('id_pont', $id_pont);
        })->sum('montant_total');
        $totalDepensesPont = \App\Models\DepensePont::where('id_pont', $id_pont)->sum('montant');
        $solde = $totalApprovisionnements - $totalDepensesStocks - $totalDepensesEntrees - $totalDepensesPont;

        // Récupérer les dépenses du pont
        $depensesPont = \App\Models\DepensePont::where('id_pont', $id_pont)
            ->orderBy('date_depense', 'desc')
            ->get();

        return view('ponts.stock', [
            'pont' => $pont,
            'etat_pont' => $etatPont,
            'peut_entrer_stock' => $peutEntrerStock,
            'stocks' => $stocks,
            'stockTotal' => $stockTotal,
            'totalSorties' => $totalSorties,
            'stockDisponible' => $stockDisponible,
            'fichesDechargees' => $fichesDechargees,
            'nbMouvements' => $nbMouvements,
            'solde' => $solde,
            'depensesPont' => $depensesPont,
            'totalDepensesPont' => $totalDepensesPont,
            'external_error' => null,
            'parcs' => \App\Models\Parc::where('id_pont', $id_pont)->where('statut', 'actif')->get(),
        ]);
    }

    public function storeStock(Request $request, int $id_pont)
    {
        $pontApi = $this->fetchPontFromApi($id_pont);
        if (!$this->pontAccepteEntreesStock($id_pont, $pontApi)) {
            return redirect()->route('ponts.stock', ['id_pont' => $id_pont])
                ->withErrors(['error' => 'Ce pont est fermé. Les entrées de stock ne sont pas autorisées.']);
        }

        $validated = $request->validate([
            'type' => ['required', 'in:entree,sortie'],
            'parc_id' => ['required', 'exists:parcs,id'],
            'produit_id' => ['required', 'exists:produits,id'],
            'quantite' => ['required', 'numeric', 'min:0'],
            'prix_unitaire' => ['nullable', 'numeric', 'min:0'],
            'date' => ['required', 'date'],
        ]);

        // Récupérer les infos du pont
        $mesPontsUrl = (string) config('services.external_auth.mes_ponts_url');
        $timeout = (int) config('services.external_auth.timeout', 10);
        $pont = null;

        try {
            $response = Http::acceptJson()->withoutVerifying()->timeout($timeout)->get($mesPontsUrl);
            if ($response->successful()) {
                $ponts = $response->json('ponts') ?? [];
                foreach ($ponts as $p) {
                    if (($p['id_pont'] ?? 0) == $id_pont) {
                        $pont = $p;
                        break;
                    }
                }
            }
        } catch (\Throwable $e) {}

        // Récupérer le parc
        $parc = \App\Models\Parc::find($validated['parc_id']);
        if (!$parc || $parc->id_pont != $id_pont) {
            return redirect()->route('ponts.stock', ['id_pont' => $id_pont])->withErrors(['error' => 'Parc invalide pour ce pont.']);
        }

        // Vérifier s'il y a déjà un stock ouvert pour ce parc spécifique
        $stockOuvertParc = Stock::where('id_pont', $id_pont)
            ->where('parc_id', $parc->id)
            ->where('type', 'entree')
            ->where('statut', 'ouvert')
            ->exists();
        
        if ($stockOuvertParc) {
            return redirect()->route('ponts.stock', ['id_pont' => $id_pont])->withErrors(['error' => 'Un stock est déjà ouvert pour le parc "' . $parc->nom . '". Fermez-le avant d\'en créer un nouveau.']);
        }

        // Calculer le montant total (prix_unitaire * quantité)
        $prixUnitaire = $validated['prix_unitaire'] ?? 0;
        $montantTotal = $prixUnitaire * $validated['quantite'];

        // Récupérer le produit
        $produit = \App\Models\Produit::find($validated['produit_id']);

        $codePont = $pont['code_pont'] ?? 'PONT';
        $codeStock = Stock::generateCodeStock($id_pont, $codePont);

        Stock::create([
            'id_pont' => $id_pont,
            'parc_id' => $parc->id,
            'nom_parc' => $parc->nom,
            'produit_id' => $validated['produit_id'],
            'nom_produit' => $produit ? $produit->nom : null,
            'code_pont' => $codePont,
            'nom_pont' => $pont['nom_pont'] ?? '',
            'type' => 'entree',
            'quantite' => $validated['quantite'],
            'prix_unitaire' => $prixUnitaire,
            'montant_total' => $montantTotal,
            'date_mouvement' => $validated['date'],
            'code_stock' => $codeStock,
            'statut' => 'ouvert',
            'etat' => 'actif',
        ]);

        return redirect()->route('ponts.stock', ['id_pont' => $id_pont])->with('success', 'Stock créé avec le code: ' . $codeStock);
    }

    public function toggleStockEtat(int $id_pont, int $stock_id)
    {
        $stock = Stock::where('id', $stock_id)
            ->where('id_pont', $id_pont)
            ->where('type', 'entree')
            ->first();

        if (!$stock) {
            return redirect()->route('ponts.stock', ['id_pont' => $id_pont])
                ->withErrors(['error' => 'Stock non trouvé.']);
        }

        if ($stock->isFerme()) {
            return redirect()->route('ponts.stock', ['id_pont' => $id_pont])
                ->withErrors(['error' => 'Impossible de modifier l\'état d\'un stock fermé.']);
        }

        $nouvelEtat = $stock->isActif() ? 'inactif' : 'actif';
        $stock->update(['etat' => $nouvelEtat]);

        $message = $nouvelEtat === 'actif'
            ? 'Stock activé.'
            : 'Stock désactivé. Les entrées sont suspendues.';

        return redirect()->route('ponts.stock', ['id_pont' => $id_pont])->with('success', $message);
    }

    public function fermerStock(Request $request, int $id_pont, int $stock_id)
    {
        $stock = Stock::where('id', $stock_id)->where('id_pont', $id_pont)->where('type', 'entree')->first();
        
        if (!$stock) {
            return redirect()->route('ponts.stock', ['id_pont' => $id_pont])->withErrors(['error' => 'Stock non trouvé.']);
        }

        if ($stock->isFerme()) {
            return redirect()->route('ponts.stock', ['id_pont' => $id_pont])->withErrors(['error' => 'Ce stock est déjà fermé.']);
        }

        $stock->update([
            'statut' => 'ferme',
            'date_fermeture' => now(),
        ]);

        return redirect()->route('ponts.stock', ['id_pont' => $id_pont])->with('success', 'Stock fermé avec succès.');
    }

    public function deleteStock(int $id_pont, int $stock_id)
    {
        $stock = Stock::where('id', $stock_id)->where('id_pont', $id_pont)->first();
        
        if ($stock) {
            // Détacher les fiches de sortie (stock_id nullable, conservées dans l'historique)
            \App\Models\FicheSortie::where('stock_id', $stock->id)->update(['stock_id' => null]);

            // Supprimer les entrées supplémentaires liées
            $stock->entreesStock()->delete();

            $stock->delete();
            return redirect()->route('ponts.stock', ['id_pont' => $id_pont])->with('success', 'Stock supprimé avec succès.');
        }

        return redirect()->route('ponts.stock', ['id_pont' => $id_pont])->withErrors(['error' => 'Stock non trouvé.']);
    }

    public function addEntreeStock(Request $request, int $id_pont, int $stock_id)
    {
        $pontApi = $this->fetchPontFromApi($id_pont);
        if (!$this->pontAccepteEntreesStock($id_pont, $pontApi)) {
            return redirect()->route('ponts.stock', ['id_pont' => $id_pont])
                ->withErrors(['error' => 'Ce pont est fermé. Les entrées de stock ne sont pas autorisées.']);
        }

        $validated = $request->validate([
            'quantite' => ['required', 'numeric', 'min:0'],
            'prix_unitaire' => ['nullable', 'numeric', 'min:0'],
            'date_entree' => ['required', 'date'],
            'commentaire' => ['nullable', 'string', 'max:500'],
        ]);

        $stock = Stock::where('id', $stock_id)->where('id_pont', $id_pont)->where('type', 'entree')->first();
        
        if (!$stock) {
            return redirect()->route('ponts.stock', ['id_pont' => $id_pont])->withErrors(['error' => 'Stock non trouvé.']);
        }

        if ($stock->isFerme()) {
            return redirect()->route('ponts.stock', ['id_pont' => $id_pont])->withErrors(['error' => 'Impossible d\'ajouter une entrée à un stock fermé.']);
        }

        if ($stock->isInactif()) {
            return redirect()->route('ponts.stock', ['id_pont' => $id_pont])
                ->withErrors(['error' => 'Ce stock est désactivé. Réactivez-le pour ajouter une entrée.']);
        }

        // Calculer le montant total
        $prixUnitaire = $validated['prix_unitaire'] ?? 0;
        $montantTotal = $prixUnitaire * $validated['quantite'];

        \App\Models\EntreeStock::create([
            'stock_id' => $stock->id,
            'quantite' => $validated['quantite'],
            'prix_unitaire' => $prixUnitaire,
            'montant_total' => $montantTotal,
            'date_entree' => $validated['date_entree'],
            'commentaire' => $validated['commentaire'] ?? null,
        ]);

        return redirect()->route('ponts.stock', ['id_pont' => $id_pont])->with('success', 'Entrée de stock ajoutée avec succès.');
    }

    public function updateEntreeStock(Request $request, int $id_pont, int $stock_id, int $entree_id)
    {
        $validated = $request->validate([
            'quantite' => ['required', 'numeric', 'min:0'],
            'date_entree' => ['required', 'date'],
            'commentaire' => ['nullable', 'string', 'max:500'],
        ]);

        $stock = Stock::where('id', $stock_id)->where('id_pont', $id_pont)->where('type', 'entree')->first();
        
        if (!$stock || $stock->isFerme()) {
            return redirect()->route('ponts.stock', ['id_pont' => $id_pont])->withErrors(['error' => 'Impossible de modifier cette entrée.']);
        }

        $entree = \App\Models\EntreeStock::where('id', $entree_id)->where('stock_id', $stock_id)->first();
        
        if (!$entree) {
            return redirect()->route('ponts.stock', ['id_pont' => $id_pont])->withErrors(['error' => 'Entrée non trouvée.']);
        }

        $entree->update([
            'quantite' => $validated['quantite'],
            'date_entree' => $validated['date_entree'],
            'commentaire' => $validated['commentaire'] ?? null,
        ]);

        return redirect()->route('ponts.stock', ['id_pont' => $id_pont])->with('success', 'Entrée de stock modifiée avec succès.');
    }

    public function deleteEntreeStock(int $id_pont, int $stock_id, int $entree_id)
    {
        $stock = Stock::where('id', $stock_id)->where('id_pont', $id_pont)->where('type', 'entree')->first();
        
        if (!$stock || $stock->isFerme()) {
            return redirect()->route('ponts.stock', ['id_pont' => $id_pont])->withErrors(['error' => 'Impossible de supprimer cette entrée.']);
        }

        $entree = \App\Models\EntreeStock::where('id', $entree_id)->where('stock_id', $stock_id)->first();
        
        if (!$entree) {
            return redirect()->route('ponts.stock', ['id_pont' => $id_pont])->withErrors(['error' => 'Entrée non trouvée.']);
        }

        $entree->delete();

        return redirect()->route('ponts.stock', ['id_pont' => $id_pont])->with('success', 'Entrée de stock supprimée avec succès.');
    }

    public function sorties(Request $request)
    {
        $mesPontsUrl = (string) config('services.external_auth.mes_ponts_url');
        $timeout = (int) config('services.external_auth.timeout', 10);

        // Récupérer les ponts
        $ponts = [];
        try {
            $response = Http::acceptJson()->withoutVerifying()->timeout($timeout)->get($mesPontsUrl);
            if ($response->successful()) {
                $ponts = $response->json('ponts') ?? [];
            }
        } catch (\Throwable $e) {}

        // Récupérer toutes les fiches de sortie déchargées (indépendamment du pont)
        $fichesDechargees = \App\Models\FicheSortie::whereNotNull('date_dechargement')
            ->whereNotNull('poids_pont')
            ->orderBy('date_dechargement', 'desc')
            ->get();

        // Grouper les sorties par pont pour le résumé
        $sortiesParPont = [];
        foreach ($fichesDechargees as $fiche) {
            $idPont = $fiche->id_pont;
            $nomPont = $fiche->nom_pont;

            if (!isset($sortiesParPont[$idPont])) {
                $sortiesParPont[$idPont] = [
                    'id_pont' => $idPont,
                    'nom_pont' => $nomPont,
                    'total_poids' => 0,
                    'nb_fiches' => 0,
                ];
            }

            $sortiesParPont[$idPont]['total_poids'] += (float) $fiche->poids_pont;
            $sortiesParPont[$idPont]['nb_fiches']++;
        }

        // Calculer le stock actuel pour chaque pont
        foreach ($sortiesParPont as &$sortie) {
            $idPont = $sortie['id_pont'];
            $totalEntrees = Stock::where('id_pont', $idPont)->where('type', 'entree')->sum('quantite');
            $totalSortiesManuelles = Stock::where('id_pont', $idPont)->where('type', 'sortie')->sum('quantite');
            $sortie['stock_initial'] = $totalEntrees - $totalSortiesManuelles;
            $sortie['stock_disponible'] = $sortie['stock_initial'] - $sortie['total_poids'];
        }
        unset($sortie);

        return view('ponts.sorties', [
            'sortiesParPont' => array_values($sortiesParPont),
            'fichesDechargees' => $fichesDechargees,
            'ponts' => $ponts,
            'external_error' => null,
        ]);
    }

    public function storeDepense(Request $request, int $id_pont)
    {
        $validated = $request->validate([
            'libelle' => ['required', 'string', 'max:255'],
            'montant' => ['required', 'numeric', 'min:0'],
            'date_depense' => ['required', 'date'],
            'categorie' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        // Récupérer les infos du pont
        $mesPontsUrl = (string) config('services.external_auth.mes_ponts_url');
        $timeout = (int) config('services.external_auth.timeout', 10);
        $pont = null;

        try {
            $response = Http::acceptJson()->withoutVerifying()->timeout($timeout)->get($mesPontsUrl);
            if ($response->successful()) {
                $ponts = $response->json('ponts') ?? [];
                foreach ($ponts as $p) {
                    if (($p['id_pont'] ?? 0) == $id_pont) {
                        $pont = $p;
                        break;
                    }
                }
            }
        } catch (\Throwable $e) {}

        \App\Models\DepensePont::create([
            'id_pont' => $id_pont,
            'nom_pont' => $pont['nom_pont'] ?? '',
            'code_pont' => $pont['code_pont'] ?? '',
            'libelle' => $validated['libelle'],
            'montant' => $validated['montant'],
            'date_depense' => $validated['date_depense'],
            'categorie' => $validated['categorie'] ?? null,
            'description' => $validated['description'] ?? null,
            'user_id' => auth()->id(),
        ]);

        return redirect()->route('ponts.stock', ['id_pont' => $id_pont])->with('success', 'Dépense ajoutée avec succès.');
    }

    public function destroyDepense(int $id_pont, int $depense_id)
    {
        $depense = \App\Models\DepensePont::where('id', $depense_id)->where('id_pont', $id_pont)->first();
        
        if ($depense) {
            $depense->delete();
            return redirect()->route('ponts.stock', ['id_pont' => $id_pont])->with('success', 'Dépense supprimée avec succès.');
        }

        return redirect()->route('ponts.stock', ['id_pont' => $id_pont])->withErrors(['error' => 'Dépense non trouvée.']);
    }
}
