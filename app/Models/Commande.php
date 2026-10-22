<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class Commande extends Model
{
    use HasFactory;

    public const TYPE_NORMALE = 'normale';

    public const TYPE_COCKTAIL = 'cocktail';

    public const TYPE_MIXTE = 'mixte';

    protected $fillable = [
        'reference',
        'type',
        'cocktail_id',
        'date_commande',
        'client_nom',
        'client_telephone',
        'commune_id',
        'frais_livraison',
        'statut',
        'total',
        'notes',
        'ovl_commande_id',
        'ovl_sent_at',
        'user_id',
    ];

    protected $attributes = [
        'type' => self::TYPE_NORMALE,
    ];

    protected function casts(): array
    {
        return [
            'date_commande' => 'date',
            'frais_livraison' => 'decimal:2',
            'total' => 'decimal:2',
            'ovl_sent_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function commune(): BelongsTo
    {
        return $this->belongsTo(Commune::class);
    }

    public function cocktail(): BelongsTo
    {
        return $this->belongsTo(Cocktail::class);
    }

    public function lignes(): HasMany
    {
        return $this->hasMany(CommandeLigne::class)->orderBy('id');
    }

    public function isCocktail(): bool
    {
        return in_array($this->type, [self::TYPE_COCKTAIL, self::TYPE_MIXTE], true);
    }

    public function isMixte(): bool
    {
        return $this->type === self::TYPE_MIXTE
            || ($this->hasLignesCocktail() && $this->hasLignesNormales());
    }

    public function hasLignesCocktail(): bool
    {
        return $this->lignes->contains(fn (CommandeLigne $l) => $l->quantite_ml !== null);
    }

    public function hasLignesNormales(): bool
    {
        return $this->lignes->contains(fn (CommandeLigne $l) => $l->quantite_ml === null);
    }

    public function montantArticles(): float
    {
        return round((float) $this->lignes->sum(fn (CommandeLigne $l) => $l->montant()), 2);
    }

    public function montant(): float
    {
        if ((float) $this->total > 0) {
            return (float) $this->total;
        }

        return round($this->montantArticles() + (float) $this->frais_livraison, 2);
    }

    public function recalculerTotal(): void
    {
        $this->forceFill([
            'total' => round($this->montantArticles() + (float) $this->frais_livraison, 2),
        ])->save();
    }

    public function hasCategorie(string $categorie): bool
    {
        return $this->lignes->contains(fn (CommandeLigne $l) => $l->categorie === $categorie);
    }

    public function categoriesPresentes(): array
    {
        return $this->lignes
            ->pluck('categorie')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Groupes cocktail pour le formulaire d’édition : un bloc par cocktail, pas un mélange.
     *
     * @param  Collection<int, Cocktail>|null  $cocktails
     * @return array<int, array<string, mixed>>
     */
    public function groupesCocktailPourFormulaire(?Collection $cocktails = null): array
    {
        return $this->partitionsLignesCocktail($cocktails)
            ->map(fn (Collection $lignes) => $this->formaterGroupeCocktail($lignes, $cocktails))
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Cocktail>|null  $cocktails
     * @return Collection<int, Collection<int, CommandeLigne>>
     */
    public function partitionsLignesCocktail(?Collection $cocktails = null): Collection
    {
        $lignes = $this->lignes
            ->filter(fn (CommandeLigne $l) => $l->quantite_ml !== null)
            ->values();

        if ($lignes->isEmpty()) {
            return collect();
        }

        if ($lignes->every(fn (CommandeLigne $l) => $l->cocktail_groupe !== null)) {
            $partitions = $lignes
                ->groupBy(fn (CommandeLigne $l) => (int) $l->cocktail_groupe)
                ->sortKeys()
                ->values();
        } else {
            $partitions = $this->reconstruirePartitionsCocktail($lignes, $this->catalogueCocktails($cocktails));
        }

        return $this->ordonnerPartitionsCocktail($partitions);
    }

    public function resumeParfums(int $limit = 2): string
    {
        $parts = collect();

        $this->partitionsLignesCocktail()->each(function (Collection $lignes) use ($parts) {
            $nom = $lignes->first()?->cocktail?->nom;
            if ($nom) {
                $parts->push($nom);

                return;
            }

            $parfums = $lignes
                ->map(fn (CommandeLigne $l) => $l->produit?->nom)
                ->filter()
                ->unique()
                ->implode(' + ');

            if ($parfums !== '') {
                $parts->push($parfums);
            }
        });

        $this->lignes
            ->filter(fn (CommandeLigne $l) => $l->quantite_ml === null)
            ->map(fn (CommandeLigne $l) => $l->produit?->nom)
            ->filter()
            ->unique()
            ->each(fn ($nom) => $parts->push($nom));

        $parts = $parts->unique()->values();

        if ($parts->isEmpty()) {
            return '—';
        }

        $affiche = $parts->take($limit)->implode(', ');
        $reste = $parts->count() - $limit;

        return $reste > 0 ? $affiche.' (+'.$reste.')' : $affiche;
    }

    public function statutLabel(): string
    {
        return match ($this->statut) {
            'en_attente' => 'En attente',
            'confirmee' => 'Confirmée',
            'livree' => 'Livrée',
            'annulee' => 'Annulée',
            default => ucfirst(str_replace('_', ' ', $this->statut)),
        };
    }

    public function statutBadgeClass(): string
    {
        return match ($this->statut) {
            'en_attente' => 'bg-label-warning',
            'confirmee' => 'bg-label-info',
            'livree' => 'bg-label-success',
            'annulee' => 'bg-label-danger',
            default => 'bg-label-secondary',
        };
    }

    public function estEnvoyeeVersOvl(): bool
    {
        return $this->ovl_commande_id !== null || $this->ovl_sent_at !== null;
    }

    /**
     * @param  Collection<int, Cocktail>|null  $cocktails
     * @return Collection<int, Cocktail>
     */
    private function catalogueCocktails(?Collection $cocktails): Collection
    {
        if ($cocktails !== null) {
            return $cocktails->keyBy('id');
        }

        return Cocktail::query()->with('lignes')->get()->keyBy('id');
    }

    /**
     * @param  Collection<int, CommandeLigne>  $lignes
     * @param  Collection<int, Cocktail>  $cocktails
     * @return Collection<int, Collection<int, CommandeLigne>>
     */
    private function reconstruirePartitionsCocktail(Collection $lignes, Collection $cocktails): Collection
    {
        $recettes = $cocktails
            ->mapWithKeys(function (Cocktail $cocktail) {
                $ids = $cocktail->lignes->pluck('produit_id')->unique()->sort()->values();

                return $ids->count() >= 2 ? [$cocktail->id => $ids] : [];
            })
            ->filter()
            ->sortByDesc(fn (Collection $ids) => $ids->count());

        $remaining = $lignes->values();
        $partitions = collect();

        while ($remaining->isNotEmpty()) {
            $seed = $remaining->first();
            $pool = $remaining->filter(fn (CommandeLigne $l) => $this->memeGroupeCocktailPhysique($l, $seed))->values();
            $pris = $this->extraireRecetteCocktail($pool, $seed, $recettes);

            if ($pris->isEmpty()) {
                $pris = $pool;
            }

            $cocktailId = $pris->first()?->cocktail_id;
            if ($cocktailId) {
                $pris->each(fn (CommandeLigne $l) => $l->setAttribute('cocktail_id', $cocktailId));
            }

            $partitions->push($pris->values());
            $prisIds = $pris->pluck('id');
            $remaining = $remaining->reject(fn (CommandeLigne $l) => $prisIds->contains($l->id))->values();
        }

        return $this->ordonnerPartitionsCocktail($partitions);
    }

    /**
     * Les cocktails qui partagent flacon / catégorie / qté (ceux qui se mélangeaient)
     * restent collés, dans l’ordre de saisie.
     *
     * @param  Collection<int, Collection<int, CommandeLigne>>  $partitions
     * @return Collection<int, Collection<int, CommandeLigne>>
     */
    private function ordonnerPartitionsCocktail(Collection $partitions): Collection
    {
        if ($partitions->count() <= 1) {
            return $partitions->values();
        }

        return $partitions
            ->groupBy(function (Collection $lignes) {
                $first = $lignes->first();

                return implode('|', [
                    (int) ($first?->flacon_id ?? 0),
                    (string) ($first?->categorie ?? ''),
                    (int) ($first?->quantite ?? 0),
                ]);
            })
            ->sortBy(fn (Collection $cluster) => $cluster
                ->map(fn (Collection $lignes) => $lignes->min('id'))
                ->min())
            ->flatMap(fn (Collection $cluster) => $cluster
                ->sortBy(fn (Collection $lignes) => $lignes->min('id'))
                ->values())
            ->values();
    }

    /**
     * @param  Collection<int, CommandeLigne>  $pool
     * @param  Collection<int, Collection<int, int>>  $recettes
     * @return Collection<int, CommandeLigne>
     */
    private function extraireRecetteCocktail(Collection $pool, CommandeLigne $seed, Collection $recettes): Collection
    {
        $poolIds = $pool->pluck('produit_id')->unique();
        $meilleure = null;
        $meilleureIds = collect();
        $meilleurScore = -1;

        foreach ($recettes as $cocktailId => $produitIds) {
            if (! $produitIds->contains($seed->produit_id)) {
                continue;
            }

            if ($produitIds->diff($poolIds)->isNotEmpty()) {
                continue;
            }

            $score = $produitIds->count() * 10;
            if ($produitIds->count() === $poolIds->count()) {
                $score += 1;
            }

            if ($score > $meilleurScore) {
                $meilleurScore = $score;
                $meilleure = (int) $cocktailId;
                $meilleureIds = $produitIds;
            }
        }

        if ($meilleure === null) {
            return collect();
        }

        $pris = collect();
        $prisIds = collect();

        foreach ($meilleureIds as $produitId) {
            $ligne = $pool->first(
                fn (CommandeLigne $l) => (int) $l->produit_id === (int) $produitId && ! $prisIds->contains($l->id)
            );

            if (! $ligne) {
                return collect();
            }

            $ligne->setAttribute('cocktail_id', $meilleure);
            $pris->push($ligne);
            $prisIds->push($ligne->id);
        }

        return $pris;
    }

    private function memeGroupeCocktailPhysique(CommandeLigne $ligne, CommandeLigne $seed): bool
    {
        return (int) $ligne->flacon_id === (int) $seed->flacon_id
            && $ligne->categorie === $seed->categorie
            && (int) $ligne->quantite === (int) $seed->quantite;
    }

    /**
     * @param  Collection<int, CommandeLigne>  $lignes
     * @param  Collection<int, Cocktail>|null  $cocktails
     * @return array<string, mixed>
     */
    private function formaterGroupeCocktail(Collection $lignes, ?Collection $cocktails): array
    {
        $first = $lignes->first();
        $catalogue = $cocktails !== null ? $cocktails->keyBy('id') : collect();
        $cocktailId = $first?->cocktail_id;
        $nom = '';

        if ($cocktailId) {
            $nom = $catalogue->get((int) $cocktailId)?->nom
                ?? $first->cocktail?->nom
                ?? '';
        }

        return [
            'categorie' => $first && $first->categorie === 'cocktail' ? 'detail' : ($first->categorie ?? 'detail'),
            'flacon_id' => $first->flacon_id ?? '',
            'quantite' => $first->quantite ?? 1,
            'montant' => round((float) $lignes->sum('prix_unitaire'), 2),
            'cocktail_id' => $cocktailId ?: '',
            'cocktail_nom' => $nom,
            'parfums' => $lignes->map(fn (CommandeLigne $l) => [
                'produit_id' => $l->produit_id,
                'quantite_ml' => $l->quantite_ml,
            ])->values()->all(),
        ];
    }
}
