<?php

namespace App\Http\Controllers;

use App\Models\ParticulierAgent;
use App\Models\ParticulierGroupe;
use Illuminate\Database\UniqueConstraintViolationException;
use App\Services\ParticulierAgentsApiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class ParticulierController extends Controller
{
    private function prochainNumeroAgent(): string
    {
        $lastNumero = ParticulierAgent::query()
            ->where('numero_agent', 'like', 'AGP-%')
            ->orderByDesc('id')
            ->value('numero_agent');

        if ($lastNumero && preg_match('/^AGP-(\d+)$/', $lastNumero, $matches)) {
            $next = (int) $matches[1] + 1;
        } else {
            $next = ParticulierAgent::count() + 1;
        }

        return 'AGP-' . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    public function index()
    {
        $groupes = ParticulierGroupe::withCount('agents')
            ->orderBy('created_at', 'desc')
            ->get();

        return view('particuliers.index', [
            'groupes' => $groupes,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nom_groupe' => ['required', 'string', 'max:255'],
        ]);

        ParticulierGroupe::create($validated);

        return redirect()->route('particuliers.index')
            ->with('success', 'Groupe particulier créé avec succès.');
    }

    public function agentsIndex(Request $request)
    {
        $query = ParticulierAgent::with('groupe')
            ->orderBy('nom')
            ->orderBy('prenoms');

        if ($request->filled('particulier_groupe_id')) {
            $query->where('particulier_groupe_id', (int) $request->input('particulier_groupe_id'));
        }

        if ($request->filled('q')) {
            $q = $request->string('q')->toString();
            $query->where(function ($sub) use ($q) {
                $sub->where('numero_agent', 'like', "%{$q}%")
                    ->orWhere('nom', 'like', "%{$q}%")
                    ->orWhere('prenoms', 'like', "%{$q}%")
                    ->orWhere('contact', 'like', "%{$q}%")
                    ->orWhereHas('groupe', function ($groupeQuery) use ($q) {
                        $groupeQuery->where('nom_groupe', 'like', "%{$q}%");
                    });
            });
        }

        return view('particuliers.agents.index', [
            'agents' => $query->paginate(20)->withQueryString(),
            'groupes' => ParticulierGroupe::orderBy('nom_groupe')->get(),
            'prochainNumero' => $this->prochainNumeroAgent(),
        ]);
    }

    public function storeAgent(Request $request)
    {
        $fromApi = $request->filled('id_agent') && $request->filled('nom_api');

        if ($fromApi) {
            $request->validate([
                'particulier_groupe_id' => ['required', 'exists:particulier_groupes,id'],
                'id_agent' => ['required', 'integer'],
            ]);

            $groupeId = (int) $request->input('particulier_groupe_id');
            $idAgent = (int) $request->input('id_agent');
            $numeroAgent = (string) $request->input('numero_api', 'AGT-' . $idAgent);

            $existing = ParticulierAgent::query()
                ->where(function ($query) use ($idAgent, $numeroAgent) {
                    $query->where('id_agent', $idAgent)
                        ->orWhere('numero_agent', $numeroAgent);
                })
                ->with('groupe')
                ->first();

            if ($existing) {
                if ((int) $existing->particulier_groupe_id === $groupeId) {
                    $message = 'Cet agent est déjà dans ce groupe.';
                } else {
                    $groupeNom = $existing->groupe?->nom_groupe ?? 'un autre groupe';
                    $message = 'Cet agent est déjà affecté au groupe « ' . $groupeNom . ' ».';
                }

                return redirect()->route('particuliers.show', $groupeId)
                    ->withErrors(['agent' => $message]);
            }

            try {
                ParticulierAgent::create([
                    'particulier_groupe_id' => $groupeId,
                    'id_agent' => $idAgent,
                    'numero_agent' => $numeroAgent,
                    'nom' => (string) $request->input('nom_api', ''),
                    'prenoms' => (string) $request->input('prenoms_api', ''),
                    'contact' => (string) $request->input('contact_api', ''),
                ]);
            } catch (UniqueConstraintViolationException) {
                return redirect()->route('particuliers.show', $groupeId)
                    ->withErrors(['agent' => 'Cet agent est déjà enregistré dans un groupe particulier.']);
            }

            return redirect()->route('particuliers.show', $groupeId)
                ->with('success', 'Agent ajouté avec succès.');
        }

        $validated = $request->validate([
            'particulier_groupe_id' => ['required', 'exists:particulier_groupes,id'],
            'numero_agent' => ['required', 'string', 'max:50', 'unique:particulier_agents,numero_agent'],
            'nom' => ['required', 'string', 'max:100'],
            'prenoms' => ['required', 'string', 'max:150'],
            'contact' => ['nullable', 'string', 'max:50'],
        ]);

        try {
            ParticulierAgent::create($validated);
        } catch (UniqueConstraintViolationException) {
            return redirect()->back()
                ->withInput()
                ->withErrors(['numero_agent' => 'Ce numéro d\'agent est déjà utilisé.']);
        }

        $redirect = $request->input('redirect');
        if ($redirect === 'show') {
            return redirect()->route('particuliers.show', $validated['particulier_groupe_id'])
                ->with('success', 'Agent ajouté avec succès.');
        }

        return redirect()->route('particuliers.agents.index')
            ->with('success', 'Agent créé avec succès.');
    }

    public function updateAgent(Request $request, ParticulierAgent $agent)
    {
        $validated = $request->validate([
            'particulier_groupe_id' => ['required', 'exists:particulier_groupes,id'],
            'numero_agent' => ['required', 'string', 'max:50', 'unique:particulier_agents,numero_agent,' . $agent->id],
            'nom' => ['required', 'string', 'max:100'],
            'prenoms' => ['required', 'string', 'max:150'],
            'contact' => ['nullable', 'string', 'max:50'],
        ]);

        $agent->update($validated);

        return redirect()->route('particuliers.agents.index')
            ->with('success', 'Agent modifié avec succès.');
    }

    public function destroyAgent(ParticulierAgent $agent)
    {
        $agent->delete();

        return redirect()->back()->with('success', 'Agent supprimé avec succès.');
    }

    private function extraireMotCleGroupe(string $nomGroupe): string
    {
        return app(ParticulierAgentsApiService::class)->extraireMotCleGroupe($nomGroupe);
    }

    private function filtrerAgentsApiPourGroupe(string $nomGroupe, array $agentsApi): array
    {
        return app(ParticulierAgentsApiService::class)->filtrerPourGroupe($nomGroupe, $agentsApi);
    }

    private function fetchAgentsApi(Request $request): array
    {
        return app(ParticulierAgentsApiService::class)->fetchAll($request);
    }

    public function show(Request $request, int $id)
    {
        $groupe = ParticulierGroupe::with('agents')->findOrFail($id);

        $agentsApi = $this->fetchAgentsApi($request);
        $agentsGroupeApi = $this->filtrerAgentsApiPourGroupe($groupe->nom_groupe, $agentsApi);

        $idsDejaUtilises = ParticulierAgent::query()
            ->whereNotNull('id_agent')
            ->pluck('id_agent')
            ->map(fn ($idAgent) => (int) $idAgent)
            ->unique()
            ->values()
            ->all();

        $agentsDisponibles = array_values(array_filter($agentsGroupeApi, function ($a) use ($idsDejaUtilises) {
            return !in_array((int) ($a['id_agent'] ?? 0), $idsDejaUtilises, true);
        }));

        $agentsLocauxByIdAgent = $groupe->agents->keyBy(fn ($a) => (int) ($a->id_agent ?? 0));

        return view('particuliers.show', [
            'groupe' => $groupe,
            'agentsGroupeApi' => $agentsGroupeApi,
            'agentsLocauxByIdAgent' => $agentsLocauxByIdAgent,
            'agentsDisponibles' => $agentsDisponibles,
            'agentsApiTotal' => count($agentsApi),
            'prochainNumero' => $this->prochainNumeroAgent(),
        ]);
    }

    public function update(Request $request, int $id)
    {
        $groupe = ParticulierGroupe::findOrFail($id);

        $validated = $request->validate([
            'nom_groupe' => ['required', 'string', 'max:255'],
        ]);

        $groupe->update($validated);

        return redirect()->route('particuliers.index')
            ->with('success', 'Groupe renommé avec succès.');
    }

    public function destroy(int $id)
    {
        $groupe = ParticulierGroupe::findOrFail($id);
        $groupe->delete();

        return redirect()->route('particuliers.index')
            ->with('success', 'Groupe particulier supprimé avec succès.');
    }
}
