<?php

namespace App\Http\Controllers;

use App\Models\CamionEtat;
use App\Models\Depense;
use App\Models\FicheSortie;
use App\Models\PontEtat;
use App\Models\Stock;
use App\Models\Usine;
use App\Services\CamionsDatabaseResolver;
use App\Services\ChefEquipeContext;
use App\Services\FicheSortieNumeroService;
use App\Services\FicheSortieTicketCorrespondanceService;
use App\Services\MesAgentsService;
use App\Services\MesTicketsService;
use App\Services\MontantAgentFicheService;
use App\Services\UsinesParProduitService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

class DepenseController extends Controller
{
    private function vehiculeEstEnCoursUtilisation(int $vehiculeId): bool
    {
        return FicheSortie::query()
            ->where('vehicule_id', $vehiculeId)
            ->whereNull('date_dechargement')
            ->exists();
    }

    private function gerableParPontMap(): array
    {
        return PontEtat::query()->pluck('gerable', 'id_pont')->map(fn ($v) => (bool) $v)->toArray();
    }

    private function pontEstGerable(int $idPont): bool
    {
        return (bool) PontEtat::query()->where('id_pont', $idPont)->value('gerable');
    }

    private function annoterPontsGerable(array $ponts): array
    {
        $gerableParPont = $this->gerableParPontMap();

        return array_map(function ($pont) use ($gerableParPont) {
            $idPont = (int) ($pont['id_pont'] ?? 0);
            $pont['gerable'] = (bool) ($gerableParPont[$idPont] ?? false);

            return $pont;
        }, $ponts);
    }

    private function enrichirPontsAvecAgents(array $ponts): array
    {
        $connection = app(CamionsDatabaseResolver::class)->connectionForAuth();
        if ($connection === null || ! Schema::connection($connection)->hasTable('pont_bascule')) {
            return $ponts;
        }

        $pontRows = DB::connection($connection)
            ->table('pont_bascule')
            ->select(['id_pont', 'id_agent', 'gerant'])
            ->get()
            ->keyBy('id_pont');

        $agentIdsByName = $this->agentIdsByNameFromDatabase($connection);

        return array_map(function (array $pont) use ($pontRows, $agentIdsByName) {
            $idPont = (int) ($pont['id_pont'] ?? 0);
            if ($idPont <= 0) {
                return $pont;
            }

            $row = $pontRows->get($idPont);
            if ($row) {
                if (! empty($row->id_agent)) {
                    $pont['id_agent'] = (int) $row->id_agent;
                } elseif (empty($pont['gerant']) && ! empty($row->gerant)) {
                    $pont['gerant'] = $row->gerant;
                }
            }

            if (empty($pont['id_agent'])) {
                $gerant = $this->normalizePersonName($pont['gerant'] ?? ($row->gerant ?? ''));
                if ($gerant !== '' && isset($agentIdsByName[$gerant])) {
                    $pont['id_agent'] = $agentIdsByName[$gerant];
                }
            }

            return $pont;
        }, $ponts);
    }

    /**
     * @return array<string, int>
     */
    private function agentIdsByNameFromDatabase(string $connection): array
    {
        if (! Schema::connection($connection)->hasTable('agents')) {
            return [];
        }

        $map = [];
        $agents = DB::connection($connection)
            ->table('agents')
            ->select(['id_agent', 'nom', 'prenom'])
            ->get();

        foreach ($agents as $agent) {
            $id = (int) ($agent->id_agent ?? 0);
            if ($id <= 0) {
                continue;
            }

            $name = $this->normalizePersonName(trim(($agent->nom ?? '').' '.($agent->prenom ?? '')));
            if ($name !== '') {
                $map[$name] = $id;
            }
        }

        return $map;
    }

    /**
     * @return array<string, int>
     */
    private function agentIdsByNameFromList(array $agents): array
    {
        $map = [];

        foreach ($agents as $agent) {
            $id = (int) ($agent['id_agent'] ?? 0);
            if ($id <= 0) {
                continue;
            }

            $name = $this->normalizePersonName($agent['nom_complet'] ?? '');
            if ($name !== '') {
                $map[$name] = $id;
            }
        }

        return $map;
    }

    private function normalizePersonName(string $name): string
    {
        $name = trim(preg_replace('/\s+/', ' ', $name) ?? $name);

        return mb_strtolower($name, 'UTF-8');
    }

    /**
     * @return array<int, list<array{id: int, label: string, code: string, nom: string, gerable: bool}>>
     */
    private function buildAgentsPontsMap(array $ponts, array $agents): array
    {
        $ponts = $this->enrichirPontsAvecAgents($ponts);
        $agentIdsByName = $this->agentIdsByNameFromList($agents);

        $pontsById = [];
        foreach ($ponts as $pont) {
            $idPont = (int) ($pont['id_pont'] ?? 0);
            if ($idPont > 0) {
                $pontsById[$idPont] = $pont;
            }
        }

        $map = [];

        foreach ($ponts as $pont) {
            $idPont = (int) ($pont['id_pont'] ?? 0);
            if ($idPont <= 0) {
                continue;
            }

            $agentId = (int) ($pont['id_agent'] ?? 0);
            if ($agentId <= 0) {
                $gerant = $this->normalizePersonName($pont['gerant'] ?? '');
                $agentId = (int) ($agentIdsByName[$gerant] ?? 0);
            }

            if ($agentId <= 0) {
                continue;
            }

            $map[$agentId][] = $this->formatPontPourAgentMap($pont);
        }

        $map = $this->mergeAgentsPontsFromDatabase($map, $pontsById);

        foreach ($map as &$agentPonts) {
            $agentPonts = collect($agentPonts)
                ->unique('id')
                ->sortBy('nom', SORT_NATURAL | SORT_FLAG_CASE)
                ->values()
                ->all();
        }
        unset($agentPonts);

        return $map;
    }

    /**
     * @param  array<string, mixed>  $pont
     * @return array{id: int, label: string, code: string, nom: string, gerable: bool}
     */
    private function formatPontPourAgentMap(array $pont): array
    {
        $nom = trim((string) ($pont['nom_pont'] ?? ''));
        $code = trim((string) ($pont['code_pont'] ?? ''));
        $label = $code !== '' ? $nom.' ('.$code.')' : $nom;

        return [
            'id' => (int) ($pont['id_pont'] ?? 0),
            'label' => $label,
            'code' => $code,
            'nom' => $nom,
            'gerable' => ! empty($pont['gerable']),
        ];
    }

    /**
     * Complète la carte agent → ponts avec les associations id_agent en base Unipalm.
     *
     * @param  array<int, list<array{id: int, label: string, code: string, nom: string, gerable: bool}>>  $map
     * @param  array<int, array<string, mixed>>  $pontsById
     * @return array<int, list<array{id: int, label: string, code: string, nom: string, gerable: bool}>>
     */
    private function mergeAgentsPontsFromDatabase(array $map, array $pontsById): array
    {
        $connection = app(CamionsDatabaseResolver::class)->connectionForAuth();
        if ($connection === null || ! Schema::connection($connection)->hasTable('pont_bascule')) {
            return $map;
        }

        $gerableParPont = $this->gerableParPontMap();
        $agentIdsByName = $this->agentIdsByNameFromDatabase($connection);

        try {
            $rows = DB::connection($connection)
                ->table('pont_bascule')
                ->select(['id_pont', 'id_agent', 'nom_pont', 'code_pont', 'gerant'])
                ->get();
        } catch (\Throwable $e) {
            return $map;
        }

        foreach ($rows as $row) {
            $idPont = (int) ($row->id_pont ?? 0);
            if ($idPont <= 0) {
                continue;
            }

            $agentId = (int) ($row->id_agent ?? 0);
            if ($agentId <= 0) {
                $gerant = $this->normalizePersonName($row->gerant ?? '');
                $agentId = (int) ($agentIdsByName[$gerant] ?? 0);
            }

            if ($agentId <= 0) {
                continue;
            }

            $dejaPresent = collect($map[$agentId] ?? [])->contains(
                fn (array $pont): bool => (int) ($pont['id'] ?? 0) === $idPont
            );
            if ($dejaPresent) {
                continue;
            }

            $apiPont = $pontsById[$idPont] ?? [];
            $nom = trim((string) ($apiPont['nom_pont'] ?? $row->nom_pont ?? ''));
            $code = trim((string) ($apiPont['code_pont'] ?? $row->code_pont ?? ''));

            $map[$agentId][] = [
                'id' => $idPont,
                'label' => $code !== '' ? $nom.' ('.$code.')' : $nom,
                'code' => $code,
                'nom' => $nom,
                'gerable' => (bool) ($gerableParPont[$idPont] ?? ! empty($apiPont['gerable'])),
            ];
        }

        return $map;
    }

