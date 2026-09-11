<?php

namespace App\Http\Controllers;

use App\Enums\VolumeUnit;
use App\Models\Commande;
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
        // Corrige les anciennes commandes enregistrées à 0 alors qu'un tarif existe.
        Commande::query()
            ->where(function ($q) {
                $q->where('prix_unitaire', '<=', 0)->orWhere('total', '<=', 0);
            })
            ->whereNotNull('produit_id')
            ->whereNotNull('flacon_id')
            ->orderBy('id')
            ->chunkById(50, function ($commandes) {
                foreach ($commandes as $commande) {
                    $commande->synchroniserPrixDepuisTarif();
                }
            });

        $baseQuery = Commande::query()
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
            ->latest();

        $commandesEnGros = (clone $baseQuery)
            ->where('categorie', PrixUnitaire::CATEGORIE_EN_GROS)
            ->get();

        $commandesDetail = (clone $baseQuery)
            ->where(function ($q) {
                $q->where('categorie', PrixUnitaire::CATEGORIE_DETAIL)
                    ->orWhereNull('categorie')
                    ->orWhere('categorie', '');
            })
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
            'produit_id' => ['required', 'integer', Rule::exists('produits', 'id')],
            'flacon_id' => ['required', 'integer', Rule::exists('flacons', 'id')],
            'categorie' => ['required', Rule::in(array_keys(PrixUnitaire::categories()))],
            'quantite' => ['required', 'integer', 'min:1'],
            'date_commande' => ['required', 'date'],
            'client_nom' => ['nullable', 'string', 'max:255'],
            'client_telephone' => ['required', 'string', 'max:50'],
            'statut' => ['required', 'in:en_attente,confirmee,livree,annulee'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $tarif = PrixUnitaire::trouver(
            (int) $validated['produit_id'],
            (int) $validated['flacon_id'],
            $validated['categorie']
        );

        if (! $tarif) {
            $label = $validated['categorie'] === PrixUnitaire::CATEGORIE_EN_GROS ? 'en gros' : 'détail';

            return redirect()
                ->route('commandes.index', ['create' => 1, 'section' => $validated['categorie']])
                ->withInput()
                ->withErrors([
                    'produit_id' => "Aucun prix {$label} défini pour ce parfum et cette contenance. Renseignez-le d'abord.",
                ]);
        }

        $prixUnitaire = (float) $tarif->prix;
        $montant = round($prixUnitaire * (int) $validated['quantite'], 2);

        try {
            DB::transaction(function () use ($validated, $prixUnitaire, $montant) {
                $commande = Commande::query()->create([
                    ...$validated,
                    'prix_unitaire' => $prixUnitaire,
                    'total' => $montant,
                    'reference' => $this->generateReference(),
                    'user_id' => Auth::id(),
                ]);

                if ($commande->statut === 'livree') {
                    $commande->load(['produit', 'flacon']);
                    $this->deduireStock($commande);
                }
            });
        } catch (ValidationException $e) {
            return redirect()
                ->route('commandes.index', ['create' => 1, 'section' => $validated['categorie']])
                ->withInput()
                ->withErrors($e->errors());
        }

        return redirect()
            ->route('commandes.index', ['section' => $validated['categorie']])
            ->with('success', 'Commande créée avec succès.');
    }

    public function updateStatut(Request $request, Commande $commande)
    {
        $validated = $request->validate([
            'statut' => ['required', 'in:en_attente,confirmee,livree,annulee'],
        ]);

        $nouveauStatut = $validated['statut'];
        $ancienStatut = $commande->statut;

        if ($ancienStatut === $nouveauStatut) {
            return redirect()->route('commandes.index', ['section' => $commande->categorie ?: 'detail']);
        }

        try {
            DB::transaction(function () use ($commande, $ancienStatut, $nouveauStatut) {
                $commande = Commande::query()->lockForUpdate()->findOrFail($commande->id);
                $commande->load(['produit', 'flacon']);

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
                ->route('commandes.index', ['section' => $commande->categorie ?: 'detail'])
                ->withErrors($e->errors());
        }

        return redirect()
            ->route('commandes.index', ['section' => $commande->categorie ?: 'detail'])
            ->with('success', 'Statut de la commande mis à jour.');
    }

    private function volumeCommandeMl(Commande $commande): float
    {
        $contenance = (int) ($commande->flacon?->contenance_ml ?? 0);
        $quantite = (int) $commande->quantite;

        return round($contenance * $quantite, 2);
    }

    private function deduireStock(Commande $commande): void
    {
        if (! $commande->produit_id || ! $commande->flacon) {
            throw ValidationException::withMessages([
                'statut' => 'Impossible de livrer : parfum ou contenance manquant.',
            ]);
        }

        $volumeMl = $this->volumeCommandeMl($commande);

        if ($volumeMl <= 0) {
            throw ValidationException::withMessages([
                'statut' => 'Volume à déduire invalide.',
            ]);
        }

        $produit = Produit::query()->lockForUpdate()->findOrFail($commande->produit_id);
        $stockAvant = (float) $produit->stock_ml;

        if ($stockAvant < $volumeMl) {
            throw ValidationException::withMessages([
                'statut' => sprintf(
                    'Stock insuffisant pour %s : %s ml requis, %s ml disponibles.',
                    $produit->nom,
                    number_format($volumeMl, 0, ',', ' '),
                    number_format($stockAvant, 0, ',', ' ')
                ),
            ]);
        }

        $stockApres = round($stockAvant - $volumeMl, 2);
        $produit->update(['stock_ml' => $stockApres]);

        StockMouvement::query()->create([
            'produit_id' => $produit->id,
            'user_id' => Auth::id(),
            'type' => 'sortie',
            'quantite' => $volumeMl,
            'unite' => VolumeUnit::Ml,
            'quantite_ml' => $volumeMl,
            'stock_avant' => $stockAvant,
            'stock_apres' => $stockApres,
            'commentaire' => 'Livraison commande '.$commande->reference.
                ' ('.$commande->flacon->contenance_ml.' ml × '.$commande->quantite.')',
        ]);
    }

    private function restaurerStock(Commande $commande): void
    {
        if (! $commande->produit_id || ! $commande->flacon) {
            return;
        }

        $volumeMl = $this->volumeCommandeMl($commande);

        if ($volumeMl <= 0) {
            return;
        }

        $produit = Produit::query()->lockForUpdate()->findOrFail($commande->produit_id);
        $stockAvant = (float) $produit->stock_ml;
        $stockApres = round($stockAvant + $volumeMl, 2);
        $produit->update(['stock_ml' => $stockApres]);

        StockMouvement::query()->create([
            'produit_id' => $produit->id,
            'user_id' => Auth::id(),
            'type' => 'entree',
            'quantite' => $volumeMl,
            'unite' => VolumeUnit::Ml,
            'quantite_ml' => $volumeMl,
            'stock_avant' => $stockAvant,
            'stock_apres' => $stockApres,
            'commentaire' => 'Annulation livraison commande '.$commande->reference.
                ' ('.$commande->flacon->contenance_ml.' ml × '.$commande->quantite.')',
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
