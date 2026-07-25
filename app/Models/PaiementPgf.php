<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaiementPgf extends Model
{
    protected $table = 'paiements_pgf';

    protected $fillable = [
        'id_bordereau',
        'montant',
        'date_paiement',
        'mode_paiement',
        'caisse',
        'reference',
        'commentaire',
    ];

    protected $casts = [
        'montant' => 'decimal:2',
        'date_paiement' => 'date',
    ];

    public function bordereau(): BelongsTo
    {
        return $this->belongsTo(BordereauPgf::class, 'id_bordereau');
    }
}
