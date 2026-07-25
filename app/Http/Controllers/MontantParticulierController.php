<?php

namespace App\Http\Controllers;

use App\Models\PaiementParticulierAgent;
use App\Models\ParticulierAgent;
use App\Services\MontantParticulierReportingService;
use Illuminate\Http\Request;

class MontantParticulierController extends Controller
{
    public function __construct(
        private MontantParticulierReportingService $reporting
    ) {}

    public function index(Request $request)
    {
        $filtres = $this->reporting->filtresDepuisRequest($request);
        $options = $this->reporting->optionsFiltres();
        $agents = $this->reporting->queryAgentsLocaux()->get();
        $data = [];

        foreach ($agents as $agent) {
            $montantDu = (int) round($this->reporting->calculerMontantDuAgent($agent->id, $filtres));
            $montantDuGlobal = (int) round($this->reporting->calculerMontantDuAgent($agent->id, [
                'particulier_agent_id' => $agent->id,
            ]));
            $montantPaye = (int) PaiementParticulierAgent::where('particulier_agent_id', $agent->id)->sum('montant');

            if ($this->reporting->filtresActifs($filtres) && $montantDu === 0) {
                continue;
            }

            $data[] = [
                'agent' => $agent,
                'montant_du' => $montantDu,
                'montant_du_global' => $montantDuGlobal,
                'montant_paye' => $montantPaye,
                'reste_a_payer' => $montantDuGlobal - $montantPaye,
            ];
        }

        $search = trim((string) $request->query('q', ''));
        if ($search !== '') {
            $needle = mb_strtolower($search);
            $data = array_values(array_filter($data, function ($item) use ($needle) {
                $agent = $item['agent'];
                $nom = mb_strtolower($agent->nom_complet);
                $numero = mb_strtolower((string) ($agent->numero_agent ?? ''));

                return str_contains($nom, $needle) || str_contains($numero, $needle);
            }));
        }

        $agentNoms = collect($data)
            ->map(fn ($item) => $item['agent']->nom_complet)
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();

        return view('gestion_financiere.montant_particulier', [
            'data' => $data,
            'search' => $search,
            'agentNoms' => $agentNoms,
            'filtres' => $filtres,
            'filtresActifs' => $this->reporting->filtresActifs($filtres),
            'produits' => $options['produits'],
            'usines' => $options['usines'],
        ]);
    }

    public function show(Request $request, ParticulierAgent $agent)
    {
        if ($agent->id_agent) {
            return redirect()->route('gestionfinanciere.montant_particulier')
                ->withErrors(['error' => 'Cet agent est lié à l’API mes_agents. Consultez Montant Pisteur.']);
        }

        $filtres = $this->reporting->filtresDepuisRequest($request);
        $filtres['particulier_agent_id'] = $agent->id;

        $montantDu = (int) round($this->reporting->calculerMontantDuAgent($agent->id, $filtres));
        $montantDuGlobal = (int) round($this->reporting->calculerMontantDuAgent($agent->id, [
            'particulier_agent_id' => $agent->id,
        ]));
        $paiements = PaiementParticulierAgent::where('particulier_agent_id', $agent->id)
            ->orderByDesc('date_paiement')
            ->orderByDesc('id')
            ->get();
        $montantPaye = (int) $paiements->sum('montant');
        $resteAPayer = $montantDuGlobal - $montantPaye;

        $ticketsAvecMontant = $this->reporting->ticketsAvecMontant($filtres);
        $groupesProduitUsine = $this->reporting->grouperParProduitEtUsine($ticketsAvecMontant);
        $options = $this->reporting->optionsFiltres();

        return view('gestion_financiere.particulier_financier_detail', [
            'agent' => $agent->load('groupe'),
            'ticketsAvecMontant' => $ticketsAvecMontant,
            'groupesProduitUsine' => $groupesProduitUsine,
            'paiements' => $paiements,
            'montantDu' => $montantDu,
            'montantDuGlobal' => $montantDuGlobal,
            'montantPaye' => $montantPaye,
            'resteAPayer' => $resteAPayer,
            'filtres' => $filtres,
            'filtresActifs' => $this->reporting->filtresActifs($filtres),
            'produits' => $options['produits'],
            'usines' => $options['usines'],
            'queryFiltres' => $this->reporting->filtresPourUrl($filtres),
        ]);
    }

    public function storePaiement(Request $request, ParticulierAgent $agent)
    {
        if ($agent->id_agent) {
            return redirect()->route('gestionfinanciere.montant_particulier')
                ->withErrors(['error' => 'Agent non éligible aux paiements particuliers.']);
        }

        $validated = $request->validate([
            'montant' => ['required', 'integer', 'min:1'],
            'date_paiement' => ['required', 'date'],
            'mode_paiement' => ['nullable', 'string', 'max:50'],
            'reference' => ['nullable', 'string', 'max:100'],
            'commentaire' => ['nullable', 'string', 'max:500'],
        ]);

        PaiementParticulierAgent::create(array_merge($validated, [
            'particulier_agent_id' => $agent->id,
        ]));

        return back()->with('success', 'Paiement enregistré avec succès.');
    }
}
