<?php

namespace App\Http\Controllers;

use App\Models\PrixAgent;
use App\Models\Produit;
use App\Services\ChefEquipeContext;
use App\Services\MesAgentsService;
use App\Services\UsinesParProduitService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class AgentController extends Controller
{
    public function __construct(
        private UsinesParProduitService $usinesParProduitService,
        private MesAgentsService $mesAgentsService,
        private ChefEquipeContext $chefContext,
    ) {}

    /**
     * @param  Collection<int, PrixAgent>  $prixCollection
     * @param  Collection<int, Produit>  $produits
     * @return list<array{id: int|string, nom: string, prix: list<PrixAgent>}>
     */
    private function grouperPrixParProduit(Collection $prixCollection, Collection $produits): array
    {
        $groupes = $produits->map(fn (Produit $produit) => [
            'id' => $produit->id,
            'nom' => $produit->nom,
            'prix' => [],
        ])->all();

        $sansProduit = [
            'id' => 'sans',
            'nom' => 'Sans produit',
            'prix' => [],
        ];

        foreach ($prixCollection as $prix) {
            $place = false;

            if ($prix->produit_id) {
                foreach ($groupes as &$groupe) {
                    if ($groupe['id'] === $prix->produit_id) {
                        $groupe['prix'][] = $prix;
                        $place = true;
                        break;
                    }
                }
                unset($groupe);
            }

            if (!$place && $prix->nom_produit) {
                $nomPrix = mb_strtolower(trim((string) $prix->nom_produit), 'UTF-8');
                foreach ($groupes as &$groupe) {
                    if (mb_strtolower((string) $groupe['nom'], 'UTF-8') === $nomPrix) {
                        $groupe['prix'][] = $prix;
                        $place = true;
                        break;
                    }
                }
                unset($groupe);
            }

            if (!$place) {
                $sansProduit['prix'][] = $prix;
            }
        }

        usort($groupes, function ($a, $b) {
            $countA = count($a['prix']);
            $countB = count($b['prix']);
            if ($countA !== $countB) {
                return $countB <=> $countA;
            }

            return strcasecmp($a['nom'], $b['nom']);
        });

        if (count($sansProduit['prix']) > 0) {
            $groupes[] = $sansProduit;
        }

        return $groupes;
    }

    private function periodesSeChevauchent(?string $dateDebutA, ?string $dateFinA, ?string $dateDebutB, ?string $dateFinB): bool
    {
        $debutA = $dateDebutA ? Carbon::parse($dateDebutA)->startOfDay() : Carbon::create(1900, 1, 1);
        $finA = $dateFinA ? Carbon::parse($dateFinA)->endOfDay() : Carbon::create(2100, 12, 31)->endOfDay();
        $debutB = $dateDebutB ? Carbon::parse($dateDebutB)->startOfDay() : Carbon::create(1900, 1, 1);
        $finB = $dateFinB ? Carbon::parse($dateFinB)->endOfDay() : Carbon::create(2100, 12, 31)->endOfDay();

        return $debutA->lte($finB) && $debutB->lte($finA);
    }

    private function prixAgentEnConflit(
        int $idAgent,
        int $produitId,
        string $nomUsine,
        string $type,
        ?string $dateDebut,
        ?string $dateFin,
        ?int $excludePrixId = null
    ): ?PrixAgent {
        $query = PrixAgent::query()
            ->where('id_agent', $idAgent)
            ->where('produit_id', $produitId)
            ->where('nom_usine', $nomUsine)
            ->where('type', $type);

        if ($excludePrixId) {
            $query->where('id', '!=', $excludePrixId);
        }

        foreach ($query->get() as $existant) {
            if ($this->periodesSeChevauchent(
                $dateDebut,
                $dateFin,
                $existant->date_debut?->format('Y-m-d'),
                $existant->date_fin?->format('Y-m-d')
            )) {
                return $existant;
            }
        }

        return null;
    }

    private function formaterPeriodePrix(?PrixAgent $prix): string
    {
        if (!$prix) {
            return '';
        }

        $debut = $prix->date_debut ? $prix->date_debut->format('d/m/Y') : '…';
        $fin = $prix->date_fin ? $prix->date_fin->format('d/m/Y') : '…';

        return "{$debut} au {$fin}";
    }

    public function index(Request $request)
    {
        $token = $this->mesAgentsService->resolveToken($request);
        $chef = $this->chefContext->resolveChef($request);
        $page = max(1, (int) $request->query('page', 1));
        $search = trim((string) $request->query('search', ''));
        $idChef = (int) $request->query('id_chef', 0);
        $sousGroupe = strtolower(trim((string) $request->query('sous_groupe', '')));
        if (! in_array($sousGroupe, ['particulier', 'professionnel'], true)) {
            $sousGroupe = '';
        }

        $result = $this->mesAgentsService->listAgents([
            'token' => $token,
            'id_chef' => $idChef,
            'search' => $search,
            'sous_groupe' => $sousGroupe,
            'page' => $page,
        ]);

        $titre = match ($sousGroupe) {
            'particulier' => 'Pisteurs particuliers',
            'professionnel' => 'Pisteurs professionnels',
            default => 'Liste des agents',
        };

        return view('agents.index', [
            'agents' => $result['agents'],
            'chefs' => $result['chefs'],
            'pagination' => $result['pagination'],
            'external_error' => $result['error'],
            'chefToken' => $token,
            'chefActif' => $chef,
            'sousGroupe' => $sousGroupe,
            'titre' => $titre,
        ]);
    }

    public function apiIndex(Request $request)
    {
        $token = $this->mesAgentsService->resolveToken($request);
        $result = $this->mesAgentsService->listAgents([
            'token' => $token,
            'id_chef' => (int) $request->query('id_chef', 0),
            'search' => trim((string) $request->query('search', '')),
            'page' => max(1, (int) $request->query('page', 1)),
            'per_page' => max(1, min(100, (int) $request->query('per_page', 15))),
        ]);

        if ($result['error']) {
            return response()->json([
                'success' => false,
                'error' => $result['error'],
            ], 502);
        }

        return response()->json([
            'success' => true,
            'agents' => $result['agents'],
            'chefs' => $result['chefs'],
            'pagination' => $result['pagination'],
        ]);
    }

    public function show(Request $request, int $id_agent)
    {
        $agent = $this->mesAgentsService->findAgentById($id_agent);

        if (!$agent) {
            return redirect()->route('agents.index')->withErrors(['error' => 'Agent non trouvé.']);
        }

        $produits = Produit::orderBy('nom')->get();
        $usinesParProduit = $this->usinesParProduitService->usinesParProduitPourSelect();

        $orderPrix = fn ($q) => $q->orderBy('nom_produit')->orderBy('nom_usine')->orderBy('date_debut');

        $prixTransporteur = $orderPrix(PrixAgent::where('id_agent', $id_agent)->where('type', 'transporteur'))->get();
        $prixPgf = $orderPrix(PrixAgent::where('id_agent', $id_agent)->where('type', 'pgf'))->get();
        $prixAutreCamionSeuls = $orderPrix(PrixAgent::where('id_agent', $id_agent)->where('type', 'autre_camion'))->get();
        // Ancien type "transporteur" = non-PGF → traité comme autre_camion
        $prixAutreCamion = $prixAutreCamionSeuls->merge($prixTransporteur)->values();

        // Uniquement 2 grilles : Autre Camion (non-PGF) et Camion PGF
        $codesTransporteurs = collect([
            (object) ['nom' => 'Autre Camion'],
            (object) ['nom' => 'Camion PGF'],
        ]);
        $typeParCodeNom = [
            'Autre Camion' => 'autre_camion',
            'Camion PGF' => 'pgf',
        ];

        $prixParTypeSlug = [
            'pgf' => $this->grouperPrixParProduit($prixPgf, $produits),
            'autre_camion' => $this->grouperPrixParProduit($prixAutreCamion, $produits),
        ];

        $prixCountsParType = [
            'pgf' => $prixPgf->count(),
            'autre_camion' => $prixAutreCamion->count(),
        ];

        return view('agents.show', [
            'agent' => $agent,
            'produits' => $produits,
            'usinesParProduit' => $usinesParProduit,
            'codesTransporteurs' => $codesTransporteurs,
            'typeParCodeNom' => $typeParCodeNom,
            'prixParTypeSlug' => $prixParTypeSlug,
            'prixCountsParType' => $prixCountsParType,
            'prixAll' => $prixPgf->merge($prixAutreCamion),
            'prixTransporteur' => $prixTransporteur,
            'prixPgf' => $prixPgf,
            'prixAutreCamion' => $prixAutreCamion,
        ]);
    }

    public function storePrix(Request $request, int $id_agent)
    {
        $validated = $request->validate([
            'produit_id' => ['required', 'integer', 'exists:produits,id'],
            'id_usine' => ['required', 'string'],
            'nom_usine' => ['required', 'string'],
            'type' => ['required', 'in:pgf,autre_camion'],
            'prix' => ['required', 'numeric', 'min:0'],
            'date_debut' => ['nullable', 'date'],
            'date_fin' => ['nullable', 'date', 'after_or_equal:date_debut'],
            'toutes_usines' => ['nullable', 'in:0,1'],
        ]);

        $produit = Produit::findOrFail((int) $validated['produit_id']);
        $produitId = (int) $produit->id;
        $nomProduit = $produit->nom;

        $payloadBase = [
            'id_agent' => $id_agent,
            'produit_id' => $produitId,
            'nom_produit' => $nomProduit,
            'type' => $validated['type'],
            'prix' => $validated['prix'],
            'date_debut' => $validated['date_debut'],
            'date_fin' => $validated['date_fin'],
        ];

        if ($request->input('toutes_usines') === '1' || $validated['id_usine'] === 'all') {
            $usinesProduit = $this->usinesParProduitService->usinesPourProduitId($produitId);

            if ($usinesProduit === []) {
                return redirect()->route('agents.show', ['id_agent' => $id_agent])
                    ->withErrors(['error' => 'Aucune usine associée à ce produit (API ou locale).']);
            }

            $count = 0;
            $ignored = 0;
            foreach ($usinesProduit as $usine) {
                if ($this->prixAgentEnConflit(
                    $id_agent,
                    $produitId,
                    $usine['nom'],
                    $validated['type'],
                    $validated['date_debut'] ?? null,
                    $validated['date_fin'] ?? null
                )) {
                    $ignored++;
                    continue;
                }

                PrixAgent::create(array_merge($payloadBase, [
                    'id_usine' => $usine['id_usine'],
                    'nom_usine' => $usine['nom'],
                ]));
                $count++;
            }

            if ($count === 0) {
                return redirect()->route('agents.show', ['id_agent' => $id_agent])
                    ->withErrors(['error' => $ignored > 0
                        ? "Aucun prix ajouté : {$ignored} usine(s) ont déjà un prix sur une période qui chevauche."
                        : 'Aucune usine disponible pour ce produit.']);
            }

            $message = "Prix ajouté pour {$count} usine(s) du produit « {$nomProduit} ».";
            if ($ignored > 0) {
                $message .= " ({$ignored} usine(s) ignorée(s) — période en conflit.)";
            }

            return redirect()->route('agents.show', ['id_agent' => $id_agent])
                ->with('success', $message);
        }

        $idUsine = $validated['id_usine'];
        if (!$this->usinesParProduitService->usineAppartientAuProduit(
            $produitId,
            $idUsine,
            $validated['nom_usine']
        )) {
            return redirect()->route('agents.show', ['id_agent' => $id_agent])
                ->withErrors(['error' => "L'usine sélectionnée n'est pas associée à ce produit."]);
        }

        $conflit = $this->prixAgentEnConflit(
            $id_agent,
            $produitId,
            $validated['nom_usine'],
            $validated['type'],
            $validated['date_debut'] ?? null,
            $validated['date_fin'] ?? null
        );

        if ($conflit) {
            return redirect()->route('agents.show', ['id_agent' => $id_agent])
                ->withErrors(['error' => 'Cette période chevauche un prix existant pour cette usine ('
                    . $this->formaterPeriodePrix($conflit) . ').']);
        }

        PrixAgent::create(array_merge($payloadBase, [
            'id_usine' => is_numeric($idUsine) ? (int) $idUsine : $idUsine,
            'nom_usine' => $validated['nom_usine'],
        ]));

        return redirect()->route('agents.show', ['id_agent' => $id_agent])
            ->with('success', 'Prix ajouté avec succès.');
    }

    public function updatePrix(Request $request, int $id_agent, int $prix_id)
    {
        $validated = $request->validate([
            'prix' => ['required', 'numeric', 'min:0'],
            'date_debut' => ['nullable', 'date'],
            'date_fin' => ['nullable', 'date', 'after_or_equal:date_debut'],
        ]);

        $prix = PrixAgent::where('id', $prix_id)->where('id_agent', $id_agent)->first();

        if (!$prix) {
            return redirect()->route('agents.show', ['id_agent' => $id_agent])
                ->withErrors(['error' => 'Prix non trouvé.']);
        }

        $conflit = $this->prixAgentEnConflit(
            $id_agent,
            (int) $prix->produit_id,
            (string) $prix->nom_usine,
            (string) $prix->type,
            $validated['date_debut'] ?? null,
            $validated['date_fin'] ?? null,
            $prix->id
        );

        if ($conflit) {
            return redirect()->route('agents.show', ['id_agent' => $id_agent])
                ->withErrors(['error' => 'Cette période chevauche un autre prix pour cette usine ('
                    . $this->formaterPeriodePrix($conflit) . ').']);
        }

        $prix->update($validated);

        return redirect()->route('agents.show', ['id_agent' => $id_agent])
            ->with('success', 'Prix modifié avec succès.');
    }

    public function deletePrix(int $id_agent, int $prix_id)
    {
        $prix = PrixAgent::where('id', $prix_id)->where('id_agent', $id_agent)->first();
        
        if ($prix) {
            $prix->delete();
            return redirect()->route('agents.show', ['id_agent' => $id_agent])
                ->with('success', 'Prix supprimé avec succès.');
        }

        return redirect()->route('agents.show', ['id_agent' => $id_agent])
            ->withErrors(['error' => 'Prix non trouvé.']);
    }
}
