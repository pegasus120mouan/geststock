<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BordereauPgf extends Model
{
    protected $table = 'bordereaux_pgf';

    protected $fillable = [
        'numero',
        'libelle',
        'date_generation',
        'date_debut',
        'date_fin',
        'montant_total',
        'montant_paye',
        'poids_total',
        'fiches_data',
    ];

    protected $casts = [
        'date_generation' => 'date',
        'date_debut' => 'date',
        'date_fin' => 'date',
        'montant_total' => 'decimal:2',
        'montant_paye' => 'decimal:2',
        'poids_total' => 'decimal:2',
        'fiches_data' => 'array',
    ];

    public function getResteAPayerAttribute(): float
    {
        return max(0, (float) $this->montant_total - (float) ($this->montant_paye ?? 0));
    }

    public function paiements(): HasMany
    {
        return $this->hasMany(PaiementPgf::class, 'id_bordereau');
    }
}
