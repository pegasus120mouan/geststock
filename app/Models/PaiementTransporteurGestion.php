<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaiementTransporteurGestion extends Model
{
    protected $table = 'paiements_transporteur_gestion';

    protected $fillable = [
        'transporteur_id',
        'montant',
        'date_paiement',
        'mode_paiement',
        'reference',
        'commentaire',
    ];

    protected $casts = [
        'date_paiement' => 'date',
    ];

    public function transporteur(): BelongsTo
    {
        return $this->belongsTo(Transporteur::class);
    }
}
