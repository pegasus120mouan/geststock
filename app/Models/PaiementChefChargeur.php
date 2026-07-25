<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaiementChefChargeur extends Model
{
    protected $table = 'paiements_chef_chargeur';

    protected $fillable = [
        'id_chef_chargeur',
        'montant',
        'date_paiement',
        'mode_paiement',
        'reference',
        'commentaire',
    ];

    protected $casts = [
        'date_paiement' => 'date',
    ];

    public function chefChargeur()
    {
        return $this->belongsTo(ChefChargeur::class, 'id_chef_chargeur');
    }
}
