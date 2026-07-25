<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaiementFournisseur extends Model
{
    protected $table = 'paiements_fournisseur';

    protected $fillable = [
        'fournisseur_id',
        'montant',
        'date_paiement',
        'mode_paiement',
        'reference',
        'commentaire',
    ];

    protected $casts = [
        'montant' => 'decimal:2',
        'date_paiement' => 'date',
    ];

    public function fournisseur()
    {
        return $this->belongsTo(Fournisseur::class);
    }
}
