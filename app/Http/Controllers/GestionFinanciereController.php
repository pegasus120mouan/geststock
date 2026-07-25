<?php

namespace App\Http\Controllers;

use App\Models\DemandeSortie;
use App\Models\MouvementSolde;
use App\Models\User;
use App\Services\ChefEquipeSession;
use App\Services\HistoriqueTransactionsService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class GestionFinanciereController extends Controller
{
    public function __construct(
        private readonly HistoriqueTransactionsService $historiqueTransactions,
    ) {}

    public function index(Request $request)
    {
        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'type' => trim((string) $request->query('type', '')),
            'date_debut' => trim((string) $request->query('date_debut', '')),
            'date_fin' => trim((string) $request->query('date_fin', '')),
        ];

        $all = $this->historiqueTransactions->allTransactions($request);
        $totalMontant = (float) $all->sum('montant');

        $page = max(1, (int) $request->query('page', 1));
        $perPage = 20;
        $transactions = new \Illuminate\Pagination\LengthAwarePaginator(
            $all->sortByDesc(fn ($row) => sprintf('%s-%010d', $row->date_sort, $row->id_sort))
                ->values()
                ->slice(($page - 1) * $perPage, $perPage)
                ->values(),
            $all->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()],
        );

        return view('gestionfinanciere.index', [
            'transactions' => $transactions,
            'filters' => $filters,
            'totalMontant' => $totalMontant,
        ]);
    }

    public function store(Request $request, ChefEquipeSession $chefSession)
    {
        $user = $this->resolveUser($request, $chefSession);

        $rawMontant = (string) $request->input('montant', '');
        $normalizedMontant = preg_replace('/\s+/u', '', $rawMontant);
        $normalizedMontant = str_replace([',', '.'], '', (string) $normalizedMontant);
        $request->merge(['montant' => $normalizedMontant]);

        $validated = $request->validate([
            'type' => ['required', 'in:deposit,withdraw'],
            'montant' => ['required', 'integer', 'min:1'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        MouvementSolde::create([
            'user_id' => $user->id,
            'type' => $validated['type'],
            'montant' => $validated['montant'],
            'note' => $validated['note'] ?? null,
        ]);

        $label = $validated['type'] === 'deposit' ? 'Dépôt' : 'Retrait';

        return redirect()
            ->route('gestionfinanciere.index')
            ->with('success', $label.' de '.number_format((int) $validated['montant'], 0, ',', ' ').' FCFA enregistré.');
    }

    public function destroy(Request $request, MouvementSolde $mouvement, ChefEquipeSession $chefSession)
    {
        $user = $this->resolveUser($request, $chefSession);

        abort_if($mouvement->user_id !== $user->id, 403);

        $mouvement->delete();

        return redirect()
            ->route('gestionfinanciere.index')
            ->with('success', 'Transaction supprimée.');
    }

    public function sorties(Request $request)
    {
        $demandes = DemandeSortie::query()
            ->orderByDesc('date_demande')
            ->paginate(20)
            ->withQueryString();

        return view('gestionfinanciere.sorties', [
            'demandes' => $demandes,
        ]);
    }

    public function storeSortie(Request $request)
    {
        $rawMontant = (string) $request->input('montant', '');
        $normalizedMontant = str_replace([' ', "\u{00A0}"], '', $rawMontant);
        $normalizedMontant = str_replace(',', '.', $normalizedMontant);
        $request->merge(['montant' => $normalizedMontant]);

        $validated = $request->validate([
            'montant' => ['required', 'numeric', 'min:0.01'],
            'motif' => ['required', 'string'],
        ]);

        $now = Carbon::now();

        do {
            $candidate = 'DS-' . $now->format('YmdHis') . '-' . random_int(100, 999);
        } while (DemandeSortie::query()->where('numero_demande', $candidate)->exists());

        DemandeSortie::create([
            'numero_demande' => $candidate,
            'date_demande' => $now,
            'montant' => $validated['montant'],
            'motif' => $validated['motif'],
            'statut' => 'en_attente',
            'montant_payer' => null,
            'montant_reste' => $validated['montant'],
        ]);

        return redirect()->route('gestionfinanciere.sorties');
    }

    private function resolveUser(Request $request, ChefEquipeSession $chefSession): User
    {
        $user = $request->user();
        if ($user instanceof User) {
            return $user;
        }

        $chef = $chefSession->chef($request);
        abort_unless($chef, 403, 'Session chef d\'équipe introuvable.');

        $idChef = (int) ($chef['id_chef'] ?? 0);
        $login = trim((string) ($chef['login'] ?? ''));
        abort_unless($idChef > 0 || $login !== '', 403, 'Identifiant chef d\'équipe invalide.');

        $userQuery = User::query();
        if ($idChef > 0) {
            $userQuery->where('id_chef', $idChef);
        }
        if ($login !== '') {
            $userQuery->when(
                $idChef > 0,
                fn ($query) => $query->orWhere('login', $login),
                fn ($query) => $query->where('login', $login),
            );
        }
        $user = $userQuery->first();

        if ($user) {
            return $user;
        }

        $loginToUse = $login !== '' ? $login : 'chef-' . $idChef;
        if (User::query()->where('login', $loginToUse)->exists()) {
            $loginToUse = 'chef-' . $idChef . '-' . Str::lower(Str::random(4));
        }

        return User::create([
            'name' => (string) ($chef['nom'] ?? 'Chef'),
            'prenom' => $chef['prenoms'] ?? null,
            'login' => $loginToUse,
            'id_chef' => $idChef > 0 ? $idChef : null,
            'chef_equipe_token' => (string) ($chef['token'] ?? ''),
            'password' => Hash::make(Str::random(32)),
            'role' => 'agent',
        ]);
    }
}
