<?php

namespace App\Services;

use App\Models\AvanceTransporteur;
use App\Models\PaiementAgent;
use App\Models\PaiementChefChargeur;
use App\Models\PaiementFournisseur;
use App\Models\PaiementParticulierAgent;
use App\Models\PaiementPisteur;
use App\Models\PaiementTransporteur;
use App\Models\PaiementTransporteurGestion;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class HistoriqueTransactionsService
{
    public function __construct(
        private readonly MesAgentsService $mesAgentsService,
    ) {}

    /**
     * @return LengthAwarePaginator<int, object>
     */
    public function paginate(Request $request, int $perPage = 20): LengthAwarePaginator
    {
        $items = $this->allTransactions($request)
            ->sortByDesc(fn ($row) => sprintf('%s-%010d', $row->date_sort, $row->id_sort))
            ->values();

        $page = max(1, (int) $request->query('page', 1));
        $slice = $items->slice(($page - 1) * $perPage, $perPage)->values();

        return new LengthAwarePaginator(
            $slice,
            $items->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()],
        );
    }

    /**
     * @return Collection<int, object>
     */
    public function allTransactions(Request $request): Collection
    {
        $search = trim((string) $request->query('q', ''));
        $dateDebut = trim((string) $request->query('date_debut', ''));
        $dateFin = trim((string) $request->query('date_fin', ''));
        $type = trim((string) $request->query('type', ''));

        $rows = collect();

        if ($type === '' || $type === 'agent') {
            $rows = $rows->merge($this->paiementsAgents($request, $search, $dateDebut, $dateFin));
        }
        if ($type === '' || $type === 'particulier') {
            $rows = $rows->merge($this->paiementsParticuliers($search, $dateDebut, $dateFin));
        }
        if ($type === '' || $type === 'transporteur') {
            $rows = $rows->merge($this->paiementsTransporteurs($search, $dateDebut, $dateFin));
        }
        if ($type === '' || $type === 'fournisseur') {
            $rows = $rows->merge($this->paiementsFournisseurs($search, $dateDebut, $dateFin));
        }
        if ($type === '' || $type === 'chef_chargeur') {
            $rows = $rows->merge($this->paiementsChefsChargeurs($search, $dateDebut, $dateFin));
        }
        if ($type === '' || $type === 'pisteur') {
            $rows = $rows->merge($this->paiementsPisteurs($search, $dateDebut, $dateFin));
        }

        return $rows;
    }

    /**
     * @return Collection<int, object>
     */
    private function paiementsAgents(Request $request, string $search, string $dateDebut, string $dateFin): Collection
    {
        $query = PaiementAgent::query()->with('bordereau');

        try {
            $agentIds = $this->mesAgentsService->chefAgentIds($request);
            if ($agentIds !== []) {
                $query->whereIn('id_agent', $agentIds);
            }
        } catch (\Throwable) {
            // Sans contexte chef (API/session), afficher tous les paiements agents.
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('numero_recu', 'like', "%{$search}%")
                    ->orWhere('reference', 'like', "%{$search}%")
                    ->orWhere('commentaire', 'like', "%{$search}%")
                    ->orWhere('mode_paiement', 'like', "%{$search}%")
                    ->orWhereHas('bordereau', function ($b) use ($search) {
                        $b->where('numero', 'like', "%{$search}%")
                            ->orWhere('agent_nom', 'like', "%{$search}%")
                            ->orWhere('agent_numero', 'like', "%{$search}%");
                    });
            });
        }

        $this->applyDateFilters($query, $dateDebut, $dateFin, 'date_paiement');

        return $query->get()->map(function (PaiementAgent $p) {
            $bordereau = $p->bordereau;

            return (object) [
                'key' => 'agent-'.$p->id,
                'id_sort' => (int) $p->id,
                'date_sort' => optional($p->date_paiement)->format('Y-m-d') ?: ($p->created_at?->format('Y-m-d') ?? '1970-01-01'),
                'date' => $p->date_paiement,
                'type' => 'agent',
                'type_label' => 'Paiement agent',
                'beneficiaire' => $bordereau?->agent_nom ?: ('Agent #'.$p->id_agent),
                'reference' => $bordereau?->numero ?: ($p->numero_recu ?: '—'),
                'mode' => $p->mode_paiement ?: '—',
                'montant' => (float) $p->montant,
                'note' => $p->commentaire ?: ($p->reference ?: '—'),
                'pdf_url' => route('gestionfinanciere.recus.pdf', $p->id),
                'detail_url' => $bordereau
                    ? route('gestionfinanciere.agent.show', ['id_agent' => $p->id_agent])
                    : null,
            ];
        });
    }

    /**
     * @return Collection<int, object>
     */
    private function paiementsParticuliers(string $search, string $dateDebut, string $dateFin): Collection
    {
        $query = PaiementParticulierAgent::query()->with('agent');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('reference', 'like', "%{$search}%")
                    ->orWhere('commentaire', 'like', "%{$search}%")
                    ->orWhereHas('agent', function ($a) use ($search) {
                        $a->where('nom', 'like', "%{$search}%")
                            ->orWhere('prenoms', 'like', "%{$search}%")
                            ->orWhere('numero_agent', 'like', "%{$search}%");
                    });
            });
        }

        $this->applyDateFilters($query, $dateDebut, $dateFin, 'date_paiement');

        return $query->get()->map(function (PaiementParticulierAgent $p) {
            $agent = $p->agent;
            $nom = $agent
                ? trim(($agent->nom ?? '').' '.($agent->prenoms ?? ''))
                : 'Particulier #'.$p->particulier_agent_id;

            return (object) [
                'key' => 'particulier-'.$p->id,
                'id_sort' => (int) $p->id,
                'date_sort' => optional($p->date_paiement)->format('Y-m-d') ?: '1970-01-01',
                'date' => $p->date_paiement,
                'type' => 'particulier',
                'type_label' => 'Paiement particulier',
                'beneficiaire' => $nom !== '' ? $nom : 'Particulier',
                'reference' => $p->reference ?: '—',
                'mode' => $p->mode_paiement ?: '—',
                'montant' => (float) $p->montant,
                'note' => $p->commentaire ?: '—',
                'pdf_url' => null,
                'detail_url' => null,
            ];
        });
    }

    /**
     * @return Collection<int, object>
     */
    private function paiementsTransporteurs(string $search, string $dateDebut, string $dateFin): Collection
    {
        $rows = collect();

        $queryAvances = AvanceTransporteur::query()->with('transporteur');
        if ($search !== '') {
            $queryAvances->where(function ($q) use ($search) {
                $q->where('reference', 'like', "%{$search}%")
                    ->orWhere('commentaire', 'like', "%{$search}%")
                    ->orWhereHas('transporteur', fn ($t) => $t->where('nom', 'like', "%{$search}%"));
            });
        }
        $this->applyDateFilters($queryAvances, $dateDebut, $dateFin, 'date_avance');

        $rows = $rows->merge($queryAvances->get()->map(function (AvanceTransporteur $avance) {
            return (object) [
                'key' => 'avance-transporteur-'.$avance->id,
                'id_sort' => (int) $avance->id,
                'date_sort' => optional($avance->date_avance)->format('Y-m-d') ?: '1970-01-01',
                'date' => $avance->date_avance,
                'type' => 'transporteur',
                'type_label' => 'Avance transporteur',
                'beneficiaire' => $avance->transporteur
                    ? trim(($avance->transporteur->nom ?? '').' '.($avance->transporteur->prenoms ?? ''))
                    : ('Transporteur #'.$avance->transporteur_id),
                'reference' => $avance->reference ?: '—',
                'mode' => $avance->mode_paiement ?: '—',
                'montant' => (float) $avance->montant,
                'note' => $avance->commentaire ?: 'Avance',
                'pdf_url' => null,
                'detail_url' => null,
            ];
        }));

        $queryGestion = PaiementTransporteurGestion::query()->with('transporteur');
        if ($search !== '') {
            $queryGestion->where(function ($q) use ($search) {
                $q->where('reference', 'like', "%{$search}%")
                    ->orWhere('commentaire', 'like', "%{$search}%")
                    ->orWhereHas('transporteur', fn ($t) => $t->where('nom', 'like', "%{$search}%"));
            });
        }
        $this->applyDateFilters($queryGestion, $dateDebut, $dateFin, 'date_paiement');

        $rows = $rows->merge($queryGestion->get()->map(function (PaiementTransporteurGestion $p) {
            return (object) [
                'key' => 'transporteur-gestion-'.$p->id,
                'id_sort' => (int) $p->id,
                'date_sort' => optional($p->date_paiement)->format('Y-m-d') ?: '1970-01-01',
                'date' => $p->date_paiement,
                'type' => 'transporteur',
                'type_label' => 'Paiement transporteur',
                'beneficiaire' => $p->transporteur
                    ? trim(($p->transporteur->nom ?? '').' '.($p->transporteur->prenoms ?? ''))
                    : ('Transporteur #'.$p->transporteur_id),
                'reference' => $p->reference ?: '—',
                'mode' => $p->mode_paiement ?: '—',
                'montant' => (float) $p->montant,
                'note' => $p->commentaire ?: '—',
                'pdf_url' => null,
                'detail_url' => null,
            ];
        }));

        $queryFiche = PaiementTransporteur::query();
        if ($search !== '') {
            $queryFiche->where(function ($q) use ($search) {
                $q->where('observation', 'like', "%{$search}%")
                    ->orWhere('matricule_vehicule', 'like', "%{$search}%");
            });
        }
        $this->applyDateFilters($queryFiche, $dateDebut, $dateFin, 'date_paiement');

        return $rows->merge($queryFiche->get()->map(function (PaiementTransporteur $p) {
            return (object) [
                'key' => 'transporteur-'.$p->id,
                'id_sort' => (int) $p->id,
                'date_sort' => optional($p->date_paiement)->format('Y-m-d') ?: '1970-01-01',
                'date' => $p->date_paiement,
                'type' => 'transporteur',
                'type_label' => 'Paiement transporteur',
                'beneficiaire' => $p->matricule_vehicule ?: 'Transporteur',
                'reference' => $p->id_bordereau ? ('Bordereau #'.$p->id_bordereau) : '—',
                'mode' => '—',
                'montant' => (float) $p->montant,
                'note' => $p->observation ?: '—',
                'pdf_url' => null,
                'detail_url' => null,
            ];
        }));
    }

    /**
     * @return Collection<int, object>
     */
    private function paiementsFournisseurs(string $search, string $dateDebut, string $dateFin): Collection
    {
        $query = PaiementFournisseur::query()->with('fournisseur');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('reference', 'like', "%{$search}%")
                    ->orWhere('commentaire', 'like', "%{$search}%")
                    ->orWhereHas('fournisseur', fn ($f) => $f->where('nom', 'like', "%{$search}%"));
            });
        }

        $this->applyDateFilters($query, $dateDebut, $dateFin, 'date_paiement');

        return $query->get()->map(function (PaiementFournisseur $p) {
            return (object) [
                'key' => 'fournisseur-'.$p->id,
                'id_sort' => (int) $p->id,
                'date_sort' => optional($p->date_paiement)->format('Y-m-d') ?: '1970-01-01',
                'date' => $p->date_paiement,
                'type' => 'fournisseur',
                'type_label' => 'Paiement fournisseur',
                'beneficiaire' => $p->fournisseur?->nom ?: ('Fournisseur #'.$p->fournisseur_id),
                'reference' => $p->reference ?: '—',
                'mode' => $p->mode_paiement ?: '—',
                'montant' => (float) $p->montant,
                'note' => $p->commentaire ?: '—',
                'pdf_url' => null,
                'detail_url' => null,
            ];
        });
    }

    /**
     * @return Collection<int, object>
     */
    private function paiementsChefsChargeurs(string $search, string $dateDebut, string $dateFin): Collection
    {
        $query = PaiementChefChargeur::query()->with('chefChargeur');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('reference', 'like', "%{$search}%")
                    ->orWhere('commentaire', 'like', "%{$search}%")
                    ->orWhereHas('chefChargeur', function ($c) use ($search) {
                        $c->where('nom', 'like', "%{$search}%")
                            ->orWhere('prenoms', 'like', "%{$search}%");
                    });
            });
        }

        $this->applyDateFilters($query, $dateDebut, $dateFin, 'date_paiement');

        return $query->get()->map(function (PaiementChefChargeur $p) {
            $chef = $p->chefChargeur;
            $nom = $chef ? trim(($chef->nom ?? '').' '.($chef->prenoms ?? '')) : ('Chef #'.$p->id_chef_chargeur);

            return (object) [
                'key' => 'chef-chargeur-'.$p->id,
                'id_sort' => (int) $p->id,
                'date_sort' => optional($p->date_paiement)->format('Y-m-d') ?: '1970-01-01',
                'date' => $p->date_paiement,
                'type' => 'chef_chargeur',
                'type_label' => 'Paiement chef chargeur',
                'beneficiaire' => $nom !== '' ? $nom : 'Chef chargeur',
                'reference' => $p->reference ?: '—',
                'mode' => $p->mode_paiement ?: '—',
                'montant' => (float) $p->montant,
                'note' => $p->commentaire ?: '—',
                'pdf_url' => null,
                'detail_url' => null,
            ];
        });
    }

    /**
     * @return Collection<int, object>
     */
    private function paiementsPisteurs(string $search, string $dateDebut, string $dateFin): Collection
    {
        if (! class_exists(PaiementPisteur::class)) {
            return collect();
        }

        try {
            $query = PaiementPisteur::query();
            if ($search !== '') {
                $query->where(function ($q) use ($search) {
                    $q->where('reference', 'like', "%{$search}%")
                        ->orWhere('commentaire', 'like', "%{$search}%");
                });
            }
            $this->applyDateFilters($query, $dateDebut, $dateFin, 'date_paiement');

            return $query->get()->map(function ($p) {
                return (object) [
                    'key' => 'pisteur-'.$p->id,
                    'id_sort' => (int) $p->id,
                    'date_sort' => optional($p->date_paiement)->format('Y-m-d') ?: '1970-01-01',
                    'date' => $p->date_paiement,
                    'type' => 'pisteur',
                    'type_label' => 'Paiement pisteur',
                    'beneficiaire' => 'Pisteur',
                    'reference' => $p->reference ?? '—',
                    'mode' => $p->mode_paiement ?? '—',
                    'montant' => (float) $p->montant,
                    'note' => $p->commentaire ?? '—',
                    'pdf_url' => null,
                    'detail_url' => null,
                ];
            });
        } catch (\Throwable) {
            return collect();
        }
    }

    private function applyDateFilters($query, string $dateDebut, string $dateFin, string $column): void
    {
        if ($dateDebut !== '') {
            $query->whereDate($column, '>=', $dateDebut);
        }
        if ($dateFin !== '') {
            $query->whereDate($column, '<=', $dateFin);
        }
    }
}
