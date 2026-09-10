<?php

namespace App\Http\Controllers;

use App\Models\Commande;
use App\Models\Produit;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class FinanceController extends Controller
{
    public function bilanMois(Request $request): View
    {
        $mois = (int) $request->input('mois', now()->month);
        $annee = (int) $request->input('annee', now()->year);

        $debut = Carbon::create($annee, $mois, 1)->startOfDay();
        $fin = (clone $debut)->endOfMonth();

        $commandes = Commande::query()
            ->with(['produit', 'flacon'])
            ->whereBetween('date_commande', [$debut->toDateString(), $fin->toDateString()])
            ->orderByDesc('date_commande')
            ->get();

        $caTotal = (float) $commandes->sum(fn (Commande $c) => $c->montant());
        $nbCommandes = $commandes->count();
        $nbLivrees = $commandes->where('statut', 'livree')->count();
        $caLivre = (float) $commandes->where('statut', 'livree')->sum(fn (Commande $c) => $c->montant());
        $nbEnAttente = $commandes->where('statut', 'en_attente')->count();

        $parParfum = $commandes
            ->groupBy('produit_id')
            ->map(function ($items) {
                /** @var \Illuminate\Support\Collection<int, Commande> $items */
                $first = $items->first();

                return (object) [
                    'produit' => $first?->produit?->nom ?? '—',
                    'quantite' => $items->sum('quantite'),
                    'montant' => (float) $items->sum(fn (Commande $c) => $c->montant()),
                    'nb' => $items->count(),
                ];
            })
            ->sortByDesc('montant')
            ->values();

        return view('finance.bilan-mois', [
            'mois' => $mois,
            'annee' => $annee,
            'debut' => $debut,
            'fin' => $fin,
            'commandes' => $commandes,
            'caTotal' => $caTotal,
            'caLivre' => $caLivre,
            'nbCommandes' => $nbCommandes,
            'nbLivrees' => $nbLivrees,
            'nbEnAttente' => $nbEnAttente,
            'parParfum' => $parParfum,
            'moisOptions' => $this->moisOptions(),
            'annees' => range(now()->year, now()->year - 4),
        ]);
    }

    public function statistiques(Request $request): View
    {
        $annee = (int) $request->input('annee', now()->year);

        $parMois = Commande::query()
            ->selectRaw('MONTH(date_commande) as mois')
            ->selectRaw('COUNT(*) as nb_commandes')
            ->selectRaw('COALESCE(SUM(total), 0) as ca')
            ->selectRaw("SUM(CASE WHEN statut = 'livree' THEN 1 ELSE 0 END) as nb_livrees")
            ->whereYear('date_commande', $annee)
            ->groupBy(DB::raw('MONTH(date_commande)'))
            ->orderBy('mois')
            ->get()
            ->keyBy('mois');

        $statsMensuelles = collect(range(1, 12))->map(function (int $mois) use ($parMois) {
            $row = $parMois->get($mois);

            return (object) [
                'mois' => $mois,
                'label' => $this->moisOptions()[$mois],
                'nb_commandes' => (int) ($row->nb_commandes ?? 0),
                'nb_livrees' => (int) ($row->nb_livrees ?? 0),
                'ca' => (float) ($row->ca ?? 0),
            ];
        });

        $topParfums = Commande::query()
            ->select('produit_id')
            ->selectRaw('COUNT(*) as nb')
            ->selectRaw('SUM(quantite) as qte')
            ->selectRaw('COALESCE(SUM(total), 0) as ca')
            ->with('produit')
            ->whereYear('date_commande', $annee)
            ->groupBy('produit_id')
            ->orderByDesc('ca')
            ->limit(10)
            ->get();

        $parStatut = Commande::query()
            ->select('statut')
            ->selectRaw('COUNT(*) as nb')
            ->selectRaw('COALESCE(SUM(total), 0) as ca')
            ->whereYear('date_commande', $annee)
            ->groupBy('statut')
            ->get();

        $nbParfumsActifs = Produit::query()->where('statut', 'actif')->count();
        $caAnnuel = (float) $statsMensuelles->sum('ca');
        $nbCommandesAnnee = (int) $statsMensuelles->sum('nb_commandes');

        return view('finance.statistiques', [
            'annee' => $annee,
            'annees' => range(now()->year, now()->year - 4),
            'statsMensuelles' => $statsMensuelles,
            'topParfums' => $topParfums,
            'parStatut' => $parStatut,
            'nbParfumsActifs' => $nbParfumsActifs,
            'caAnnuel' => $caAnnuel,
            'nbCommandesAnnee' => $nbCommandesAnnee,
            'moisOptions' => $this->moisOptions(),
        ]);
    }

    /** @return array<int, string> */
    private function moisOptions(): array
    {
        return [
            1 => 'Janvier',
            2 => 'Février',
            3 => 'Mars',
            4 => 'Avril',
            5 => 'Mai',
            6 => 'Juin',
            7 => 'Juillet',
            8 => 'Août',
            9 => 'Septembre',
            10 => 'Octobre',
            11 => 'Novembre',
            12 => 'Décembre',
        ];
    }
}
