<?php

namespace App\Http\Controllers;

use App\Services\ChefEquipeContext;
use App\Services\FinancementService;
use App\Services\MesAgentsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FinancementController extends Controller
{
    public function __construct(
        private readonly FinancementService $financementService,
        private readonly MesAgentsService $mesAgentsService,
        private readonly ChefEquipeContext $chefContext,
    ) {}

    public function index(Request $request): View
    {
        $filters = $this->listFiltersFromRequest($request);
        $filters['page'] = max(1, (int) $request->query('page', 1));

        $summaries = $this->financementService->paginatedAgentSummaries($filters);
        $financements = $this->financementService->detailedList($filters);
        $agents = $this->financementService->listAgentsForSelect($filters);

        $chef = $this->chefContext->resolveChef($request);
        $chefToken = $this->mesAgentsService->resolveToken($request);

        $externalError = $agents === [] && $summaries->total() === 0
            ? 'Aucun agent disponible pour ce chef d\'équipe.'
            : null;

        return view('financements.index', compact(
            'summaries',
            'financements',
            'filters',
            'agents',
            'externalError',
            'chef',
            'chefToken',
        ));
    }

    public function show(Request $request, int $id_agent): View|RedirectResponse
    {
        $chefFilters = $this->chefFilters($request);

        if (! $this->financementService->agentAccessible($id_agent, $chefFilters)) {
            return redirect()->route('financements.index')
                ->withErrors(['error' => 'Agent non trouvé ou hors de votre équipe.']);
        }

        $agent = $this->financementService->findAgent($id_agent);
        if (! $agent) {
            return redirect()->route('financements.index')
                ->withErrors(['error' => 'Agent non trouvé.']);
        }

        $filters = array_merge($chefFilters, [
            'search' => trim((string) $request->input('search', '')),
            'type_filter' => $request->input('type_filter', ''),
            'date_debut' => $request->input('date_debut'),
            'date_fin' => $request->input('date_fin'),
        ]);

        $stats = $this->financementService->statsForAgent($id_agent);
        $financements = $this->financementService->paginatedAgentHistory($id_agent, $filters);
        $agents = $this->financementService->listAgentsForSelect($chefFilters);

        return view('financements.show', compact(
            'agent',
            'stats',
            'financements',
            'filters',
            'agents',
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        $chefFilters = $this->chefFilters($request);

        if ($request->has('montant')) {
            $request->merge([
                'montant' => preg_replace('/\s+/', '', (string) $request->input('montant')),
            ]);
        }

        $validated = $request->validate([
            'id_agent' => ['required', 'integer', 'min:1'],
            'montant' => ['required', 'numeric', 'min:1'],
            'motif' => ['required', 'string', 'max:1000'],
            'redirect_to' => ['nullable', 'string'],
        ]);

        if (! $this->financementService->agentAccessible((int) $validated['id_agent'], $chefFilters)) {
            return back()->withErrors(['id_agent' => 'Agent introuvable ou hors de votre équipe.'])->withInput();
        }

        try {
            $this->financementService->create(
                (int) $validated['id_agent'],
                (float) $validated['montant'],
                $validated['motif'],
            );
        } catch (\Throwable $e) {
            return back()->withErrors(['error' => 'Impossible d\'enregistrer le financement : ' . $e->getMessage()])->withInput();
        }

        $redirectTo = $validated['redirect_to'] ?? route('financements.index');
        if (! is_string($redirectTo) || ! str_starts_with($redirectTo, url('/financements'))) {
            $redirectTo = route('financements.index');
        }

        return redirect($redirectTo)->with('success', 'Financement enregistré avec succès.');
    }

    /**
     * @return array<string, mixed>
     */
    private function listFiltersFromRequest(Request $request): array
    {
        return array_merge($this->chefFilters($request), [
            'search' => trim((string) $request->input('search', '')),
            'agent_id' => $request->input('agent_id'),
            'date_debut' => $request->input('date_debut'),
            'date_fin' => $request->input('date_fin'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function chefFilters(Request $request): array
    {
        $token = $this->mesAgentsService->resolveToken($request);
        $idChef = (int) $request->query('id_chef', 0);

        $filters = [];
        if ($token !== '') {
            $filters['token'] = $token;
        }
        if ($idChef > 0) {
            $filters['id_chef'] = $idChef;
        }

        return $filters;
    }
}
