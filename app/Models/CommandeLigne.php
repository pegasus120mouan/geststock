<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommandeLigne extends Model
{
    use HasFactory;

    protected $table = 'commande_lignes';

    protected $fillable = [
        'commande_id',
        'produit_id',
        'flacon_id',
        'categorie',
        'quantite',
        'prix_unitaire',
        'total',
    ];

    protected function casts(): array
    {
        return [
            'quantite' => 'integer',
            'prix_unitaire' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    public function commande(): BelongsTo
    {
        return $this->belongsTo(Commande::class);
    }

    public function produit(): BelongsTo
    {
        return $this->belongsTo(Produit::class);
    }

    public function flacon(): BelongsTo
    {
        return $this->belongsTo(Flacon::class);
    }

    public function isEnGros(): bool
    {
        return $this->categorie === PrixUnitaire::CATEGORIE_EN_GROS;
    }

    public function categorieLabel(): string
    {
        return match ($this->categorie) {
            PrixUnitaire::CATEGORIE_EN_GROS => 'En gros',
            default => 'Détail',
        };
    }

    public function volumeMl(): float
    {
        $contenance = (int) ($this->flacon?->contenance_ml ?? 0);

        return round($contenance * (int) $this->quantite, 2);
    }

    public function montant(): float
    {
        if ((float) $this->total > 0) {
            return (float) $this->total;
        }

        return round((float) $this->prix_unitaire * (int) $this->quantite, 2);
    }
}
