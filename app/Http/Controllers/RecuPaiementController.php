<?php

namespace App\Http\Controllers;

use App\Models\PaiementAgent;
use App\Models\PaiementParticulierAgent;
use App\Services\RecuPaiementService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class RecuPaiementController extends Controller
{
    public function __construct(
        private RecuPaiementService $recuPaiementService
    ) {}

    public function index(Request $request)
    {
        $queryAgents = PaiementAgent::query()
            ->with('bordereau')
            ->orderByDesc('date_paiement')
            ->orderByDesc('id');

        if ($request->filled('q')) {
            $q = $request->string('q')->toString();
            $queryAgents->where(function ($sub) use ($q) {
                $sub->where('numero_recu', 'like', "%{$q}%")
                    ->orWhere('reference', 'like', "%{$q}%")
                    ->orWhereHas('bordereau', function ($b) use ($q) {
                        $b->where('numero', 'like', "%{$q}%")
                            ->orWhere('agent_nom', 'like', "%{$q}%")
                            ->orWhere('agent_numero', 'like', "%{$q}%");
                    });
            });
        }

        if ($request->filled('date_debut')) {
            $queryAgents->whereDate('date_paiement', '>=', $request->input('date_debut'));
        }

        if ($request->filled('date_fin')) {
            $queryAgents->whereDate('date_paiement', '<=', $request->input('date_fin'));
        }

        $paiementsAgents = $queryAgents->paginate(15, ['*'], 'page_agent')->withQueryString();

        $queryParticuliers = PaiementParticulierAgent::query()
            ->with('agent.groupe')
            ->orderByDesc('date_paiement')
            ->orderByDesc('id');

        if ($request->filled('q')) {
            $q = $request->string('q')->toString();
            $queryParticuliers->where(function ($sub) use ($q) {
                $sub->where('reference', 'like', "%{$q}%")
                    ->orWhereHas('agent', function ($a) use ($q) {
                        $a->where('numero_agent', 'like', "%{$q}%")
                            ->orWhere('nom', 'like', "%{$q}%")
                            ->orWhere('prenoms', 'like', "%{$q}%");
                    });
            });
        }

        if ($request->filled('date_debut')) {
            $queryParticuliers->whereDate('date_paiement', '>=', $request->input('date_debut'));
        }

        if ($request->filled('date_fin')) {
            $queryParticuliers->whereDate('date_paiement', '<=', $request->input('date_fin'));
        }

        $paiementsParticuliers = $queryParticuliers->paginate(15, ['*'], 'page_particulier')->withQueryString();

        $totaux = [
            'agents' => (int) PaiementAgent::sum('montant'),
            'particuliers' => (int) PaiementParticulierAgent::sum('montant'),
        ];

        return view('gestion_financiere.recus_paiement.index', [
            'paiementsAgents' => $paiementsAgents,
            'paiementsParticuliers' => $paiementsParticuliers,
            'totaux' => $totaux,
        ]);
    }

    public function pdf(Request $request, int $id)
    {
        $paiement = PaiementAgent::with('bordereau')->findOrFail($id);

        if (!$paiement->numero_recu) {
            $this->recuPaiementService->assignerNumero($paiement);
            $paiement->refresh();
        }

        $donnees = $this->recuPaiementService->donneesPdf(
            $paiement,
            $request->user()?->name
        );

        $pdf = Pdf::loadView('gestion_financiere.recu_paiement_pdf', $donnees);
        $pdf->setPaper('A4', 'portrait');

        $filename = 'recu_' . preg_replace('/[^a-zA-Z0-9_-]+/', '_', $donnees['numeroRecu']) . '.pdf';

        return $pdf->stream($filename);
    }
}
