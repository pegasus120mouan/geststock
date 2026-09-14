<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Commande extends Model
{
    use HasFactory;

    protected $fillable = [
        'reference',
        'date_commande',
        'client_nom',
        'client_telephone',
        'statut',
        'total',
        'notes',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'date_commande' => 'date',
            'total' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function lignes(): HasMany
    {
        return $this->hasMany(CommandeLigne::class)->orderBy('id');
    }

    public function montant(): float
    {
        if ((float) $this->total > 0) {
            return (float) $this->total;
        }

        return round((float) $this->lignes->sum(fn (CommandeLigne $l) => $l->montant()), 2);
    }

    public function recalculerTotal(): void
    {
        $this->forceFill([
            'total' => round((float) $this->lignes()->sum('total'), 2),
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
        $noms = $this->lignes
            ->map(fn (CommandeLigne $l) => $l->produit?->nom)
            ->filter()
            ->unique()
            ->values();

        if ($noms->isEmpty()) {
            return '—';
        }

        $affiche = $noms->take($limit)->implode(', ');
        $reste = $noms->count() - $limit;

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
}
