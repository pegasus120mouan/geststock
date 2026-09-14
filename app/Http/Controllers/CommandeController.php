<?php

namespace App\Http\Controllers;

use App\Enums\VolumeUnit;
use App\Models\Commande;
use App\Models\CommandeLigne;
use App\Models\Flacon;
use App\Models\PrixUnitaire;
use App\Models\Produit;
use App\Models\StockMouvement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CommandeController extends Controller
{
    public function index(Request $request)
    {
        $baseQuery = Commande::query()
            ->with(['lignes.produit', 'lignes.flacon'])
            ->withCount('lignes')
            ->when($request->filled('q'), function ($query) use ($request) {
                $q = '%'.$request->string('q').'%';
                $query->where(function ($inner) use ($q) {
                    $inner->where('reference', 'like', $q)
                        ->orWhere('client_nom', 'like', $q)
                        ->orWhere('client_telephone', 'like', $q)
                        ->orWhereHas('lignes.produit', fn ($p) => $p->where('nom', 'like', $q));
                });
            })
            ->when($request->filled('statut'), fn ($q) => $q->where('statut', $request->string('statut')))
            ->latest();

        $commandesEnGros = (clone $baseQuery)
            ->whereHas('lignes', fn ($q) => $q->where('categorie', PrixUnitaire::CATEGORIE_EN_GROS))
            ->get();

        $commandesDetail = (clone $baseQuery)
            ->whereHas('lignes', fn ($q) => $q->where('categorie', PrixUnitaire::CATEGORIE_DETAIL))
            ->get();

        $produits = Produit::query()
            ->where('statut', 'actif')
            ->orderBy('nom')
            ->get(['id', 'nom']);

        $flacons = Flacon::query()
            ->actif()
            ->orderBy('contenance_ml')
            ->get(['id', 'nom', 'contenance_ml']);

        return view('commandes.index', compact('commandesEnGros', 'commandesDetail', 'produits', 'flacons'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'date_commande' => ['required', 'date'],
            'client_nom' => ['nullable', 'string', 'max:255'],
            'client_telephone' => ['required', 'string', 'max:50'],
            'statut' => ['required', 'in:en_attente,confirmee,livree,annulee'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'lignes' => ['required', 'array', 'min:1'],
            'lignes.*.produit_id' => ['required', 'integer', Rule::exists('produits', 'id')],
            'lignes.*.flacon_id' => ['required', 'integer', Rule::exists('flacons', 'id')],
            'lignes.*.categorie' => ['required', Rule::in(array_keys(PrixUnitaire::categories()))],
            'lignes.*.quantite' => ['required', 'integer', 'min:1'],
        ], [
            'lignes.required' => 'Ajoutez au moins un parfum à la commande.',
            'lignes.*.produit_id.required' => 'Sélectionnez un parfum.',
            'lignes.*.flacon_id.required' => 'Sélectionnez une contenance.',
            'lignes.*.categorie.required' => 'Sélectionnez une catégorie.',
            'lignes.*.quantite.required' => 'Indiquez la quantité.',
        ]);

        $lignesPreparees = [];

        foreach ($validated['lignes'] as $index => $ligne) {
            $tarif = PrixUnitaire::trouver(
                (int) $ligne['produit_id'],
                (int) $ligne['flacon_id'],
                $ligne['categorie']
            );

            if (! $tarif) {
                $label = $ligne['categorie'] === PrixUnitaire::CATEGORIE_EN_GROS ? 'en gros' : 'détail';

                return redirect()
                    ->route('commandes.index', ['create' => 1])
                    ->withInput()
                    ->withErrors([
                        "lignes.$index.produit_id" => "Aucun prix {$label} pour cette ligne (parfum + contenance).",
                    ]);
            }

            $prix = (float) $tarif->prix;
            $qte = (int) $ligne['quantite'];

            $lignesPreparees[] = [
                'produit_id' => (int) $ligne['produit_id'],
                'flacon_id' => (int) $ligne['flacon_id'],
                'categorie' => $ligne['categorie'],
                'quantite' => $qte,
                'prix_unitaire' => $prix,
                'total' => round($prix * $qte, 2),
            ];
        }

        $totalCommande = round(collect($lignesPreparees)->sum('total'), 2);

        try {
            DB::transaction(function () use ($validated, $lignesPreparees, $totalCommande) {
                $commande = Commande::query()->create([
                    'date_commande' => $validated['date_commande'],
                    'client_nom' => $validated['client_nom'] ?? null,
                    'client_telephone' => $validated['client_telephone'],
                    'statut' => $validated['statut'],
                    'notes' => $validated['notes'] ?? null,
                    'total' => $totalCommande,
                    'reference' => $this->generateReference(),
                    'user_id' => Auth::id(),
                ]);

                foreach ($lignesPreparees as $ligne) {
                    $commande->lignes()->create($ligne);
                }

                if ($commande->statut === 'livree') {
                    $commande->load(['lignes.produit', 'lignes.flacon']);
                    $this->deduireStock($commande);
                }
            });
        } catch (ValidationException $e) {
            return redirect()
                ->route('commandes.index', ['create' => 1])
                ->withInput()
                ->withErrors($e->errors());
        }

        return redirect()
            ->route('commandes.index')
            ->with('success', 'Commande créée avec succès.');
    }

    public function updateStatut(Request $request, Commande $commande)
    {
        $validated = $request->validate([
            'statut' => ['required', 'in:en_attente,confirmee,livree,annulee'],
        ]);

        $nouveauStatut = $validated['statut'];
        $ancienStatut = $commande->statut;
        $section = $request->input('section', 'en_gros');

        if ($ancienStatut === $nouveauStatut) {
            return redirect()->route('commandes.index', ['section' => $section]);
        }

        try {
            DB::transaction(function () use ($commande, $ancienStatut, $nouveauStatut) {
                $commande = Commande::query()->lockForUpdate()->findOrFail($commande->id);
                $commande->load(['lignes.produit', 'lignes.flacon']);

                if ($ancienStatut !== 'livree' && $nouveauStatut === 'livree') {
                    $this->deduireStock($commande);
                }

                if ($ancienStatut === 'livree' && $nouveauStatut !== 'livree') {
                    $this->restaurerStock($commande);
                }

                $commande->update(['statut' => $nouveauStatut]);
            });
        } catch (ValidationException $e) {
            return redirect()
                ->route('commandes.index', ['section' => $section])
                ->withErrors($e->errors());
        }

        return redirect()
            ->route('commandes.index', ['section' => $section])
            ->with('success', 'Statut de la commande mis à jour.');
    }

    private function deduireStock(Commande $commande): void
    {
        if ($commande->lignes->isEmpty()) {
            throw ValidationException::withMessages([
                'statut' => 'Impossible de livrer : aucune ligne de commande.',
            ]);
        }

        foreach ($commande->lignes as $ligne) {
            $this->appliquerMouvementStock($commande, $ligne, 'sortie');
        }
    }

    private function restaurerStock(Commande $commande): void
    {
        foreach ($commande->lignes as $ligne) {
            $this->appliquerMouvementStock($commande, $ligne, 'entree');
        }
    }

    private function appliquerMouvementStock(Commande $commande, CommandeLigne $ligne, string $type): void
    {
        if (! $ligne->produit_id || ! $ligne->flacon) {
            throw ValidationException::withMessages([
                'statut' => 'Impossible de modifier le stock : parfum ou contenance manquant sur une ligne.',
            ]);
        }

        $volumeMl = $ligne->volumeMl();

        if ($volumeMl <= 0) {
            throw ValidationException::withMessages([
                'statut' => 'Volume invalide sur une ligne de commande.',
            ]);
        }

        $produit = Produit::query()->lockForUpdate()->findOrFail($ligne->produit_id);
        $stockAvant = (float) $produit->stock_ml;

        if ($type === 'sortie' && $stockAvant < $volumeMl) {
            throw ValidationException::withMessages([
                'statut' => sprintf(
                    'Stock insuffisant pour %s : %s ml requis, %s ml disponibles.',
                    $produit->nom,
                    number_format($volumeMl, 0, ',', ' '),
                    number_format($stockAvant, 0, ',', ' ')
                ),
            ]);
        }

        $stockApres = $type === 'sortie'
            ? round($stockAvant - $volumeMl, 2)
            : round($stockAvant + $volumeMl, 2);

        $produit->update(['stock_ml' => $stockApres]);

        StockMouvement::query()->create([
            'produit_id' => $produit->id,
            'user_id' => Auth::id(),
            'type' => $type,
            'quantite' => $volumeMl,
            'unite' => VolumeUnit::Ml,
            'quantite_ml' => $volumeMl,
            'stock_avant' => $stockAvant,
            'stock_apres' => $stockApres,
            'commentaire' => ($type === 'sortie' ? 'Livraison' : 'Annulation livraison').
                ' commande '.$commande->reference.
                ' ('.$ligne->flacon->contenance_ml.' ml × '.$ligne->quantite.
                ' — '.$ligne->categorieLabel().')',
        ]);
    }

    private function generateReference(): string
    {
        do {
            $reference = 'CMD-'.now()->format('Ymd').'-'.Str::upper(Str::random(4));
        } while (Commande::query()->where('reference', $reference)->exists());

        return $reference;
    }
}
