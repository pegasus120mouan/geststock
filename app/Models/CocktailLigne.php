<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CocktailLigne extends Model
{
    use HasFactory;

    protected $table = 'cocktail_lignes';

    protected $fillable = [
        'cocktail_id',
        'produit_id',
        'quantite_ml',
    ];

    protected function casts(): array
    {
        return [
            'quantite_ml' => 'decimal:2',
        ];
    }

    public function cocktail(): BelongsTo
    {
        return $this->belongsTo(Cocktail::class);
    }

    public function produit(): BelongsTo
    {
        return $this->belongsTo(Produit::class);
    }
}
