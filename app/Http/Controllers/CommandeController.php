<?php

namespace App\Http\Controllers;

use App\Enums\VolumeUnit;
use App\Models\Cocktail;
use App\Models\Commande;
use App\Models\CommandeLigne;
use App\Models\Commune;
use App\Models\CoutLivraison;
use App\Models\Flacon;
use App\Models\PrixUnitaire;
use App\Models\Produit;
use App\Models\StockMouvement;
use App\Services\OvlIntegrationService;
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
        if ($request->has('edit') || $request->has('create')) {
            return redirect()->route('home');
        }

        return view('commandes.index', $this->donneesListeCommandes($request) + [
            'editId' => null,
            'openSection' => $this->sectionDepuisRequete($request),
            'openCreate' => (bool) session('open_create'),
        ]);
    }

    public function show(Request $request, string $commande)
    {
        if ($request->query()) {
            return redirect()->route('home');
        }

        if (! ctype_digit($commande)) {
            return redirect()->route('commandes.index');
        }

        $commande = Commande::query()->find((int) $commande);

        if (! $commande) {
            return redirect()->route('commandes.index');
        }

        $commande->load(['lignes.produit', 'lignes.flacon', 'commune', 'cocktail']);
        $data = $this->donneesListeCommandes($request);
        $data['allCommandes'] = $data['allCommandes']
            ->prepend($commande)
            ->unique('id')
            ->values();

        return view('commandes.index', $data + [
            'editId' => $commande->id,
            'openSection' => $this->sectionPourCommande($commande),
            'openCreate' => false,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validateCommande($request);
        $lignesPreparees = $this->preparerLignes($validated);

        if ($lignesPreparees instanceof \Illuminate\Http\RedirectResponse) {
            return $lignesPreparees;
        }

        $validated['type'] = $this->typeDepuisLignes($lignesPreparees);
        $validated['cocktail_id'] = $this->cocktailIdDepuisGroupes($validated['groupes_cocktail'] ?? []);

        $fraisLivraison = $this->fraisLivraisonPourCommune((int) $validated['commune_id']);
        $totalArticles = round(collect($lignesPreparees)->sum('total'), 2);
        $totalCommande = round($totalArticles + $fraisLivraison, 2);

        try {
            DB::transaction(function () use ($validated, $lignesPreparees, $totalCommande, $fraisLivraison) {
                $commande = Commande::query()->create([
                    'date_commande' => $validated['date_commande'],
                    'type' => $validated['type'],
                    'cocktail_id' => $validated['cocktail_id'] ?? null,
                    'client_nom' => $validated['client_nom'] ?? null,
                    'client_telephone' => $validated['client_telephone'],
                    'commune_id' => $validated['commune_id'],
                    'frais_livraison' => $fraisLivraison,
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
                ->route('commandes.index')
                ->with('open_create', true)
                ->withInput()
                ->withErrors($e->errors());
        }

        return $this->redirectListe(
            in_array($validated['type'], [Commande::TYPE_COCKTAIL, Commande::TYPE_MIXTE], true)
                ? 'cocktail'
                : 'en_gros'
        )->with('success', 'Commande créée avec succès.');
    }

    public function update(Request $request, Commande $commande)
    {
        $validated = $this->validateCommande($request);
        $lignesPreparees = $this->preparerLignes($validated, $commande->id);

        if ($lignesPreparees instanceof \Illuminate\Http\RedirectResponse) {
            return $lignesPreparees;
        }

        $validated['type'] = $this->typeDepuisLignes($lignesPreparees);
        $validated['cocktail_id'] = $this->cocktailIdDepuisGroupes($validated['groupes_cocktail'] ?? []);

        $fraisLivraison = $this->fraisLivraisonPourCommune((int) $validated['commune_id']);
        $totalArticles = round(collect($lignesPreparees)->sum('total'), 2);
        $totalCommande = round($totalArticles + $fraisLivraison, 2);
        $section = in_array($validated['type'], [Commande::TYPE_COCKTAIL, Commande::TYPE_MIXTE], true)
            ? 'cocktail'
            : $request->input('section', 'en_gros');
        $ancienStatut = $commande->statut;
        $nouveauStatut = $validated['statut'];

        try {
            DB::transaction(function () use (
                $commande,
                $validated,
                $lignesPreparees,
                $totalCommande,
                $fraisLivraison,
                $ancienStatut,
                $nouveauStatut
            ) {
                $commande = Commande::query()->lockForUpdate()->findOrFail($commande->id);
                $commande->load(['lignes.produit', 'lignes.flacon']);

                if ($ancienStatut === 'livree') {
                    $this->restaurerStock($commande);
                }

                $commande->update([
                    'date_commande' => $validated['date_commande'],
                    'type' => $validated['type'],
                    'cocktail_id' => $validated['cocktail_id'] ?? null,
                    'client_nom' => $validated['client_nom'] ?? null,
                    'client_telephone' => $validated['client_telephone'],
                    'commune_id' => $validated['commune_id'],
                    'frais_livraison' => $fraisLivraison,
                    'statut' => $nouveauStatut,
                    'notes' => $validated['notes'] ?? null,
                    'total' => $totalCommande,
                ]);

                $commande->lignes()->delete();
                foreach ($lignesPreparees as $ligne) {
                    $commande->lignes()->create($ligne);
                }

                $commande->load(['lignes.produit', 'lignes.flacon']);

                if ($nouveauStatut === 'livree') {
                    $this->deduireStock($commande);
                }
            });
        } catch (ValidationException $e) {
            return redirect()
                ->route('commandes.show', $commande)
                ->withInput()
                ->withErrors($e->errors());
        }

        return $this->redirectListe($section)->with('success', 'Commande mise à jour.');
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
            return $this->redirectListe($section);
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
            return $this->redirectListe($section)->withErrors($e->errors());
        }

        return $this->redirectListe($section)->with('success', 'Statut de la commande mis à jour.');
    }

    public function envoyerOvl(Request $request, Commande $commande, OvlIntegrationService $ovl)
    {
        $section = $request->input('section', 'en_gros');
        $commande->loadMissing('commune');

        try {
            $result = $ovl->envoyerCommande($commande);
        } catch (\Throwable $e) {
            return $this->redirectListe($section)->with('error', $e->getMessage());
        }

        $message = $result['message'];
        if ($result['id']) {
            $message .= ' (OVL #'.$result['id'].')';
        }

        return $this->redirectListe($section)->with('success', $message);
    }

    private function validateCommande(Request $request): array
    {
        return $request->validate([
            'date_commande' => ['required', 'date'],
            'client_nom' => ['nullable', 'string', 'max:255'],
            'client_telephone' => ['required', 'string', 'max:50'],
            'commune_id' => ['required', 'integer', Rule::exists('communes', 'id')],
            'statut' => ['required', 'in:en_attente,confirmee,livree,annulee'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'lignes' => ['nullable', 'array'],
            'lignes.*.produit_id' => ['nullable', 'integer', Rule::exists('produits', 'id')],
            'lignes.*.flacon_id' => ['nullable', 'integer', Rule::exists('flacons', 'id')],
            'lignes.*.categorie' => ['nullable', Rule::in(array_keys(PrixUnitaire::categories()))],
            'lignes.*.quantite' => ['nullable', 'integer', 'min:1'],
            'groupes_cocktail' => ['nullable', 'array'],
            'groupes_cocktail.*.categorie' => ['nullable', Rule::in(array_keys(PrixUnitaire::categories()))],
            'groupes_cocktail.*.flacon_id' => ['nullable', 'integer', Rule::exists('flacons', 'id')],
            'groupes_cocktail.*.quantite' => ['nullable', 'integer', 'min:1'],
            'groupes_cocktail.*.cocktail_id' => ['nullable', 'integer', Rule::exists('cocktails', 'id')],
            'groupes_cocktail.*.parfums' => ['nullable', 'array'],
            'groupes_cocktail.*.parfums.*.produit_id' => ['nullable', 'integer', Rule::exists('produits', 'id')],
            'groupes_cocktail.*.parfums.*.quantite_ml' => ['nullable', 'numeric', 'gt:0'],
        ], [
            'commune_id.required' => 'Sélectionnez une commune.',
            'lignes.*.produit_id.required' => 'Sélectionnez un parfum.',
            'lignes.*.flacon_id.required' => 'Sélectionnez une contenance.',
            'groupes_cocktail.*.parfums.*.quantite_ml.gt' => 'La quantité d’un parfum doit être supérieure à 0 ml.',
        ]);
    }

    private function fraisLivraisonPourCommune(int $communeId): float
    {
        $cout = CoutLivraison::query()
            ->where('commune_id', $communeId)
            ->where('statut', 'actif')
            ->first();

        if (! $cout) {
            throw ValidationException::withMessages([
                'commune_id' => 'Aucun coût de livraison actif pour cette commune.',
            ]);
        }

        return round((float) $cout->montant, 2);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<int, array<string, mixed>>|\Illuminate\Http\RedirectResponse
     */
    private function preparerLignes(array $validated, ?int $editId = null)
    {
        $preparees = [];

        $normales = collect($validated['lignes'] ?? [])
            ->filter(fn ($ligne) => filled($ligne['produit_id'] ?? null))
            ->all();

        if ($normales !== []) {
            $result = $this->preparerLignesNormales($normales, $editId);
            if ($result instanceof \Illuminate\Http\RedirectResponse) {
                return $result;
            }
            $preparees = array_merge($preparees, $result);
        }

        foreach ($validated['groupes_cocktail'] ?? [] as $gIndex => $groupe) {
            $parfums = collect($groupe['parfums'] ?? [])
                ->filter(fn ($parfum) => filled($parfum['produit_id'] ?? null) && (float) ($parfum['quantite_ml'] ?? 0) > 0)
                ->all();

            $aDesParfumsSansQte = collect($groupe['parfums'] ?? [])
                ->contains(fn ($parfum) => filled($parfum['produit_id'] ?? null) && (float) ($parfum['quantite_ml'] ?? 0) <= 0);

            if ($parfums === [] && (filled($groupe['cocktail_id'] ?? null) || $aDesParfumsSansQte)) {
                return $this->redirectErreursLignes([
                    "groupes_cocktail.$gIndex.parfums" => 'Indiquez la quantité (ml) de chaque parfum du cocktail.',
                ], $editId);
            }

            if ($parfums === []) {
                continue;
            }

            $result = $this->preparerUnGroupeCocktail($groupe, $parfums, (int) $gIndex, $editId);
            if ($result instanceof \Illuminate\Http\RedirectResponse) {
                return $result;
            }
            $preparees = array_merge($preparees, $result);
        }

        if ($preparees === []) {
            return $this->redirectErreursLignes([
                'lignes' => 'Ajoutez au moins un parfum normal ou un cocktail à la commande.',
            ], $editId);
        }

        return $preparees;
    }

    /**
     * @param  array<int, array<string, mixed>>  $lignesPreparees
     */
    private function typeDepuisLignes(array $lignesPreparees): string
    {
        $hasCocktail = collect($lignesPreparees)->contains(fn ($ligne) => ($ligne['quantite_ml'] ?? null) !== null);
        $hasNormal = collect($lignesPreparees)->contains(fn ($ligne) => ($ligne['quantite_ml'] ?? null) === null);

        if ($hasCocktail && $hasNormal) {
            return Commande::TYPE_MIXTE;
        }

        return $hasCocktail ? Commande::TYPE_COCKTAIL : Commande::TYPE_NORMALE;
    }

    /**
     * @param  array<int, array<string, mixed>>  $groupes
     */
    private function cocktailIdDepuisGroupes(array $groupes): ?int
    {
        $ids = collect($groupes)
            ->pluck('cocktail_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        return $ids->count() === 1 ? $ids->first() : null;
    }

    /**
     * @param  array<int, array<string, mixed>>  $lignes
     * @return array<int, array<string, mixed>>|\Illuminate\Http\RedirectResponse
     */
    private function preparerLignesNormales(array $lignes, ?int $editId = null)
    {
        $lignesPreparees = [];
        $erreurs = [];
        $produits = Produit::query()
            ->whereIn('id', collect($lignes)->pluck('produit_id')->unique()->filter())
            ->get()
            ->keyBy('id');
        $flacons = Flacon::query()
            ->whereIn('id', collect($lignes)->pluck('flacon_id')->unique()->filter())
            ->get()
            ->keyBy('id');

        foreach ($lignes as $index => $ligne) {
            $qte = (int) ($ligne['quantite'] ?? 0);
            if ($qte < 1 || empty($ligne['flacon_id']) || empty($ligne['categorie'])) {
                $erreurs["lignes.$index.produit_id"] = 'Catégorie, contenance et quantité sont obligatoires pour chaque parfum.';
                continue;
            }

            $tarif = PrixUnitaire::trouver(
                (int) $ligne['produit_id'],
                (int) $ligne['flacon_id'],
                $ligne['categorie']
            );

            if (! $tarif) {
                $produit = $produits->get((int) $ligne['produit_id']);
                $flacon = $flacons->get((int) $ligne['flacon_id']);
                $parfum = $produit?->nom ?: 'inconnu';
                $contenance = $flacon
                    ? ((int) $flacon->contenance_ml).' ml'
                    : 'inconnue';
                $categoriePrix = $ligne['categorie'] === PrixUnitaire::CATEGORIE_EN_GROS
                    ? 'gros'
                    : 'détail';

                $erreurs["lignes.$index.produit_id"] = "Parfum : {$parfum}, contenance : {$contenance}, prix {$categoriePrix} manquant.";

                continue;
            }

            $prix = (float) $tarif->prix;
            $qte = (int) $ligne['quantite'];

            $lignesPreparees[] = [
                'produit_id' => (int) $ligne['produit_id'],
                'flacon_id' => (int) $ligne['flacon_id'],
                'categorie' => $ligne['categorie'],
                'quantite' => $qte,
                'quantite_ml' => null,
                'prix_unitaire' => $prix,
                'total' => round($prix * $qte, 2),
            ];
        }

        if ($erreurs !== []) {
            return $this->redirectErreursLignes($erreurs, $editId);
        }

        return $lignesPreparees;
    }

    /**
     * @param  array<string, mixed>  $groupe
     * @param  array<int, array<string, mixed>>  $parfums
     * @return array<int, array<string, mixed>>|\Illuminate\Http\RedirectResponse
     */
    private function preparerUnGroupeCocktail(array $groupe, array $parfums, int $gIndex, ?int $editId = null)
    {
        $flaconId = (int) ($groupe['flacon_id'] ?? 0);
        $categorie = $groupe['categorie'] ?? '';
        $nbFlacons = (int) ($groupe['quantite'] ?? 0);

        if ($flaconId <= 0 || ! in_array($categorie, array_keys(PrixUnitaire::categories()), true) || $nbFlacons < 1) {
            return $this->redirectErreursLignes([
                "groupes_cocktail.$gIndex.flacon_id" => 'Catégorie, contenance et nombre de flacons sont obligatoires pour un cocktail.',
            ], $editId);
        }

        $flacon = Flacon::query()->find($flaconId);
        $contenance = (int) ($flacon?->contenance_ml ?? 0);
        $labelPrix = $categorie === PrixUnitaire::CATEGORIE_EN_GROS ? 'gros' : 'détail';
        $erreurs = [];

        if ($contenance <= 0) {
            return $this->redirectErreursLignes([
                "groupes_cocktail.$gIndex.flacon_id" => 'Contenance invalide pour ce cocktail.',
            ], $editId);
        }

        $parParfum = [];
        foreach ($parfums as $index => $ligne) {
            $produitId = (int) $ligne['produit_id'];
            $ml = round((float) $ligne['quantite_ml'], 2);

            if (isset($parParfum[$produitId])) {
                $parParfum[$produitId]['ml'] = round($parParfum[$produitId]['ml'] + $ml, 2);
                continue;
            }

            $parParfum[$produitId] = [
                'index' => $index,
                'produit_id' => $produitId,
                'ml' => $ml,
            ];
        }

        if (count($parParfum) < 2) {
            return $this->redirectErreursLignes([
                "groupes_cocktail.$gIndex.parfums" => 'Un cocktail doit associer au moins deux parfums différents.',
            ], $editId);
        }

        $volumeTotal = round(collect($parParfum)->sum('ml'), 2);

        if (abs($volumeTotal - $contenance) > 0.01) {
            $ecart = round($volumeTotal - $contenance, 2);
            $message = $ecart > 0
                ? "Le cocktail fait {$volumeTotal} ml, soit {$ecart} ml de trop pour un flacon de {$contenance} ml."
                : "Le cocktail fait {$volumeTotal} ml : il manque ".abs($ecart)." ml pour remplir le flacon de {$contenance} ml.";

            return $this->redirectErreursLignes([
                "groupes_cocktail.$gIndex.flacon_id" => $message,
            ], $editId);
        }

        $produits = Produit::query()
            ->whereIn('id', array_keys($parParfum))
            ->get()
            ->keyBy('id');

        $lignesPreparees = [];

        foreach ($parParfum as $item) {
            $index = $item['index'];
            $produit = $produits->get($item['produit_id']);
            $parfum = $produit?->nom ?: 'inconnu';

            $tarif = PrixUnitaire::trouver($item['produit_id'], $flaconId, $categorie);

            if (! $tarif) {
                $erreurs["groupes_cocktail.$gIndex.parfums.$index.produit_id"] = "Parfum : {$parfum}, contenance : {$contenance} ml, prix {$labelPrix} manquant.";
                continue;
            }

            $part = $item['ml'] / $contenance;
            $prixUnitaire = round((float) $tarif->prix * $part, 2);

            $lignesPreparees[] = [
                'produit_id' => $item['produit_id'],
                'flacon_id' => $flaconId,
                'categorie' => $categorie,
                'quantite' => $nbFlacons,
                'quantite_ml' => $item['ml'],
                'prix_unitaire' => $prixUnitaire,
                'total' => round($prixUnitaire * $nbFlacons, 2),
            ];
        }

        if ($erreurs !== []) {
            return $this->redirectErreursLignes($erreurs, $editId);
        }

        return $lignesPreparees;
    }

    /**
     * @param  array<string, string>  $erreurs
     */
    private function redirectErreursLignes(array $erreurs, ?int $editId = null): \Illuminate\Http\RedirectResponse
    {
        if ($editId) {
            return redirect()
                ->route('commandes.show', $editId)
                ->withInput()
                ->withErrors($erreurs);
        }

        return redirect()
            ->route('commandes.index')
            ->with('open_create', true)
            ->withInput()
            ->withErrors($erreurs);
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
                ($ligne->isCocktail()
                    ? ' (cocktail '.$ligne->quantite_ml.' ml × '.$ligne->quantite.' — '.$ligne->categorieLabel().')'
                    : ' ('.$ligne->flacon->contenance_ml.' ml × '.$ligne->quantite.
                        ' — '.$ligne->categorieLabel().')'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function donneesListeCommandes(Request $request): array
    {
        $baseQuery = Commande::query()
            ->with(['lignes.produit', 'lignes.flacon', 'commune', 'cocktail'])
            ->withCount('lignes')
            ->when($request->filled('q'), function ($query) use ($request) {
                $q = '%'.$request->string('q').'%';
                $query->where(function ($inner) use ($q) {
                    $inner->where('reference', 'like', $q)
                        ->orWhere('client_nom', 'like', $q)
                        ->orWhere('client_telephone', 'like', $q)
                        ->orWhereHas('commune', fn ($c) => $c->where('nom', 'like', $q))
                        ->orWhereHas('cocktail', fn ($c) => $c->where('nom', 'like', $q))
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

        $commandesCocktail = (clone $baseQuery)
            ->where(function ($query) {
                $query->whereIn('type', [Commande::TYPE_COCKTAIL, Commande::TYPE_MIXTE])
                    ->orWhereHas('lignes', fn ($l) => $l->whereNotNull('quantite_ml'));
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

        $communes = Commune::query()
            ->actif()
            ->with(['coutLivraison' => fn ($q) => $q->where('statut', 'actif')])
            ->whereHas('coutLivraison', fn ($q) => $q->where('statut', 'actif'))
            ->orderBy('nom')
            ->get(['id', 'nom']);

        $cocktails = Cocktail::query()
            ->where('statut', 'actif')
            ->with(['lignes.produit'])
            ->orderBy('nom')
            ->get();

        return [
            'commandesEnGros' => $commandesEnGros,
            'commandesDetail' => $commandesDetail,
            'commandesCocktail' => $commandesCocktail,
            'allCommandes' => $commandesEnGros
                ->concat($commandesDetail)
                ->concat($commandesCocktail)
                ->unique('id')
                ->values(),
            'produits' => $produits,
            'flacons' => $flacons,
            'communes' => $communes,
            'cocktails' => $cocktails,
            'cocktailsCatalog' => $cocktails->map(fn (Cocktail $cocktail) => $cocktail->toCatalogArray())->values(),
        ];
    }

    private function sectionDepuisRequete(Request $request): string
    {
        $section = (string) session('section', $request->input('section', 'en_gros'));

        return in_array($section, ['en_gros', 'detail', 'cocktail'], true) ? $section : 'en_gros';
    }

    private function sectionPourCommande(Commande $commande): string
    {
        $commande->loadMissing('lignes');

        if ($commande->hasLignesCocktail()) {
            return 'cocktail';
        }

        if ($commande->hasCategorie(PrixUnitaire::CATEGORIE_DETAIL)
            && ! $commande->hasCategorie(PrixUnitaire::CATEGORIE_EN_GROS)) {
            return 'detail';
        }

        return 'en_gros';
    }

    private function redirectListe(?string $section = null): \Illuminate\Http\RedirectResponse
    {
        $redirect = redirect()->route('commandes.index');

        if (in_array($section, ['en_gros', 'detail', 'cocktail'], true)) {
            $redirect->with('section', $section);
        }

        return $redirect;
    }

    private function generateReference(): string
    {
        do {
            $reference = 'CMD-'.now()->format('Ymd').'-'.Str::upper(Str::random(4));
        } while (Commande::query()->where('reference', $reference)->exists());

        return $reference;
    }
}