    private function calculerStockDisponible(Stock $stock, ?int $excludeFicheId = null): float
    {
        $entrees = (float) $stock->total_entrees;

        $query = FicheSortie::query()
            ->where('stock_id', $stock->id)
            ->whereNotNull('date_dechargement')
            ->whereNotNull('poids_pont');

        if ($excludeFicheId) {
            $query->where('id', '!=', $excludeFicheId);
        }

        $sorties = (float) $query->sum('poids_pont');

        return max(0, $entrees - $sorties);
    }

    private function resoudreStockPourDechargement(FicheSortie $fiche, int $parcId): ?Stock
    {
        if ($fiche->stock_id) {
            $stockLie = Stock::query()
                ->where('id', $fiche->stock_id)
                ->where('parc_id', $parcId)
                ->where('type', 'entree')
                ->where('statut', 'ouvert')
                ->first();

            if ($stockLie) {
                return $stockLie;
            }
        }

        $query = Stock::query()
            ->where('parc_id', $parcId)
            ->where('type', 'entree')
            ->where('statut', 'ouvert');

        if ($fiche->produit_id) {
            $stockProduit = (clone $query)->where('produit_id', $fiche->produit_id)->first();
            if ($stockProduit) {
                return $stockProduit;
            }
        }

        return $query->orderBy('id')->first();
    }

    private function queryStockOuvertPontProduit(int $idPont, int $produitId)
    {
        return Stock::query()
            ->where('id_pont', $idPont)
            ->where('produit_id', $produitId)
            ->where('type', 'entree')
            ->where('statut', 'ouvert')
            ->whereHas('parc', function ($query) use ($idPont) {
                $query->where('id_pont', $idPont)->where('statut', 'actif');
            });
    }

    private function stockEstActif(Stock $stock): bool
    {
        $etat = $stock->etat ?? 'actif';

        return $etat === 'actif';
    }

    /**
     * Stock ouvert et actif sur un parc actif du pont, pour le produit donné.
     */
    private function trouverStockActifPourPontEtProduit(int $idPont, int $produitId): ?Stock
    {
        $candidats = $this->queryStockOuvertPontProduit($idPont, $produitId)
            ->orderBy('id')
            ->get();

        return $candidats->first(fn (Stock $stock) => $this->stockEstActif($stock));
    }

    private function messageStockIndisponiblePourFiche(int $idPont, int $produitId): string
    {
        $stocksOuverts = $this->queryStockOuvertPontProduit($idPont, $produitId)->get();

        if ($stocksOuverts->isEmpty()) {
            return 'Aucun parc actif avec un stock ouvert pour ce produit sur ce pont.';
        }

        if ($stocksOuverts->every(fn (Stock $s) => !$this->stockEstActif($s))) {
            return 'Un stock existe pour ce produit mais il est désactivé. Activez-le depuis la gestion du stock du pont.';
        }

        return 'Aucun stock actif disponible pour ce produit sur ce pont.';
    }

    /**
     * Usines locales groupées par produit_id pour les listes déroulantes (fiche de sortie).
     *
     * @return array<int|string, list<array{nom: string, code: string}>>
     */
    private function usinesLocalesParProduitPourSelect(): array
    {
        if (!Schema::hasColumn('usines', 'produit_id')) {
            return [];
        }

        return Usine::query()
            ->whereNotNull('produit_id')
            ->orderBy('nom_usine')
            ->get()
            ->groupBy('produit_id')
            ->map(fn ($group) => $group->map(fn (Usine $u) => [
                'nom' => $u->nom_usine,
                'code' => $u->code_usine ?? '',
            ])->values()->all())
            ->all();
    }

    private function usineAppartientAuProduit(?string $nomUsine, int $produitId): bool
    {
        if ($nomUsine === null || $nomUsine === '') {
            return true;
        }

        return app(UsinesParProduitService::class)
            ->usineAppartientAuProduit($produitId, 'all', $nomUsine);
    }

    /**
     * @return array{produits: \Illuminate\Database\Eloquent\Collection, usinesParProduit: array, usinesFiltre: list<array{nom_usine: string}>}
     */
    private function chargerDonneesUsinesProduitsFiches(): array
    {
        $produits = \App\Models\Produit::orderBy('nom')->get();
        $usinesParProduit = app(UsinesParProduitService::class)->usinesParProduitPourSelect();
        $usinesFiltre = Schema::hasColumn('usines', 'produit_id')
            ? Usine::query()
                ->orderBy('nom_usine')
                ->get()
                ->map(fn (Usine $u) => ['nom_usine' => $u->nom_usine])
                ->all()
            : [];

        return [
            'produits' => $produits,
            'usinesParProduit' => $usinesParProduit,
            'usinesFiltre' => $usinesFiltre,
        ];
    }

    public function verifierStockPontProduit(Request $request)
    {
        $validated = $request->validate([
            'id_pont' => ['required', 'integer'],
            'produit_id' => ['required', 'integer', 'exists:produits,id'],
        ]);

        $idPont = (int) $validated['id_pont'];
        $produitId = (int) $validated['produit_id'];

        if (!$this->pontEstGerable($idPont)) {
            return response()->json([
                'valid' => true,
                'gerable' => false,
                'message' => 'Pont non gérable — aucun parc ni stock requis.',
            ]);
        }

        $stock = $this->trouverStockActifPourPontEtProduit($idPont, $produitId);

        if (!$stock) {
            return response()->json([
                'valid' => false,
                'gerable' => true,
                'message' => $this->messageStockIndisponiblePourFiche($idPont, $produitId),
            ]);
        }

        return response()->json([
            'valid' => true,
            'gerable' => true,
            'message' => sprintf(
                'Stock actif — Parc %s (%s)',
                $stock->nom_parc ?? '-',
                $stock->nom_produit ?? '-'
            ),
            'stock_id' => $stock->id,
            'parc_id' => $stock->parc_id,
            'nom_parc' => $stock->nom_parc,
            'nom_produit' => $stock->nom_produit,
        ]);
    }

    public function listeDepenses(Request $request)
    {
        $query = Depense::query();

        // Filtre par véhicule
        if ($request->filled('vehicule')) {
            $query->where('matricule_vehicule', $request->input('vehicule'));
        }

        // Filtre par service
        if ($request->filled('service')) {
            $query->where('type_depense', $request->input('service'));
        }

        // Filtre par fournisseur
        if ($request->filled('fournisseur')) {
            $query->where('description', $request->input('fournisseur'));
        }

        // Filtre par date début
        if ($request->filled('date_debut')) {
            $query->whereDate('date_depense', '>=', $request->input('date_debut'));
        }

        // Filtre par date fin
        if ($request->filled('date_fin')) {
            $query->whereDate('date_depense', '<=', $request->input('date_fin'));
        }

        $depenses = $query->orderBy('date_depense', 'desc')
            ->orderBy('id', 'desc')
            ->paginate(20)
            ->appends($request->query());

        // Récupérer les véhicules depuis l'API pour le formulaire d'ajout
        $vehicules = [];
        try {
            $response = Http::acceptJson()
                ->withoutVerifying()
                ->timeout(10)
                ->get('https://api.objetombrepegasus.online/api/camions/mes_camions.php');
            if ($response->successful()) {
                $vehicules = $response->json('vehicules') ?? [];
            }
        } catch (\Throwable $e) {}

        // Charger les services et fournisseurs
        $services = \App\Models\Service::orderBy('nom_service')->get();
        $fournisseurs = \App\Models\Fournisseur::with('service')->orderBy('nom')->get();

        return view('depenses.liste', [
            'depenses' => $depenses,
            'vehicules' => $vehicules,
            'services' => $services,
            'fournisseurs' => $fournisseurs,
            'external_error' => null,
        ]);
    }

