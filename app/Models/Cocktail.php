<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cocktail extends Model
{
    use HasFactory;

    protected $fillable = [
        'nom',
        'description',
        'statut',
    ];

    public function lignes(): HasMany
    {
        return $this->hasMany(CocktailLigne::class)->orderBy('id');
    }

    public function isActif(): bool
    {
        return $this->statut === 'actif';
    }

    public function volumeTotalMl(): float
    {
        return (float) $this->lignes->sum('quantite_ml');
    }
}
