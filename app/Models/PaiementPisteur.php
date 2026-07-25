<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaiementPisteur extends Model
{
    protected $table = 'paiements_pisteur';

    protected $fillable = [
        'id_pisteur',
        'montant',
        'date_paiement',
        'mode_paiement',
        'reference',
        'commentaire',
    ];

    protected $casts = [
        'date_paiement' => 'date',
    ];

    public function pisteur()
    {
        return $this->belongsTo(Pisteur::class, 'id_pisteur');
    }
}
