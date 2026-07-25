<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PrixAgent extends Model
{
    protected $table = 'prix_agents';

    protected $fillable = [
        'id_agent',
        'id_usine',
        'nom_usine',
        'produit_id',
        'nom_produit',
        'type',
        'prix',
        'date_debut',
        'date_fin',
    ];

    protected $casts = [
        'prix' => 'decimal:2',
        'date_debut' => 'date',
        'date_fin' => 'date',
    ];

    public function produit()
    {
        return $this->belongsTo(Produit::class);
    }
}
