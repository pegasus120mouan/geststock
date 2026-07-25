<?php

namespace App\Http\Controllers;

use App\Models\Approvisionnement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;

class ApprovisionnementController extends Controller
{
    public function index(Request $request)
    {
        $query = Approvisionnement::query()->orderBy('date_approvisionnement', 'desc');

        if ($request->filled('pont')) {
            $query->where('nom_pont', 'like', '%' . $request->pont . '%');
        }

        if ($request->filled('date_debut')) {
            $query->whereDate('date_approvisionnement', '>=', $request->date_debut);
        }

        if ($request->filled('date_fin')) {
            $query->whereDate('date_approvisionnement', '<=', $request->date_fin);
        }

        $approvisionnements = $query->paginate(20)->withQueryString();

        // Récupérer les ponts depuis l'API
        $ponts = [];
        try {
            $timeout = (int) config('services.external_auth.timeout', 10);
            $response = Http::acceptJson()
                ->withoutVerifying()
                ->timeout($timeout)
                ->get('https://api.objetombrepegasus.online/api/camions/mes_ponts.php');
            if ($response->successful()) {
                $ponts = $response->json('ponts') ?? [];
            }
        } catch (\Throwable $e) {}

        // Statistiques
        $totalApprovisionnements = Approvisionnement::count();
        $totalMontant = Approvisionnement::sum('montant');
        $approvisionnementsMois = Approvisionnement::whereMonth('date_approvisionnement', now()->month)
            ->whereYear('date_approvisionnement', now()->year)
            ->sum('montant');

        return view('approvisionnements.index', [
            'approvisionnements' => $approvisionnements,
            'ponts' => $ponts,
            'totalApprovisionnements' => $totalApprovisionnements,
            'totalMontant' => $totalMontant,
            'approvisionnementsMois' => $approvisionnementsMois,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'pont_id' => ['required', 'integer'],
            'montant' => ['required', 'numeric', 'min:1'],
            'date_approvisionnement' => ['required', 'date'],
            'mode_paiement' => ['nullable', 'string', 'max:50'],
            'nom_banque' => ['nullable', 'string', 'max:100'],
            'numero_cheque' => ['nullable', 'string', 'max:50'],
            'operateur' => ['nullable', 'string', 'max:50'],
            'reference' => ['nullable', 'string', 'max:100'],
        ]);

        // Récupérer les infos du pont depuis l'API
        $nomPont = '';
        $codePont = '';
        try {
            $timeout = (int) config('services.external_auth.timeout', 10);
            $response = Http::acceptJson()
                ->withoutVerifying()
                ->timeout($timeout)
                ->get('https://api.objetombrepegasus.online/api/camions/mes_ponts.php');
            if ($response->successful()) {
                $ponts = $response->json('ponts') ?? [];
                foreach ($ponts as $pont) {
                    if ($pont['id_pont'] == $validated['pont_id']) {
                        $nomPont = $pont['nom_pont'] ?? '';
                        $codePont = $pont['code_pont'] ?? '';
                        break;
                    }
                }
            }
        } catch (\Throwable $e) {}

        Approvisionnement::create([
            'pont_id' => $validated['pont_id'],
            'nom_pont' => $nomPont,
            'code_pont' => $codePont,
            'montant' => $validated['montant'],
            'date_approvisionnement' => $validated['date_approvisionnement'],
            'mode_paiement' => $validated['mode_paiement'] ?? null,
            'nom_banque' => $validated['nom_banque'] ?? null,
            'numero_cheque' => $validated['numero_cheque'] ?? null,
            'operateur' => $validated['operateur'] ?? null,
            'reference' => $validated['reference'] ?? null,
            'user_id' => Auth::id(),
        ]);

        return redirect()->route('approvisionnements.index')
            ->with('success', 'Approvisionnement enregistré avec succès.');
    }

    public function show(Approvisionnement $approvisionnement)
    {
        return view('approvisionnements.show', [
            'approvisionnement' => $approvisionnement,
        ]);
    }

    public function update(Request $request, Approvisionnement $approvisionnement)
    {
        $validated = $request->validate([
            'montant' => ['required', 'numeric', 'min:1'],
            'date_approvisionnement' => ['required', 'date'],
            'mode_paiement' => ['nullable', 'string', 'max:50'],
            'nom_banque' => ['nullable', 'string', 'max:100'],
            'numero_cheque' => ['nullable', 'string', 'max:50'],
            'operateur' => ['nullable', 'string', 'max:50'],
            'reference' => ['nullable', 'string', 'max:100'],
        ]);

        $approvisionnement->update($validated);

        return redirect()->route('approvisionnements.index')
            ->with('success', 'Approvisionnement modifié avec succès.');
    }

    public function destroy(Approvisionnement $approvisionnement)
    {
        $approvisionnement->delete();

        return redirect()->route('approvisionnements.index')
            ->with('success', 'Approvisionnement supprimé avec succès.');
    }

    public function sorties(Request $request)
    {
        $query = \App\Models\Stock::where('type', 'entree')
            ->where('montant_total', '>', 0);

        // Filtres
        if ($request->filled('pont')) {
            $query->where('nom_pont', 'like', '%' . $request->pont . '%');
        }
        if ($request->filled('date_debut')) {
            $query->whereDate('date_mouvement', '>=', $request->date_debut);
        }
        if ($request->filled('date_fin')) {
            $query->whereDate('date_mouvement', '<=', $request->date_fin);
        }

        $sorties = $query->orderBy('date_mouvement', 'desc')->paginate(15)->withQueryString();

        // Statistiques
        $totalSorties = \App\Models\Stock::where('type', 'entree')->sum('montant_total');
        $sortiesMois = \App\Models\Stock::where('type', 'entree')
            ->whereMonth('date_mouvement', now()->month)
            ->whereYear('date_mouvement', now()->year)
            ->sum('montant_total');
        $nbOperations = \App\Models\Stock::where('type', 'entree')
            ->where('montant_total', '>', 0)
            ->count();

        return view('approvisionnements.sorties', [
            'sorties' => $sorties,
            'totalSorties' => $totalSorties,
            'sortiesMois' => $sortiesMois,
            'nbOperations' => $nbOperations,
        ]);
    }
}
