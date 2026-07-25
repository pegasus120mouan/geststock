<?php

namespace App\Http\Controllers;

use App\Models\Commande;
use App\Models\Flacon;
use App\Models\Produit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CommandeController extends Controller
{
    public function index(Request $request)
    {
        $commandes = Commande::query()
            ->with(['user', 'produit', 'flacon'])
            ->when($request->filled('q'), function ($query) use ($request) {
                $q = '%'.$request->string('q').'%';
                $query->where(function ($inner) use ($q) {
                    $inner->where('reference', 'like', $q)
                        ->orWhere('client_nom', 'like', $q)
                        ->orWhere('client_telephone', 'like', $q)
                        ->orWhereHas('produit', fn ($p) => $p->where('nom', 'like', $q));
                });
            })
            ->when($request->filled('statut'), fn ($q) => $q->where('statut', $request->string('statut')))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $produits = Produit::query()
            ->where('statut', 'actif')
            ->orderBy('nom')
            ->get(['id', 'nom']);

        $flacons = Flacon::query()
            ->actif()
            ->orderBy('contenance_ml')
            ->get(['id', 'nom', 'contenance_ml']);

        return view('commandes.index', compact('commandes', 'produits', 'flacons'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'produit_id' => ['required', 'integer', Rule::exists('produits', 'id')],
            'flacon_id' => ['required', 'integer', Rule::exists('flacons', 'id')],
            'quantite' => ['required', 'integer', 'min:1'],
            'client_nom' => ['nullable', 'string', 'max:255'],
            'client_telephone' => ['required', 'string', 'max:50'],
            'statut' => ['required', 'in:en_attente,confirmee,livree,annulee'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $validated['total'] = 0;
        $validated['reference'] = $this->generateReference();
        $validated['user_id'] = Auth::id();

        Commande::query()->create($validated);

        return redirect()
            ->route('commandes.index')
            ->with('success', 'Commande créée avec succès.');
    }

    private function generateReference(): string
    {
        do {
            $reference = 'CMD-'.now()->format('Ymd').'-'.Str::upper(Str::random(4));
        } while (Commande::query()->where('reference', $reference)->exists());

        return $reference;
    }
}
