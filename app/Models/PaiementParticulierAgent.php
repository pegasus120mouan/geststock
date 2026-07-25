<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaiementParticulierAgent extends Model
{
    protected $table = 'paiements_particulier_agent';

    protected $fillable = [
        'particulier_agent_id',
        'montant',
        'date_paiement',
        'mode_paiement',
        'reference',
        'commentaire',
    ];

    protected $casts = [
        'date_paiement' => 'date',
    ];

    public function agent()
    {
        return $this->belongsTo(ParticulierAgent::class, 'particulier_agent_id');
    }
}