    public function listeFichesSortie(Request $request)
    {
        $query = FicheSortie::query();

        // Filtre par véhicule
        if ($request->filled('vehicule')) {
            $query->where('matricule_vehicule', trim((string) $request->input('vehicule')));
        }

        // Filtre par pont
        if ($request->filled('pont')) {
            $query->where('nom_pont', $request->input('pont'));
        }

        // Filtre par produit
        if ($request->filled('produit_id')) {
            $produitId = (int) $request->input('produit_id');
            $nomProduit = \App\Models\Produit::find($produitId)?->nom;
            $query->where(function ($sub) use ($produitId, $nomProduit) {
                $sub->where('produit_id', $produitId);
                if ($nomProduit) {
                    $sub->orWhere('nom_produit', $nomProduit);
                }
            });
        }

        // Filtre par usine
        if ($request->filled('usine')) {
            $query->where('usine', $request->input('usine'));
        }

        // Filtre par chef chargeur
        if ($request->filled('chef_chargeur')) {
            $query->where('id_chef_chargeur', $request->input('chef_chargeur'));
        }

        // Filtre par date (chargement ou déchargement)
        $typeDate = $request->input('type_date', 'chargement');
        $dateColumn = $typeDate === 'dechargement' ? 'date_dechargement' : 'date_chargement';

        if ($request->filled('date_debut')) {
            $query->whereDate($dateColumn, '>=', $request->input('date_debut'));
        }

        if ($request->filled('date_fin')) {
            $query->whereDate($dateColumn, '<=', $request->input('date_fin'));
        }

        $fiches = $query->orderBy('date_chargement', 'desc')
            ->orderBy('id', 'desc')
            ->paginate(20)
            ->withQueryString();

        // Récupérer les véhicules, ponts et agents depuis l'API
        $mesCamionsUrl = (string) config('services.external_auth.mes_camions_url');
        $mesPontsUrl = (string) config('services.external_auth.mes_ponts_url');
        $mesAgentsUrl = (string) config('services.external_auth.mes_agents_url');
        $timeout = (int) config('services.external_auth.timeout', 10);
        $phpsessid = session('external_auth.phpsessid', '');

        $vehicules = [];
        $ponts = [];
        $agents = [];

        try {
            $camionsResponse = Http::acceptJson()
                ->withoutVerifying()
                ->timeout($timeout)
                ->withHeaders(['Cookie' => 'PHPSESSID=' . $phpsessid])
                ->get($mesCamionsUrl);
            if ($camionsResponse->successful()) {
                $vehicules = $camionsResponse->json('vehicules') ?? [];
            }
        } catch (\Throwable $e) {}

        try {
            $pontsResponse = Http::acceptJson()
                ->withoutVerifying()
                ->timeout($timeout)
                ->withHeaders(['Cookie' => 'PHPSESSID=' . $phpsessid])
                ->get($mesPontsUrl);
            if ($pontsResponse->successful()) {
                $ponts = $this->annoterPontsGerable($pontsResponse->json('ponts') ?? []);
            }
        } catch (\Throwable $e) {}

        try {
            $agents = app(MesAgentsService::class)->fetchAllAgents([], $request);
        } catch (\Throwable $e) {}

        $donneesUsinesProduits = $this->chargerDonneesUsinesProduitsFiches();

        // Récupérer les chefs des chargeurs
        $chefChargeurs = \App\Models\ChefChargeur::orderBy('nom')->get();

        $parcsParPont = \App\Models\Parc::where('statut', 'actif')
            ->get()
            ->groupBy('id_pont');

        // Statistiques des fiches de sortie
        $totalFiches = FicheSortie::count();
        $fichesEnAttente = FicheSortie::whereNull('date_dechargement')->count();
        $fichesDechargees = FicheSortie::whereNotNull('date_dechargement')->count();

        $matriculesFiltre = FicheSortie::query()
            ->distinct()
            ->orderBy('matricule_vehicule')
            ->pluck('matricule_vehicule')
            ->filter()
            ->values()
            ->all();
        $vehiculesFiltre = array_map(
            fn (string $matricule) => ['matricule_vehicule' => $matricule],
            $matriculesFiltre
        );

        $filtresActifs = $request->filled('vehicule')
            || $request->filled('pont')
            || $request->filled('produit_id')
            || $request->filled('usine')
            || $request->filled('chef_chargeur')
            || $request->filled('date_debut')
            || $request->filled('date_fin');

        return view('fiches_sortie.index', [
            'fiches' => $fiches,
            'vehicules' => $vehicules,
            'vehiculesFiltre' => $vehiculesFiltre,
            'filtresActifs' => $filtresActifs,
            'ponts' => $ponts,
            'agents' => $agents,
            'usines' => $donneesUsinesProduits['usinesFiltre'],
            'produits' => $donneesUsinesProduits['produits'],
            'usinesParProduit' => $donneesUsinesProduits['usinesParProduit'],
            'chefChargeurs' => $chefChargeurs,
            'totalFiches' => $totalFiches,
            'fichesEnAttente' => $fichesEnAttente,
            'fichesDechargees' => $fichesDechargees,
            'parcsParPont' => $parcsParPont,
            'gerableParPont' => $this->gerableParPontMap(),
            'external_error' => null,
        ]);
    }

    public function listeFichesNonDechargees(Request $request)
    {
        $query = FicheSortie::whereNull('date_dechargement');

        // Filtre par véhicule
        if ($request->filled('vehicule')) {
            $query->where('matricule_vehicule', $request->input('vehicule'));
        }

        // Filtre par pont
        if ($request->filled('pont')) {
            $query->where('nom_pont', $request->input('pont'));
        }

        // Filtre par usine
        if ($request->filled('usine')) {
            $query->where('usine', $request->input('usine'));
        }

        // Filtre par date de chargement
        if ($request->filled('date_debut')) {
            $query->whereDate('date_chargement', '>=', $request->input('date_debut'));
        }

        if ($request->filled('date_fin')) {
            $query->whereDate('date_chargement', '<=', $request->input('date_fin'));
        }

        $fiches = $query->orderBy('date_chargement', 'desc')
            ->orderBy('id', 'desc')
            ->paginate(20)
            ->withQueryString();

        // Récupérer les véhicules, ponts et agents depuis l'API
        $mesCamionsUrl = (string) config('services.external_auth.mes_camions_url');
        $mesPontsUrl = (string) config('services.external_auth.mes_ponts_url');
        $timeout = (int) config('services.external_auth.timeout', 10);
        $phpsessid = session('external_auth.phpsessid', '');

        $vehicules = [];
        $ponts = [];
        $usines = [];

        try {
            $camionsResponse = Http::acceptJson()
                ->withoutVerifying()
                ->timeout($timeout)
                ->withHeaders(['Cookie' => 'PHPSESSID=' . $phpsessid])
                ->get($mesCamionsUrl);
            if ($camionsResponse->successful()) {
                $vehicules = $camionsResponse->json('vehicules') ?? [];
            }
        } catch (\Throwable $e) {}

        try {
            $pontsResponse = Http::acceptJson()
                ->withoutVerifying()
                ->timeout($timeout)
                ->withHeaders(['Cookie' => 'PHPSESSID=' . $phpsessid])
                ->get($mesPontsUrl);
            if ($pontsResponse->successful()) {
                $ponts = $this->annoterPontsGerable($pontsResponse->json('ponts') ?? []);
            }
        } catch (\Throwable $e) {}

        $donneesUsinesProduits = $this->chargerDonneesUsinesProduitsFiches();

        // Récupérer tous les parcs actifs groupés par pont
        $parcsParPont = \App\Models\Parc::where('statut', 'actif')
            ->get()
            ->groupBy('id_pont');

        return view('fiches_sortie.non_dechargees', [
            'fiches' => $fiches,
            'vehicules' => $vehicules,
            'ponts' => $ponts,
            'usines' => $donneesUsinesProduits['usinesFiltre'],
            'parcsParPont' => $parcsParPont,
            'gerableParPont' => $this->gerableParPontMap(),
            'external_error' => null,
        ]);
    }

