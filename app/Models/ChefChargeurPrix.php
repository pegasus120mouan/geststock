<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChefChargeurPrix extends Model
{
    protected $table = 'chef_chargeur_prix';

    protected $fillable = [
        'id_chef_chargeur',
        'prix_unitaire',
        'date_debut',
        'date_fin',
    ];

    protected $casts = [
        'date_debut' => 'date',
        'date_fin' => 'date',
    ];

    public function chefChargeur()
    {
        return $this->belongsTo(ChefChargeur::class, 'id_chef_chargeur');
    }
}
