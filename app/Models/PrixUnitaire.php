<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrixUnitaire extends Model
{
    use HasFactory;

    protected $table = 'prix_unitaires';

    protected $fillable = [
        'produit_id',
        'flacon_id',
        'prix',
    ];

    protected function casts(): array
    {
        return [
            'prix' => 'decimal:2',
        ];
    }

    public function produit(): BelongsTo
    {
        return $this->belongsTo(Produit::class);
    }

    public function flacon(): BelongsTo
    {
        return $this->belongsTo(Flacon::class);
    }
}