    public function listeFichesDechargees(Request $request)
    {
        $query = FicheSortie::whereNotNull('date_dechargement');

        // Filtre par véhicule
        if ($request->filled('vehicule')) {
            $query->where('matricule_vehicule', $request->input('vehicule'));
        }

        // Filtre par pont
        if ($request->filled('pont')) {
            $query->where('nom_pont', $request->input('pont'));
        }

        // Filtre par usine
        if ($request->filled('usine')) {
            $query->where('usine', $request->input('usine'));
        }

        // Filtre par date de déchargement
        if ($request->filled('date_debut')) {
            $query->whereDate('date_dechargement', '>=', $request->input('date_debut'));
        }

        if ($request->filled('date_fin')) {
            $query->whereDate('date_dechargement', '<=', $request->input('date_fin'));
        }

        $fiches = $query->orderBy('date_dechargement', 'desc')
            ->orderBy('id', 'desc')
            ->paginate(20)
            ->withQueryString();

        // Récupérer les véhicules, ponts depuis l'API
        $mesCamionsUrl = (string) config('services.external_auth.mes_camions_url');
        $mesPontsUrl = (string) config('services.external_auth.mes_ponts_url');
        $timeout = (int) config('services.external_auth.timeout', 10);
        $phpsessid = session('external_auth.phpsessid', '');

        $vehicules = [];
        $ponts = [];
        $usines = [];

        try {
            $camionsResponse = Http::acceptJson()
                ->withoutVerifying()
                ->timeout($timeout)
                ->withHeaders(['Cookie' => 'PHPSESSID=' . $phpsessid])
                ->get($mesCamionsUrl);
            if ($camionsResponse->successful()) {
                $vehicules = $camionsResponse->json('vehicules') ?? [];
            }
        } catch (\Throwable $e) {}

        try {
            $pontsResponse = Http::acceptJson()
                ->withoutVerifying()
                ->timeout($timeout)
                ->withHeaders(['Cookie' => 'PHPSESSID=' . $phpsessid])
                ->get($mesPontsUrl);
            if ($pontsResponse->successful()) {
                $ponts = $pontsResponse->json('ponts') ?? [];
            }
        } catch (\Throwable $e) {}

        $donneesUsinesProduits = $this->chargerDonneesUsinesProduitsFiches();

        return view('fiches_sortie.dechargees', [
            'fiches' => $fiches,
            'vehicules' => $vehicules,
            'ponts' => $ponts,
            'usines' => $donneesUsinesProduits['usinesFiltre'],
            'external_error' => null,
        ]);
    }

    public function index(Request $request, int $vehiculeId)
    {
        $matricule = (string) $request->query('matricule', '');
        $etatVehicule = CamionEtat::query()
            ->where('vehicule_id', $vehiculeId)
            ->value('etat');
        $vehiculeEnPanne = in_array($etatVehicule, ['en_panne', 'inactif'], true);
        $vehiculeEnCours = $this->vehiculeEstEnCoursUtilisation($vehiculeId);

        $depenses = Depense::where('vehicule_id', $vehiculeId)
            ->orderBy('date_depense', 'desc')
            ->orderBy('id', 'desc')
            ->paginate(20);

        $displayMatricule = $matricule;
        if (!$displayMatricule) {
            $existingDepense = Depense::where('vehicule_id', $vehiculeId)->first();
            $displayMatricule = $existingDepense?->matricule_vehicule ?: '';
        }

        // Charger les ponts et agents pour le modal fiche de sortie
        $timeout = 10;
        $ponts = [];
        $agentsPontsMap = [];
        $agents = [];

        try {
            $pontsResponse = Http::acceptJson()
                ->withoutVerifying()
                ->timeout($timeout)
                ->get('https://api.objetombrepegasus.online/api/camions/mes_ponts.php');
            if ($pontsResponse->successful()) {
                $ponts = $this->annoterPontsGerable($pontsResponse->json('ponts') ?? []);
            }
        } catch (\Throwable $e) {
            // Ignorer l'erreur
        }

        try {
            $agents = app(MesAgentsService::class)->fetchAllAgents([], $request);
        } catch (\Throwable $e) {
            // Ignorer l'erreur
        }

        $agentsPontsMap = $this->buildAgentsPontsMap($ponts, $agents);

        // Charger les chefs des chargeurs
        $chefChargeurs = \App\Models\ChefChargeur::orderBy('nom')->get();

        // Charger les services et fournisseurs
        $services = \App\Models\Service::orderBy('nom_service')->get();
        $fournisseurs = \App\Models\Fournisseur::with('service')->orderBy('nom')->get();

        // Charger les produits
        $produits = \App\Models\Produit::orderBy('nom')->get();
        $usinesParProduit = app(UsinesParProduitService::class)->usinesParProduitPourSelect();

        return view('depenses.index', [
            'depenses' => $depenses,
            'vehicule' => [
                'vehicules_id' => $vehiculeId,
                'matricule_vehicule' => $displayMatricule,
            ],
            'vehicule_id' => $vehiculeId,
            'ponts' => $ponts,
            'agentsPontsMap' => $agentsPontsMap,
            'agents' => $agents,
            'usinesParProduit' => $usinesParProduit,
            'chefChargeurs' => $chefChargeurs,
            'services' => $services,
            'fournisseurs' => $fournisseurs,
            'produits' => $produits,
            'vehicule_en_panne' => $vehiculeEnPanne,
            'vehicule_en_cours' => $vehiculeEnCours,
            'external_error' => null,
        ]);
    }

