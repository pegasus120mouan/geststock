<?php

namespace App\Models;

use App\Enums\VolumeUnit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMouvement extends Model
{
    protected $fillable = [
        'produit_id',
        'user_id',
        'type',
        'quantite',
        'unite',
        'quantite_ml',
        'stock_avant',
        'stock_apres',
        'commentaire',
    ];

    protected function casts(): array
    {
        return [
            'quantite' => 'decimal:4',
            'unite' => VolumeUnit::class,
            'quantite_ml' => 'decimal:2',
            'stock_avant' => 'decimal:2',
            'stock_apres' => 'decimal:2',
        ];
    }

    public function produit(): BelongsTo
    {
        return $this->belongsTo(Produit::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isEntree(): bool
    {
        return $this->type === 'entree';
    }
}
