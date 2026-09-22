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

    /**
     * @return array{id: int, nom: string, volume_ml: float, lignes: array<int, array{produit_id: int, nom: string|null, quantite_ml: float|null}>}
     */
    public function toCatalogArray(): array
    {
        $this->loadMissing('lignes.produit');

        return [
            'id' => $this->id,
            'nom' => $this->nom,
            'volume_ml' => (float) $this->volumeTotalMl(),
            'lignes' => $this->lignes->map(function (CocktailLigne $ligne) {
                return [
                    'produit_id' => $ligne->produit_id,
                    'nom' => $ligne->produit?->nom,
                    'quantite_ml' => $ligne->quantite_ml !== null ? (float) $ligne->quantite_ml : null,
                ];
            })->values()->all(),
        ];
    }
}