    public function store(Request $request, int $vehiculeId)
    {
        $validated = $request->validate([
            'type_depense' => ['required', 'string', 'max:100'],
            'matricule_vehicule' => ['required', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:500'],
            'commentaire' => ['nullable', 'string'],
            'montant' => ['required', 'numeric', 'min:0'],
            'date_depense' => ['required', 'date'],
        ]);

        Depense::create([
            'vehicule_id' => $vehiculeId,
            'matricule_vehicule' => $validated['matricule_vehicule'],
            'type_depense' => $validated['type_depense'],
            'description' => $validated['description'] ?? '',
            'commentaire' => $validated['commentaire'] ?? '',
            'montant' => $validated['montant'],
            'date_depense' => $validated['date_depense'],
        ]);

        return back()->with('success', 'Dépense enregistrée avec succès.');
    }

    public function update(Request $request, Depense $depense)
    {
        $validated = $request->validate([
            'type_depense' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'commentaire' => ['nullable', 'string'],
            'montant' => ['required', 'numeric', 'min:0'],
            'date_depense' => ['required', 'date'],
        ]);

        $depense->update([
            'type_depense' => $validated['type_depense'],
            'description' => $validated['description'] ?? '',
            'commentaire' => $validated['commentaire'] ?? '',
            'montant' => $validated['montant'],
            'date_depense' => $validated['date_depense'],
        ]);

        return back()->with('success', 'Dépense modifiée avec succès.');
    }

    public function destroy(Depense $depense)
    {
        $depense->delete();

        return back()->with('success', 'Dépense supprimée avec succès.');
    }

    public function ficheSortie(Request $request, int $vehiculeId)
    {
        $authUser = Auth::user();

        if (!$authUser || $authUser->role !== 'proprietaire') {
            return redirect()->route('vehicules.depenses', ['vehicule_id' => $vehiculeId])
                ->withErrors(['error' => "Accès réservé aux propriétaires."]);
        }

        $etatVehicule = CamionEtat::query()
            ->where('vehicule_id', $vehiculeId)
            ->value('etat');
        if (in_array($etatVehicule, ['en_panne', 'inactif'], true)) {
            return redirect()->route('vehicules.depenses', ['vehicule_id' => $vehiculeId])
                ->withErrors(['error' => "Ce camion est en panne. Impossible de créer une fiche de sortie."]);
        }

        if ($this->vehiculeEstEnCoursUtilisation($vehiculeId)) {
            return redirect()->route('vehicules.depenses', ['vehicule_id' => $vehiculeId])
                ->withErrors(['error' => "Ce camion est en cours d'utilisation. Impossible de créer une fiche de sortie."]);
        }

        $depenses = Depense::where('vehicule_id', $vehiculeId)
            ->orderBy('date_depense', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        $matricule = '';
        $existingDepense = Depense::where('vehicule_id', $vehiculeId)->first();
        if ($existingDepense) {
            $matricule = $existingDepense->matricule_vehicule;
        }

        $totalDepenses = $depenses->sum('montant');

        // Récupérer les infos du pont et de l'agent sélectionnés
        $timeout = (int) config('services.external_auth.timeout', 10);
        $pont = null;
        $agent = null;

        $idPont = (int) $request->query('id_pont', 0);
        $idAgent = (int) $request->query('id_agent', 0);

        if ($idPont > 0) {
            try {
                $pontsResponse = Http::acceptJson()
                    ->withoutVerifying()
                    ->timeout($timeout)
                    ->get(config('services.external_auth.mes_ponts_url'));
                if ($pontsResponse->successful()) {
                    $ponts = $pontsResponse->json('ponts') ?? [];
                    foreach ($ponts as $p) {
                        if ((int)$p['id_pont'] === $idPont) {
                            $pont = $p;
                            break;
                        }
                    }
                }
            } catch (\Throwable $e) {
                // Ignorer
            }
        }

        if ($idAgent > 0) {
            $agent = app(MesAgentsService::class)->findAgentById($idAgent);
        }

        // Récupérer les tickets du véhicule pour le modal d'association
        $tickets = [];
        $ficheSortie = $request->query('fiche_id') ? FicheSortie::find($request->query('fiche_id')) : null;
        
        // Utiliser le matricule de la fiche de sortie si disponible
        if ($ficheSortie && $ficheSortie->matricule_vehicule) {
            $matricule = $ficheSortie->matricule_vehicule;
        }
        
        if ($ficheSortie && !$ficheSortie->id_ticket) {
            try {
                $mesTicketsService = app(MesTicketsService::class);
                $correspondance = app(FicheSortieTicketCorrespondanceService::class);
                $allTickets = $mesTicketsService->fetchAllTickets([], $request);
                $tickets = array_values(array_filter(
                    $allTickets,
                    static fn (array $t) => $correspondance->correspond(
                        $correspondance->ticketDepuisApi($t),
                        $ficheSortie
                    )
                ));
            } catch (\Throwable $e) {
                // Ignorer
            }
        }

        return view('depenses.fiche_sortie', [
            'depenses' => $depenses,
            'vehicule' => [
                'vehicules_id' => $vehiculeId,
                'matricule_vehicule' => $matricule,
            ],
            'vehicule_id' => $vehiculeId,
            'total_depenses' => $totalDepenses,
            'pont' => $pont,
            'agent' => $agent,
            'fiche_sortie' => $ficheSortie,
            'tickets' => $tickets,
        ]);
    }

    public function storeFicheSortie(Request $request, int $vehiculeId)
    {
        $etatVehicule = CamionEtat::query()
            ->where('vehicule_id', $vehiculeId)
            ->value('etat');
        if (in_array($etatVehicule, ['en_panne', 'inactif'], true)) {
            return response()->json([
                'success' => false,
                'message' => "Ce camion est en panne. Impossible de créer une fiche de sortie.",
            ], 422);
        }

        if ($this->vehiculeEstEnCoursUtilisation($vehiculeId)) {
            return response()->json([
                'success' => false,
                'message' => "Ce camion est en cours d'utilisation. Impossible de créer une fiche de sortie.",
            ], 422);
        }

        $validated = $request->validate([
            'id_pont' => ['required', 'integer'],
            'id_agent' => ['required', 'integer'],
            'date_chargement' => ['required', 'date'],
            'poids_pont' => ['nullable', 'numeric', 'min:0'],
            'usine' => ['nullable', 'string', 'max:255'],
            'produit_id' => ['required', 'integer', 'exists:produits,id'],
            'id_chef_chargeur' => ['nullable', 'integer'],
            'carburant' => ['nullable', 'integer', 'min:0'],
            'frais_route' => ['nullable', 'integer', 'min:0'],
            'pont_display' => ['nullable', 'string'],
            'agent_display' => ['nullable', 'string'],
            'matricule_vehicule' => ['required', 'string', 'max:50'],
        ]);

        $stockActif = null;
        if ($this->pontEstGerable((int) $validated['id_pont'])) {
            $stockActif = $this->trouverStockActifPourPontEtProduit(
                (int) $validated['id_pont'],
                (int) $validated['produit_id']
            );

            if (!$stockActif) {
                $message = $this->messageStockIndisponiblePourFiche(
                    (int) $validated['id_pont'],
                    (int) $validated['produit_id']
                );
                if ($request->ajax() || $request->wantsJson()) {
                    return response()->json(['success' => false, 'message' => $message], 422);
                }

                return back()->withErrors(['error' => $message]);
            }
        }

        if (!$this->usineAppartientAuProduit($validated['usine'] ?? null, (int) $validated['produit_id'])) {
            $message = "L'usine sélectionnée n'est pas associée à ce produit.";
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $message], 422);
            }

            return back()->withErrors(['usine' => $message]);
        }

        // Utiliser le matricule du formulaire
        $matricule = $validated['matricule_vehicule'];

        // Parser les infos du pont et de l'agent depuis le display
        $pontDisplay = $validated['pont_display'] ?? '';
        $agentDisplay = $validated['agent_display'] ?? '';

        // Extraire nom_pont et code_pont depuis "Nom Pont (CODE)"
        $nomPont = '';
        $codePont = '';
        if (preg_match('/^(.+)\s+\(([^)]+)\)$/', $pontDisplay, $matches)) {
            $nomPont = trim($matches[1]);
            $codePont = trim($matches[2]);
        }

        if ($nomPont === '') {
            $nomPont = PontEtat::query()
                ->where('id_pont', (int) $validated['id_pont'])
                ->value('nom_pont') ?? '';
        }

        // Extraire nom_agent et numero_agent depuis "Nom Agent (NUMERO)"
        $nomAgent = '';
        $numeroAgent = '';
        if (preg_match('/^(.+)\s+\(([^)]+)\)$/', $agentDisplay, $matches)) {
            $nomAgent = trim($matches[1]);
            $numeroAgent = trim($matches[2]);
        }

        // Récupérer le nom du produit si un produit est sélectionné
        $nomProduit = null;
        if (!empty($validated['produit_id'])) {
            $produit = \App\Models\Produit::find($validated['produit_id']);
            $nomProduit = $produit ? $produit->nom : null;
        }

        $numeroFiche = app(FicheSortieNumeroService::class)->generer($nomPont, (int) $validated['id_pont']);

