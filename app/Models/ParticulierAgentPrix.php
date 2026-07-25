<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ParticulierAgentPrix extends Model
{
    protected $table = 'particulier_agent_prix';

    protected $fillable = [
        'particulier_agent_id',
        'id_usine',
        'nom_usine',
        'type_transporteur',
        'produit_id',
        'prix',
        'date_debut',
        'date_fin',
    ];

    public function produit()
    {
        return $this->belongsTo(Produit::class, 'produit_id');
    }

    protected $casts = [
        'prix' => 'decimal:2',
        'date_debut' => 'date',
        'date_fin' => 'date',
    ];

    public function agent()
    {
        return $this->belongsTo(ParticulierAgent::class, 'particulier_agent_id');
    }
}
