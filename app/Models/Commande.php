<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    public function resumeParfums(int $limit = 2): string
    {
        $parts = collect();

        if ($this->cocktail?->nom) {
            $parts->push($this->cocktail->nom);
        }

        $this->lignes
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
}