        $ficheSortie = \App\Models\FicheSortie::create([
            'numero_fiche' => $numeroFiche,
            'vehicule_id' => $vehiculeId,
            'matricule_vehicule' => $matricule,
            'stock_id' => $stockActif?->id,
            'parc_id' => $stockActif?->parc_id,
            'nom_parc' => $stockActif?->nom_parc,
            'id_pont' => $validated['id_pont'],
            'nom_pont' => $nomPont,
            'code_pont' => $codePont,
            'usine' => $validated['usine'] ?? null,
            'produit_id' => $validated['produit_id'],
            'nom_produit' => $nomProduit ?? $stockActif?->nom_produit,
            'id_agent' => $validated['id_agent'],
            'nom_agent' => $nomAgent,
            'numero_agent' => $numeroAgent,
            'id_chef_chargeur' => $validated['id_chef_chargeur'] ?? null,
            'date_chargement' => $validated['date_chargement'],
            'poids_pont' => $validated['poids_pont'] ?? null,
            'carburant' => $validated['carburant'] ?? null,
            'frais_route' => $validated['frais_route'] ?? null,
        ]);

        // Réponse JSON pour les requêtes AJAX
        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Fiche de sortie créée avec succès.',
                'fiche_id' => $ficheSortie->id,
                'numero_fiche' => $numeroFiche,
            ]);
        }

        return redirect()->route('vehicules.fiche_sortie', [
            'vehicule_id' => $vehiculeId,
            'fiche_id' => $ficheSortie->id,
            'id_pont' => $validated['id_pont'],
            'id_agent' => $validated['id_agent'],
            'date_chargement' => $validated['date_chargement'],
            'poids_pont' => $validated['poids_pont'] ?? 0,
        ])->with('success', 'Fiche de sortie enregistrée avec succès.');
    }

    public function associerTicket(Request $request, int $ficheId)
    {
        $ficheSortie = FicheSortie::findOrFail($ficheId);

        $validated = $request->validate([
            'id_ticket' => ['required', 'integer'],
            'numero_ticket' => ['required', 'string', 'max:100'],
        ]);

        // Extraire le numero_ticket depuis le format "NUMERO - MATRICULE"
        $numeroTicket = $validated['numero_ticket'];
        if (str_contains($numeroTicket, ' - ')) {
            $parts = explode(' - ', $numeroTicket);
            $numeroTicket = trim($parts[0]);
        }

        $apiTicket = app(MesTicketsService::class)->findTicketById((int) $validated['id_ticket'], $request);
        if ($apiTicket) {
            $raison = app(FicheSortieTicketCorrespondanceService::class)->raisonNonCorrespondance(
                app(FicheSortieTicketCorrespondanceService::class)->ticketDepuisApi($apiTicket),
                $ficheSortie
            );
            if ($raison !== null) {
                return redirect()->route('fiches_sortie.show', ['fiche_id' => $ficheSortie->id])
                    ->with('error', $raison);
            }
        }

        $ficheSortie->update([
            'id_ticket' => $validated['id_ticket'],
            'numero_ticket' => $numeroTicket,
        ]);

        return redirect()->route('fiches_sortie.show', ['fiche_id' => $ficheSortie->id])
            ->with('success', 'Ticket associé avec succès.');
    }

    public function updatePrixTransport(Request $request, int $ficheId)
    {
        $authUser = Auth::user();

        if (!$authUser || $authUser->role !== 'proprietaire') {
            return back()->withErrors(['error' => "Accès réservé aux propriétaires."]);
        }

        $ficheSortie = FicheSortie::findOrFail($ficheId);

        $validated = $request->validate([
            'prix_unitaire_transport' => ['nullable', 'numeric', 'min:0'],
            'poids_unitaire_regime' => ['nullable', 'numeric', 'min:0'],
        ]);

        $ficheSortie->update([
            'prix_unitaire_transport' => $validated['prix_unitaire_transport'],
            'poids_unitaire_regime' => $validated['poids_unitaire_regime'],
        ]);

        return redirect()->route('tickets.index')->with('success', 'Valeurs mises à jour.');
    }

    public function storeFicheSortieFromList(Request $request)
    {
        $authUser = Auth::user();

        if (!$authUser || $authUser->role !== 'proprietaire') {
            return back()->withErrors(['error' => "Accès réservé aux propriétaires."]);
        }

        $validated = $request->validate([
            'vehicule_id' => ['required', 'integer'],
            'matricule_vehicule' => ['nullable', 'string', 'max:50'],
            'id_pont' => ['required', 'integer'],
            'id_agent' => ['required', 'integer'],
            'date_chargement' => ['required', 'date'],
            'date_dechargement' => ['nullable', 'date'],
            'poids_pont' => ['nullable', 'numeric', 'min:0'],
            'usine' => ['nullable', 'string', 'max:255'],
            'produit_id' => ['required', 'integer', 'exists:produits,id'],
            'pont_display' => ['nullable', 'string'],
            'agent_display' => ['nullable', 'string'],
        ]);

        if (!$this->usineAppartientAuProduit($validated['usine'] ?? null, (int) $validated['produit_id'])) {
            return back()->withErrors(['usine' => "L'usine sélectionnée n'est pas associée à ce produit."])->withInput();
        }

        $etatVehicule = CamionEtat::query()
            ->where('vehicule_id', $validated['vehicule_id'])
            ->value('etat');
        if (in_array($etatVehicule, ['en_panne', 'inactif'], true)) {
            return back()->withErrors(['error' => "Ce camion est en panne. Impossible de créer une fiche de sortie."]);
        }

        if ($this->vehiculeEstEnCoursUtilisation((int) $validated['vehicule_id'])) {
            return back()->withErrors(['error' => "Ce camion est en cours d'utilisation. Impossible de créer une fiche de sortie."]);
        }

        $produit = \App\Models\Produit::find($validated['produit_id']);
        $nomProduit = $produit ? $produit->nom : null;

        // Si matricule_vehicule est vide, récupérer depuis l'API
        $matricule = $validated['matricule_vehicule'] ?? '';
        if (empty($matricule)) {
            $mesCamionsUrl = (string) config('services.external_auth.mes_camions_url');
            $timeout = (int) config('services.external_auth.timeout', 10);
            $phpsessid = session('external_auth.phpsessid', '');
            try {
                $response = Http::acceptJson()
                    ->withoutVerifying()
                    ->timeout($timeout)
                    ->withHeaders(['Cookie' => 'PHPSESSID=' . $phpsessid])
                    ->get($mesCamionsUrl);
                if ($response->successful()) {
                    $vehicules = $response->json('vehicules') ?? [];
                    foreach ($vehicules as $v) {
                        if (($v['id_vehicule'] ?? 0) == $validated['vehicule_id']) {
                            $matricule = $v['matricule_vehicule'] ?? '';
                            break;
                        }
                    }
                }
            } catch (\Throwable $e) {}
        }

        // Parser les infos du pont et de l'agent depuis le display
        $pontDisplay = $validated['pont_display'] ?? '';
        $agentDisplay = $validated['agent_display'] ?? '';

        // Extraire nom_pont et code_pont depuis "Nom Pont (CODE)"
        $nomPont = '';
        $codePont = '';
        if (preg_match('/^(.+)\s+\(([^)]+)\)$/', $pontDisplay, $matches)) {
            $nomPont = trim($matches[1]);
            $codePont = trim($matches[2]);
        }

        // Si pont_display est vide, récupérer depuis l'API
        if (empty($nomPont)) {
            $mesPontsUrl = (string) config('services.external_auth.mes_ponts_url');
            $timeout = (int) config('services.external_auth.timeout', 10);
            $phpsessid = session('external_auth.phpsessid', '');
            try {
                $pontsResponse = Http::acceptJson()
                    ->withoutVerifying()
                    ->timeout($timeout)
                    ->withHeaders(['Cookie' => 'PHPSESSID=' . $phpsessid])
                    ->get($mesPontsUrl);
                if ($pontsResponse->successful()) {
                    $ponts = $pontsResponse->json('ponts') ?? [];
                    foreach ($ponts as $p) {
                        if (($p['id_pont'] ?? 0) == $validated['id_pont']) {
                            $nomPont = $p['nom_pont'] ?? '';
                            $codePont = $p['code_pont'] ?? '';
                            break;
                        }
                    }
                }
            } catch (\Throwable $e) {}
        }

        if (empty($nomPont)) {
            $nomPont = PontEtat::query()
                ->where('id_pont', (int) $validated['id_pont'])
                ->value('nom_pont') ?? '';
        }

        // Extraire nom_agent et numero_agent depuis "Nom Agent (NUMERO)"
        $nomAgent = '';
        $numeroAgent = '';
        if (preg_match('/^(.+)\s+\(([^)]+)\)$/', $agentDisplay, $matches)) {
            $nomAgent = trim($matches[1]);
            $numeroAgent = trim($matches[2]);
        }

        // Si agent_display est vide, récupérer depuis l'API
        if (empty($nomAgent)) {
            $apiAgent = app(MesAgentsService::class)->findAgentById((int) $validated['id_agent']);
            if ($apiAgent) {
                $nomAgent = $apiAgent['nom_complet'] ?? '';
                $numeroAgent = $apiAgent['numero_agent'] ?? '';
            }
        }

        $numeroFiche = app(FicheSortieNumeroService::class)->generer($nomPont, (int) $validated['id_pont']);

        FicheSortie::create([
            'numero_fiche' => $numeroFiche,
            'vehicule_id' => $validated['vehicule_id'],
            'matricule_vehicule' => $matricule,
            'id_pont' => $validated['id_pont'],
            'nom_pont' => $nomPont,
            'code_pont' => $codePont,
            'usine' => $validated['usine'] ?? null,
            'produit_id' => $validated['produit_id'],
            'nom_produit' => $nomProduit,
            'id_agent' => $validated['id_agent'],
            'nom_agent' => $nomAgent,
            'numero_agent' => $numeroAgent,
            'date_chargement' => $validated['date_chargement'],
            'date_dechargement' => $validated['date_dechargement'] ?? null,
            'poids_pont' => $validated['poids_pont'] ?? null,
            'id_ticket' => null,
            'numero_ticket' => null,
            'prix_unitaire_transport' => 0,
            'poids_unitaire_regime' => 0,
        ]);

        return redirect()->route('fiches_sortie.index')->with('success', 'Fiche de sortie ' . $numeroFiche . ' créée avec succès.');
    }

    public function storeFromList(Request $request)
    {
        $validated = $request->validate([
            'vehicule_id' => ['required', 'integer'],
            'matricule_vehicule' => ['required', 'string', 'max:50'],
            'type_depense' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'commentaire' => ['nullable', 'string'],
            'montant' => ['required', 'numeric', 'min:0'],
            'date_depense' => ['required', 'date'],
        ]);

        Depense::create([
            'vehicule_id' => $validated['vehicule_id'],
            'matricule_vehicule' => $validated['matricule_vehicule'],
            'type_depense' => $validated['type_depense'],
            'description' => $validated['description'] ?? '',
            'commentaire' => $validated['commentaire'] ?? '',
            'montant' => $validated['montant'],
            'date_depense' => $validated['date_depense'],
        ]);

        return redirect()->route('depenses.liste')->with('success', 'Dépense enregistrée avec succès.');
    }

    public function showFicheSortie(int $ficheId)
    {
        $ficheSortie = FicheSortie::findOrFail($ficheId);

        // Récupérer le chef des chargeurs si assigné
        $chefChargeur = null;
        $paiementChargeur = null;
        $prixUnitaireChargeur = null;

        if ($ficheSortie->id_chef_chargeur) {
            $chefChargeur = \App\Models\ChefChargeur::find($ficheSortie->id_chef_chargeur);

            // Calculer le paiement chargeur si on a le poids et la date de chargement
            if ($chefChargeur && $ficheSortie->poids_pont && $ficheSortie->date_chargement) {
                // Trouver le prix unitaire applicable à la date de chargement
                $prixPeriode = \App\Models\ChefChargeurPrix::where('id_chef_chargeur', $chefChargeur->id)
                    ->where('date_debut', '<=', $ficheSortie->date_chargement)
                    ->where(function ($query) use ($ficheSortie) {
                        $query->whereNull('date_fin')
                              ->orWhere('date_fin', '>=', $ficheSortie->date_chargement);
                    })
                    ->first();

                if ($prixPeriode) {
                    $prixUnitaireChargeur = $prixPeriode->prix_unitaire;
                    // Convertir kg en tonnes (diviser par 1000)
                    $poidsEnTonnes = (float) $ficheSortie->poids_pont / 1000;
                    $paiementChargeur = $prixUnitaireChargeur * $poidsEnTonnes;
                }
            }
        }

        $chauffeur = \App\Models\Chauffeur::findForCamionAtDate(
            $ficheSortie->matricule_vehicule,
            $ficheSortie->vehicule_id ? (int) $ficheSortie->vehicule_id : null,
            $ficheSortie->date_chargement ?? $ficheSortie->created_at
        );

        $parcsParPont = \App\Models\Parc::where('statut', 'actif')
            ->get()
            ->groupBy('id_pont');

        return view('fiches_sortie.show', [
            'fiche' => $ficheSortie,
            'chefChargeur' => $chefChargeur,
            'paiementChargeur' => $paiementChargeur,
            'prixUnitaireChargeur' => $prixUnitaireChargeur,
            'chauffeur' => $chauffeur,
            'parcsParPont' => $parcsParPont,
            'gerableParPont' => $this->gerableParPontMap(),
        ]);
    }

    public function getTicketsConformesApi(Request $request, ChefEquipeContext $chefContext)
    {
        $fiche = null;
        $ficheId = (int) $request->query('fiche_id', 0);
        if ($ficheId > 0) {
            $fiche = FicheSortie::find($ficheId);
        }

        $mesTicketsService = app(MesTicketsService::class);
        $correspondance = app(FicheSortieTicketCorrespondanceService::class);
        $allTickets = $mesTicketsService->fetchAllTickets([], $request);
        $tickets = [];

        foreach ($allTickets as $normalized) {
            if ($fiche && ! $correspondance->correspond($correspondance->ticketDepuisApi($normalized), $fiche)) {
                continue;
            }

            $tickets[] = [
                'id_ticket' => $normalized['id_ticket'],
                'numero_ticket' => $normalized['numero_ticket'],
                'matricule_vehicule' => $normalized['matricule_vehicule'],
                'date_ticket' => $normalized['date_ticket'] ?? '',
                'agent_nom' => $normalized['nom_agent'],
                'nom_pont' => $normalized['nom_pont'] ?? '',
                'nom_usine' => $normalized['nom_usine'] ?? '',
                'poids' => $normalized['poids'] ?? 0,
            ];
        }

        return response()->json($tickets);
    }

    public function updateFicheSortie(Request $request, int $ficheId)
    {
        $ficheSortie = FicheSortie::findOrFail($ficheId);

        $validated = $request->validate([
            'id_pont' => ['required', 'integer'],
            'id_agent' => ['required', 'integer'],
            'id_chef_chargeur' => ['nullable', 'integer'],
            'produit_id' => ['required', 'integer', 'exists:produits,id'],
            'usine' => ['nullable', 'string', 'max:255'],
            'date_dechargement' => ['nullable', 'date'],
            'poids_pont' => ['nullable', 'numeric', 'min:0'],
            'carburant' => ['nullable', 'integer', 'min:0'],
            'frais_route' => ['nullable', 'integer', 'min:0'],
        ]);

        if (!$this->usineAppartientAuProduit($validated['usine'] ?? null, (int) $validated['produit_id'])) {
            return back()->withErrors(['usine' => "L'usine sélectionnée n'est pas associée à ce produit."])->withInput();
        }

        $produit = \App\Models\Produit::find($validated['produit_id']);
        $nomProduit = $produit ? $produit->nom : null;

        // Récupérer les infos du pont depuis l'API
        $timeout = 10;
        $nomPont = $ficheSortie->nom_pont;
        $codePont = $ficheSortie->code_pont;
        $nomAgent = $ficheSortie->nom_agent;
        $numeroAgent = $ficheSortie->numero_agent;

        try {
            $pontsResponse = Http::acceptJson()
                ->withoutVerifying()
                ->timeout($timeout)
                ->get('https://api.objetombrepegasus.online/api/camions/mes_ponts.php');
            if ($pontsResponse->successful()) {
                $ponts = $pontsResponse->json('ponts') ?? [];
                foreach ($ponts as $p) {
                    if (($p['id_pont'] ?? null) == $validated['id_pont']) {
                        $nomPont = $p['nom_pont'] ?? '';
                        $codePont = $p['code_pont'] ?? '';
                        break;
                    }
                }
            }
        } catch (\Throwable $e) {
            // Ignorer l'erreur
        }

        $apiAgent = app(MesAgentsService::class)->findAgentById((int) $validated['id_agent']);
        if ($apiAgent) {
            $nomAgent = $apiAgent['nom_complet'] ?? '';
            $numeroAgent = $apiAgent['numero_agent'] ?? '';
        }

        $ficheSortie->update([
            'id_pont' => $validated['id_pont'],
            'nom_pont' => $nomPont,
            'code_pont' => $codePont,
            'id_agent' => $validated['id_agent'],
            'nom_agent' => $nomAgent,
            'numero_agent' => $numeroAgent,
            'id_chef_chargeur' => $validated['id_chef_chargeur'] ?? null,
            'produit_id' => $validated['produit_id'],
            'nom_produit' => $nomProduit,
            'usine' => $validated['usine'] ?? null,
            'date_dechargement' => $validated['date_dechargement'] ?? null,
            'poids_pont' => $validated['poids_pont'] ?? null,
            'carburant' => $validated['carburant'] ?? null,
            'frais_route' => $validated['frais_route'] ?? null,
        ]);

        return redirect()->route('fiches_sortie.index')->with('success', 'Fiche de sortie modifiée avec succès.');
    }

    public function destroyFicheSortie(int $ficheId)
    {
        $ficheSortie = FicheSortie::findOrFail($ficheId);
        $ficheSortie->delete();

        return redirect()->route('fiches_sortie.index')->with('success', 'Fiche de sortie supprimée avec succès.');
    }

    public function updateDechargement(Request $request, int $ficheId)
    {
        $ficheSortie = FicheSortie::findOrFail($ficheId);

        $redirectBack = fn (array $errors = []) => redirect()
            ->back()
            ->withInput()
            ->with('open_dechargement_modal', $ficheId)
            ->withErrors($errors);

        $pontGerable = $this->pontEstGerable((int) $ficheSortie->id_pont);

        try {
            $rules = [
                'date_dechargement' => ['required', 'date'],
                'numero_ticket' => [
                    'required',
                    'string',
                    'max:100',
                    \Illuminate\Validation\Rule::unique('fiches_sortie', 'numero_ticket')->ignore($ficheId),
                ],
                'poids_pont' => ['required', 'numeric', 'min:0.01'],
                'prix_unitaire_camion' => ['nullable', 'numeric', 'min:0'],
                'montant_camion' => ['nullable', 'numeric', 'min:0'],
            ];

            if ($pontGerable) {
                $rules['parc_id'] = ['required', 'exists:parcs,id'];
            } else {
                $rules['parc_id'] = ['nullable', 'exists:parcs,id'];
            }

            $validated = $request->validate($rules, [
                'numero_ticket.required' => 'Le numéro de ticket est obligatoire.',
                'numero_ticket.unique' => 'Ce numéro de ticket existe déjà sur une autre fiche de sortie.',
                'parc_id.required' => 'Sélectionnez un parc pour le déchargement.',
            ]);
        } catch (ValidationException $e) {
            return redirect()
                ->back()
                ->withInput()
                ->with('open_dechargement_modal', $ficheId)
                ->withErrors($e->errors());
        }

        $numeroTicket = trim($validated['numero_ticket']);
        if ($numeroTicket === '') {
            return $redirectBack(['numero_ticket' => 'Le numéro de ticket est obligatoire.']);
        }

        $dejaDecharge = $ficheSortie->date_dechargement !== null;

        $parcId = !empty($validated['parc_id']) ? (int) $validated['parc_id'] : null;
        $stockOuvert = null;
        $parc = null;

        if ($pontGerable) {
            $parc = \App\Models\Parc::find($parcId);
            if (!$parc || $parc->id_pont != $ficheSortie->id_pont) {
                return $redirectBack(['parc_id' => 'Parc invalide pour ce pont.']);
            }

            $stockOuvert = $this->resoudreStockPourDechargement($ficheSortie, $parc->id);

            if (!$stockOuvert) {
                return $redirectBack(['parc_id' => 'Aucun stock ouvert pour ce parc avec le produit « ' . ($ficheSortie->nom_produit ?? '-') . ' ».']);
            }
        }

        $poidsDecharge = (float) $validated['poids_pont'];
        $prixUnitaireCamion = $validated['prix_unitaire_camion'] ?? null;
        $montantCamion = $validated['montant_camion'] ?? null;

        // Calculer le montant camion si prix unitaire fourni
        if ($prixUnitaireCamion !== null && $prixUnitaireCamion > 0) {
            $montantCamion = $prixUnitaireCamion * $poidsDecharge;
        }

        $montantAgent = app(MontantAgentFicheService::class)->calculerMontantPourFiche(
            $ficheSortie,
            $poidsDecharge
        );

        $ficheSortie->update([
            'date_dechargement' => $validated['date_dechargement'],
            'numero_ticket' => $numeroTicket,
            'poids_pont' => $poidsDecharge,
            'prix_unitaire_camion' => $prixUnitaireCamion,
            'montant_camion' => $montantCamion,
            'stock_id' => $stockOuvert?->id,
            'parc_id' => $parc?->id,
            'nom_parc' => $parc?->nom,
            'montant_agent' => $montantAgent !== null ? round($montantAgent, 2) : null,
        ]);

        // Créer une sortie de stock PGF si la fiche a un pont gérable et n'était pas déjà déchargée
        if (!$dejaDecharge && $pontGerable && $ficheSortie->id_pont && $poidsDecharge > 0) {
            // Trouver le stock actif PGF
            $stockActif = \App\Models\StockPgf::where('statut', 'actif')
                ->orderBy('created_at', 'desc')
                ->first();

            if ($stockActif) {
                \App\Models\SortieStockPgf::create([
                    'stock_pgf_id' => $stockActif->id,
                    'fiche_sortie_id' => $ficheSortie->id,
                    'id_pont' => $ficheSortie->id_pont,
                    'nom_pont' => $ficheSortie->nom_pont,
                    'code_pont' => $ficheSortie->code_pont,
                    'quantite' => $poidsDecharge,
                    'date_sortie' => $validated['date_dechargement'],
                    'commentaire' => 'Sortie automatique - Fiche de sortie #' . $ficheSortie->id . ' - Véhicule: ' . $ficheSortie->matricule_vehicule,
                ]);
            }
        }

        return redirect()->back()->with('success', 'Déchargement enregistré avec succès.');
    }

    public function exportFicheSortiePdf(int $ficheId)
    {
        $fiche = FicheSortie::findOrFail($ficheId);
        $user = Auth::user();
        $printedBy = $user ? $user->name : 'Utilisateur';
        $printedAt = now()->format('d/m/Y à H:i:s');

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('fiches_sortie.pdf', [
            'fiche' => $fiche,
            'printedBy' => $printedBy,
            'printedAt' => $printedAt,
        ]);

        $pdf->setPaper('A4', 'portrait');

        return $pdf->stream('fiche_sortie_' . $fiche->id . '.pdf');
    }
}
