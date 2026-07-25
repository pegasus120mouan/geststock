<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChauffeurSalairePaiementPeriode extends Model
{
    protected $table = 'chauffeur_salaire_paiement_periodes';

    protected $fillable = [
        'chauffeur_salaire_paiement_id',
        'chauffeur_salaire_periode_id',
        'montant',
    ];

    protected $casts = [
        'montant' => 'decimal:2',
    ];

    public function paiement(): BelongsTo
    {
        return $this->belongsTo(ChauffeurSalairePaiement::class, 'chauffeur_salaire_paiement_id');
    }

    public function periode(): BelongsTo
    {
        return $this->belongsTo(ChauffeurSalairePeriode::class, 'chauffeur_salaire_periode_id');
    }
}
