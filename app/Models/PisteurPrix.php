<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PisteurPrix extends Model
{
    protected $table = 'pisteur_prix';

    protected $fillable = [
        'id_pisteur',
        'prix_unitaire',
        'date_debut',
        'date_fin',
    ];

    protected $casts = [
        'date_debut' => 'date',
        'date_fin' => 'date',
    ];

    public function pisteur()
    {
        return $this->belongsTo(Pisteur::class, 'id_pisteur');
    }
}
