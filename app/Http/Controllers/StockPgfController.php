<?php

namespace App\Http\Controllers;

use App\Models\StockPgf;
use App\Models\EntreeStockPgf;
use App\Models\BordereauStock;
use App\Services\ChefEquipeContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class StockPgfController extends Controller
{
    public function index()
    {
        $stocks = StockPgf::withSum('entrees', 'quantite')
            ->orderBy('created_at', 'desc')
            ->get();

        return view('stocks_pgf.index', [
            'stocks' => $stocks,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'date_debut' => ['required', 'date'],
            'date_fin' => ['nullable', 'date', 'after_or_equal:date_debut'],
        ]);

        StockPgf::create([
            'code' => StockPgf::generateCode(),
            'date_debut' => $validated['date_debut'],
            'date_fin' => $validated['date_fin'] ?? null,
            'statut' => 'actif',
        ]);

        return redirect()->route('stocks_pgf.index')
            ->with('success', 'Stock créé avec succès.');
    }

    public function show(Request $request, int $id)
    {
        $stock = StockPgf::with(['entrees', 'sorties'])->findOrFail($id);

        // Récupérer les ponts depuis l'API
        $mesPontsUrl = (string) config('services.external_auth.mes_ponts_url');
        $timeout = (int) config('services.external_auth.timeout', 10);
        $ponts = [];

        try {
            $response = Http::acceptJson()->withoutVerifying()->timeout($timeout)->get($mesPontsUrl);
            if ($response->successful()) {
                $ponts = $response->json('ponts') ?? [];
            }
        } catch (\Throwable $e) {}

        // Calculer le total des entrées et sorties
        $totalEntrees = $stock->entrees->sum('quantite');
        $totalSorties = $stock->sorties->sum('quantite');
        $stockDisponible = $totalEntrees - $totalSorties;

        // Grouper les entrées par pont
        $entreesParPont = $stock->entrees->groupBy('id_pont')->map(function ($entrees) {
            return [
                'nom_pont' => $entrees->first()->nom_pont,
                'total' => $entrees->sum('quantite'),
                'nb_entrees' => $entrees->count(),
            ];
        });

        // Grouper les sorties par pont
        $sortiesParPont = $stock->sorties->groupBy('id_pont')->map(function ($sorties) {
            return [
                'nom_pont' => $sorties->first()->nom_pont,
                'total' => $sorties->sum('quantite'),
                'nb_sorties' => $sorties->count(),
            ];
        });

        return view('stocks_pgf.show', [
            'stock' => $stock,
            'ponts' => $ponts,
            'totalEntrees' => $totalEntrees,
            'totalSorties' => $totalSorties,
            'stockDisponible' => $stockDisponible,
            'entreesParPont' => $entreesParPont,
            'sortiesParPont' => $sortiesParPont,
        ]);
    }

    public function cloturer(int $id)
    {
        $stock = StockPgf::findOrFail($id);
        $stock->update([
            'statut' => 'cloture',
            'date_fin' => now(),
        ]);

        return redirect()->route('stocks_pgf.index')
            ->with('success', 'Stock clôturé avec succès.');
    }

    public function destroy(int $id)
    {
        $stock = StockPgf::findOrFail($id);
        $stock->delete();

        return redirect()->route('stocks_pgf.index')
            ->with('success', 'Stock supprimé avec succès.');
    }

    public function addEntree(Request $request, int $id)
    {
        $stock = StockPgf::findOrFail($id);

        if ($stock->statut === 'cloture') {
            return back()->withErrors(['error' => 'Ce stock est clôturé.']);
        }

        $validated = $request->validate([
            'id_pont' => ['required', 'integer'],
            'pont_display' => ['nullable', 'string'],
            'quantite' => ['required', 'numeric', 'min:0'],
            'date_entree' => ['required', 'date'],
            'commentaire' => ['nullable', 'string', 'max:500'],
        ]);

        // Parser les infos du pont depuis le display
        $pontDisplay = $validated['pont_display'] ?? '';
        $nomPont = '';
        $codePont = '';
        if (preg_match('/^(.+)\s+\(([^)]+)\)$/', $pontDisplay, $matches)) {
            $nomPont = trim($matches[1]);
            $codePont = trim($matches[2]);
        }

        EntreeStockPgf::create([
            'stock_pgf_id' => $id,
            'id_pont' => $validated['id_pont'],
            'nom_pont' => $nomPont,
            'code_pont' => $codePont,
            'quantite' => $validated['quantite'],
            'date_entree' => $validated['date_entree'],
            'commentaire' => $validated['commentaire'] ?? null,
        ]);

        return redirect()->route('stocks_pgf.show', $id)
            ->with('success', 'Entrée de stock ajoutée avec succès.');
    }

    public function removeEntree(int $id, int $entree_id)
    {
        $entree = EntreeStockPgf::where('id', $entree_id)
            ->where('stock_pgf_id', $id)
            ->first();

        if ($entree) {
            $entree->delete();
            return redirect()->route('stocks_pgf.show', $id)
                ->with('success', 'Entrée supprimée avec succès.');
        }

        return redirect()->route('stocks_pgf.show', $id)
            ->withErrors(['error' => 'Entrée non trouvée.']);
    }

    public function sorties(Request $request)
    {
        // Récupérer tous les stocks actifs avec leurs entrées
        $stocks = StockPgf::with('entrees')
            ->where('statut', 'actif')
            ->orderBy('created_at', 'desc')
            ->get();

        // Récupérer les ponts depuis l'API
        $mesPontsUrl = (string) config('services.external_auth.mes_ponts_url');
        $timeout = (int) config('services.external_auth.timeout', 10);
        $ponts = [];

        try {
            $response = Http::acceptJson()->withoutVerifying()->timeout($timeout)->get($mesPontsUrl);
            if ($response->successful()) {
                $ponts = $response->json('ponts') ?? [];
            }
        } catch (\Throwable $e) {}

        return view('stocks_pgf.sorties', [
            'stocks' => $stocks,
            'ponts' => $ponts,
        ]);
    }

    public function bordereaux(Request $request, ChefEquipeContext $chefContext)
    {
        $bordereaux = BordereauStock::orderBy('created_at', 'desc')->get();

        // Récupérer les ponts depuis l'API
        $mesPontsUrl = (string) config('services.external_auth.mes_ponts_url');
        $timeout = (int) config('services.external_auth.timeout', 10);
        $ponts = [];

        try {
            $response = Http::acceptJson()->withoutVerifying()->timeout($timeout)->get($mesPontsUrl);
            if ($response->successful()) {
                $ponts = $response->json('ponts') ?? [];
            }
        } catch (\Throwable $e) {}

        // Récupérer les tickets depuis l'API
        $mesTicketsUrl = (string) config('services.external_auth.mes_tickets_url');
        $phpsessid = (string) $request->session()->get('external_auth.phpsessid', '');
        $ticketsApi = [];

        try {
            $ticketsResponse = Http::acceptJson()
                ->withoutVerifying()
                ->timeout($timeout)
                ->withHeaders(['Cookie' => 'PHPSESSID=' . $phpsessid])
                ->get($mesTicketsUrl, $chefContext->apiQueryParams($request));
            if ($ticketsResponse->successful()) {
                $ticketsApi = $ticketsResponse->json('tickets') ?? [];
            }
        } catch (\Throwable $e) {}

        // Indexer les tickets par id
        $ticketsById = [];
        foreach ($ticketsApi as $ticket) {
            $ticketsById[$ticket['id_ticket'] ?? 0] = $ticket;
        }

        // Calculer les tickets éligibles pour chaque bordereau
        $ticketsParBordereau = [];
        foreach ($bordereaux as $bordereau) {
            $pontsData = $bordereau->ponts_data ?? [];
            $nomsPonts = array_map(fn($p) => $p['nom_pont'] ?? '', $pontsData);

            // Récupérer les fiches de sortie dont le nom_pont correspond
            $fichesSortie = \App\Models\FicheSortie::whereIn('nom_pont', $nomsPonts)
                ->where('id_ticket', '>', 0)
                ->get();

            $ticketsEligibles = [];
            $totalPoids = 0;

            foreach ($fichesSortie as $fiche) {
                $idTicket = $fiche->id_ticket;
                $ticket = $ticketsById[$idTicket] ?? null;
                if ($ticket) {
                    $poidsUsine = (float) ($ticket['poids'] ?? 0);
                    $ticketsEligibles[] = [
                        'id_ticket' => $idTicket,
                        'numero_ticket' => $ticket['numero_ticket'] ?? '',
                        'matricule_vehicule' => $fiche->matricule_vehicule,
                        'nom_pont' => $fiche->nom_pont,
                        'poids_usine' => $poidsUsine,
                        'date_chargement' => $fiche->date_chargement,
                        'date_ticket' => $ticket['date_ticket'] ?? '',
                    ];
                    $totalPoids += $poidsUsine;
                }
            }

            $ticketsParBordereau[$bordereau->id] = [
                'tickets' => $ticketsEligibles,
                'total_poids' => $totalPoids,
            ];
        }

        return view('stocks_pgf.bordereaux', [
            'bordereaux' => $bordereaux,
            'ponts' => $ponts,
            'ticketsParBordereau' => $ticketsParBordereau,
        ]);
    }

    public function storeBordereau(Request $request)
    {
        $validated = $request->validate([
            'date_debut' => ['required', 'date'],
            'date_fin' => ['required', 'date', 'after_or_equal:date_debut'],
            'ponts' => ['required', 'array', 'min:1'],
            'ponts.*' => ['integer'],
        ]);

        // Récupérer les ponts depuis l'API
        $mesPontsUrl = (string) config('services.external_auth.mes_ponts_url');
        $timeout = (int) config('services.external_auth.timeout', 10);
        $pontsApi = [];

        try {
            $response = Http::acceptJson()->withoutVerifying()->timeout($timeout)->get($mesPontsUrl);
            if ($response->successful()) {
                $pontsApi = $response->json('ponts') ?? [];
            }
        } catch (\Throwable $e) {}

        // Indexer les ponts par id
        $pontsById = [];
        foreach ($pontsApi as $pont) {
            $pontsById[$pont['id_pont']] = $pont;
        }

        // Calculer le poids par pont pour la période
        $dateDebut = $validated['date_debut'];
        $dateFin = $validated['date_fin'];
        $pontsData = [];
        $poidsTotal = 0;

        foreach ($validated['ponts'] as $idPont) {
            $pontInfo = $pontsById[$idPont] ?? null;
            $nomPont = $pontInfo['nom_pont'] ?? 'Pont #' . $idPont;
            $codePont = $pontInfo['code_pont'] ?? '';

            // Calculer le poids des entrées de stock (table stocks) pour ce pont dans la période
            $poidsPont = \App\Models\Stock::where('id_pont', $idPont)
                ->where('type', 'entree')
                ->whereBetween('date_mouvement', [$dateDebut, $dateFin])
                ->sum('quantite');

            $pontsData[] = [
                'id_pont' => $idPont,
                'nom_pont' => $nomPont,
                'code_pont' => $codePont,
                'poids' => $poidsPont,
            ];

            $poidsTotal += $poidsPont;
        }

        BordereauStock::create([
            'numero' => BordereauStock::generateNumero(),
            'date_generation' => now(),
            'date_debut' => $dateDebut,
            'date_fin' => $dateFin,
            'ponts_data' => $pontsData,
            'poids_total' => $poidsTotal,
        ]);

        return redirect()->route('stocks_pgf.bordereaux')
            ->with('success', 'Bordereau de stock généré avec succès.');
    }

    public function showBordereau(int $id)
    {
        $bordereau = BordereauStock::findOrFail($id);

        return view('stocks_pgf.bordereau_show', [
            'bordereau' => $bordereau,
        ]);
    }

    public function destroyBordereau(int $id)
    {
        $bordereau = BordereauStock::findOrFail($id);
        $bordereau->delete();

        return redirect()->route('stocks_pgf.bordereaux')
            ->with('success', 'Bordereau supprimé avec succès.');
    }

    public function associerTickets(Request $request, int $id, ChefEquipeContext $chefContext)
    {
        $bordereau = BordereauStock::findOrFail($id);

        // Récupérer les noms des ponts du bordereau
        $pontsData = $bordereau->ponts_data ?? [];
        $nomsPonts = array_map(fn($p) => $p['nom_pont'] ?? '', $pontsData);

        // Récupérer les fiches de sortie dont le nom_pont correspond
        $fichesSortie = \App\Models\FicheSortie::whereIn('nom_pont', $nomsPonts)
            ->where('id_ticket', '>', 0)
            ->get();

        // Récupérer les tickets depuis l'API
        $mesTicketsUrl = (string) config('services.external_auth.mes_tickets_url');
        $timeout = (int) config('services.external_auth.timeout', 10);
        $phpsessid = (string) $request->session()->get('external_auth.phpsessid', '');
        $ticketsApi = [];

        try {
            $response = Http::acceptJson()
                ->withoutVerifying()
                ->timeout($timeout)
                ->withHeaders(['Cookie' => 'PHPSESSID=' . $phpsessid])
                ->get($mesTicketsUrl, $chefContext->apiQueryParams($request));
            if ($response->successful()) {
                $ticketsApi = $response->json('tickets') ?? [];
            }
        } catch (\Throwable $e) {}

        // Indexer les tickets par id
        $ticketsById = [];
        foreach ($ticketsApi as $ticket) {
            $ticketsById[$ticket['id_ticket'] ?? 0] = $ticket;
        }

        // Construire la liste des tickets éligibles
        $ticketsEligibles = [];
        $poidsSortie = 0;

        foreach ($fichesSortie as $fiche) {
            $idTicket = $fiche->id_ticket;
            $ticket = $ticketsById[$idTicket] ?? null;
            if ($ticket) {
                $poidsUsine = (float) ($ticket['poids'] ?? 0);
                $ticketsEligibles[] = [
                    'id_ticket' => $idTicket,
                    'numero_ticket' => $ticket['numero_ticket'] ?? '',
                    'matricule_vehicule' => $fiche->matricule_vehicule,
                    'nom_pont' => $fiche->nom_pont,
                    'poids_usine' => $poidsUsine,
                    'date_chargement' => $fiche->date_chargement,
                ];
                $poidsSortie += $poidsUsine;
            }
        }

        // Mettre à jour le bordereau
        $bordereau->update([
            'tickets_data' => $ticketsEligibles,
            'poids_sortie' => $poidsSortie,
        ]);

        return redirect()->route('stocks_pgf.bordereau.show', $id)
            ->with('success', count($ticketsEligibles) . ' ticket(s) associé(s) au bordereau.');
    }
}
