<?php

namespace App\Http\Controllers;

use App\Models\Groupe;
use App\Models\GroupeAgent;
use App\Services\ChefEquipeContext;
use App\Services\MesAgentsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class GroupeController extends Controller
{
    public function __construct(
        private MesAgentsService $mesAgentsService,
    ) {}

    public function index()
    {
        $groupes = Groupe::withCount(['agents', 'agentsOrdinaires', 'agentsPisteurs'])
            ->orderBy('created_at', 'desc')
            ->get();

        return view('groupes.index', [
            'groupes' => $groupes,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nom_groupe' => ['required', 'string', 'max:255'],
        ]);

        Groupe::create($validated);

        return redirect()->route('groupes.index')
            ->with('success', 'Groupe créé avec succès.');
    }

    public function show(Request $request, int $id)
    {
        $groupe = Groupe::with('agents')->findOrFail($id);

        // Récupérer les agents du chef connecté depuis l'API
        $agentsApi = $this->mesAgentsService->fetchAllAgents([], $request);

        // Indexer les agents par id
        $agentsById = [];
        foreach ($agentsApi as $agent) {
            $idAgent = $agent['id_agent'] ?? 0;
            $agentsById[$idAgent] = $agent;
        }

        // Récupérer les IDs des agents déjà dans le groupe
        $agentsIdsInGroupe = $groupe->agents->pluck('id_agent')->toArray();

        // Filtrer les agents disponibles (non encore dans le groupe)
        $agentsDisponibles = array_filter($agentsApi, function($agent) use ($agentsIdsInGroupe) {
            return !in_array($agent['id_agent'] ?? 0, $agentsIdsInGroupe);
        });

        // Enrichir les agents du groupe avec les infos de l'API
        $agentsGroupe = [];
        foreach ($groupe->agents as $ga) {
            $agentInfo = $agentsById[$ga->id_agent] ?? null;
            $agentsGroupe[] = [
                'id' => $ga->id,
                'id_agent' => $ga->id_agent,
                'type_agent' => $ga->type_agent,
                'nom_agent' => $agentInfo['nom_complet'] ?? 'Agent #' . $ga->id_agent,
                'code_agent' => $agentInfo['numero_agent'] ?? '',
            ];
        }

        return view('groupes.show', [
            'groupe' => $groupe,
            'agentsGroupe' => $agentsGroupe,
            'agentsDisponibles' => array_values($agentsDisponibles),
        ]);
    }

    public function addAgent(Request $request, int $id)
    {
        $groupe = Groupe::findOrFail($id);

        $validated = $request->validate([
            'id_agent' => ['required', 'integer'],
            'type_agent' => ['required', 'in:ordinaire,pisteur'],
        ]);

        // Vérifier si l'agent n'est pas déjà dans le groupe
        $exists = GroupeAgent::where('groupe_id', $id)
            ->where('id_agent', $validated['id_agent'])
            ->exists();

        if ($exists) {
            return redirect()->route('groupes.show', $id)
                ->with('error', 'Cet agent est déjà dans le groupe.');
        }

        GroupeAgent::create([
            'groupe_id' => $id,
            'id_agent' => $validated['id_agent'],
            'type_agent' => $validated['type_agent'],
        ]);

        return redirect()->route('groupes.show', $id)
            ->with('success', 'Agent ajouté au groupe.');
    }

    public function removeAgent(int $id, int $agent_id)
    {
        $groupeAgent = GroupeAgent::where('groupe_id', $id)
            ->where('id', $agent_id)
            ->firstOrFail();

        $groupeAgent->delete();

        return redirect()->route('groupes.show', $id)
            ->with('success', 'Agent retiré du groupe.');
    }

    public function update(Request $request, int $id)
    {
        $groupe = Groupe::findOrFail($id);

        $validated = $request->validate([
            'nom_groupe' => ['required', 'string', 'max:255'],
        ]);

        $groupe->update($validated);

        return redirect()->route('groupes.index')
            ->with('success', 'Groupe renommé avec succès.');
    }

    public function destroy(int $id)
    {
        $groupe = Groupe::findOrFail($id);
        $groupe->delete();

        return redirect()->route('groupes.index')
            ->with('success', 'Groupe supprimé avec succès.');
    }

    public function tickets(Request $request, int $id, ChefEquipeContext $chefContext)
    {
        $groupe = Groupe::with('agents')->findOrFail($id);

        // Récupérer les noms des agents du groupe
        $agentIds = $groupe->agents->pluck('id_agent')->toArray();

        // Récupérer les fiches de sortie dont l'agent appartient au groupe
        $fichesIds = \App\Models\FicheSortie::whereIn('id_agent', $agentIds)
            ->pluck('id_ticket')
            ->toArray();

        // Récupérer les tickets depuis l'API
        $mesTicketsUrl = (string) config('services.external_auth.mes_tickets_url');
        $timeout = (int) config('services.external_auth.timeout', 10);
        $phpsessid = (string) $request->session()->get('external_auth.phpsessid', '');
        $ticketsApi = [];

        try {
            $response = Http::acceptJson()
                ->timeout($timeout)
                ->withHeaders(['Cookie' => 'PHPSESSID=' . $phpsessid])
                ->get($mesTicketsUrl, $chefContext->apiQueryParams($request));
            if ($response->successful()) {
                $ticketsApi = $response->json('tickets') ?? [];
            }
        } catch (\Throwable $e) {}

        // Filtrer les tickets qui ont une fiche de sortie avec un agent du groupe
        $ticketsGroupe = array_filter($ticketsApi, function($ticket) use ($fichesIds) {
            return in_array($ticket['id_ticket'] ?? 0, $fichesIds);
        });

        // Récupérer les fiches de sortie pour enrichir les tickets
        $fichesParTicket = [];
        $fiches = \App\Models\FicheSortie::whereIn('id_ticket', $fichesIds)->get();
        foreach ($fiches as $fiche) {
            $fichesParTicket[$fiche->id_ticket] = $fiche;
        }

        return view('groupes.tickets', [
            'groupe' => $groupe,
            'tickets' => array_values($ticketsGroupe),
            'fichesParTicket' => $fichesParTicket,
        ]);
    }
}
