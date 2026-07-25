<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BordereauAgent extends Model
{
    protected $table = 'bordereaux_agent';

    protected $fillable = [
        'id_agent',
        'numero',
        'agent_nom',
        'agent_numero',
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

    public function paiements()
    {
        return $this->hasMany(PaiementAgent::class, 'id_bordereau');
    }
}
