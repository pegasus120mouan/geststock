<?php

namespace App\Http\Controllers;

use App\Models\AvanceTransporteur;
use App\Models\Transporteur;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AvanceTransporteurController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));

        $summariesQuery = Transporteur::query()
            ->leftJoin('avances_transporteur', 'transporteurs.id', '=', 'avances_transporteur.transporteur_id')
            ->select([
                'transporteurs.id',
                'transporteurs.code',
                'transporteurs.nom',
                'transporteurs.prenoms',
                DB::raw('COUNT(avances_transporteur.id) as nombre_avances'),
                DB::raw('COALESCE(SUM(avances_transporteur.montant), 0) as montant_total'),
                DB::raw('COALESCE(SUM(avances_transporteur.montant - avances_transporteur.montant_utilise), 0) as solde_restant'),
            ])
            ->groupBy('transporteurs.id', 'transporteurs.code', 'transporteurs.nom', 'transporteurs.prenoms');

        if ($search !== '') {
            $summariesQuery->where(function ($query) use ($search) {
                $query->where('transporteurs.code', 'like', "%{$search}%")
                    ->orWhere('transporteurs.nom', 'like', "%{$search}%")
                    ->orWhere('transporteurs.prenoms', 'like', "%{$search}%");
            });
        }

        $summaries = $summariesQuery
            ->orderByDesc('montant_total')
            ->orderBy('transporteurs.nom')
            ->paginate(20)
            ->withQueryString();

        return view('avances_transporteur.index', compact('summaries', 'search'));
    }

    public function show(Request $request, Transporteur $transporteur): View
    {
        $filters = [
            'date_debut' => $request->query('date_debut'),
            'date_fin' => $request->query('date_fin'),
        ];

        $avancesQuery = AvanceTransporteur::query()
            ->where('transporteur_id', $transporteur->id)
            ->orderByDesc('date_avance')
            ->orderByDesc('id');

        if ($filters['date_debut']) {
            $avancesQuery->whereDate('date_avance', '>=', $filters['date_debut']);
        }

        if ($filters['date_fin']) {
            $avancesQuery->whereDate('date_avance', '<=', $filters['date_fin']);
        }

        $avances = $avancesQuery->get();

        return view('avances_transporteur.show', [
            'avances' => $avances,
            'transporteur' => $transporteur,
            'filters' => $filters,
            'totalAvances' => (int) $avances->sum('montant'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if ($request->has('montant')) {
            $request->merge([
                'montant' => preg_replace('/\s+/', '', (string) $request->input('montant')),
            ]);
        }

        $validated = $request->validate([
            'transporteur_id' => ['required', 'integer', 'exists:transporteurs,id'],
            'montant' => ['required', 'integer', 'min:1'],
            'date_avance' => ['required', 'date'],
            'mode_paiement' => ['nullable', 'string', 'max:50'],
            'reference' => ['nullable', 'string', 'max:100'],
            'commentaire' => ['nullable', 'string', 'max:500'],
        ]);

        AvanceTransporteur::create($validated);

        return redirect()
            ->route('avances_transporteur.show', $validated['transporteur_id'])
            ->with(
                'success',
                'Avance de '.number_format((int) $validated['montant'], 0, ',', ' ')
                .' FCFA enregistrée avec succès.'
            );
    }
}
