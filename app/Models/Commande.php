<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Commande extends Model
{
    use HasFactory;

    protected $fillable = [
        'reference',
        'produit_id',
        'flacon_id',
        'quantite',
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
            'quantite' => 'integer',
            'total' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function produit(): BelongsTo
    {
        return $this->belongsTo(Produit::class);
    }

    public function flacon(): BelongsTo
    {
        return $this->belongsTo(Flacon::class);
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
