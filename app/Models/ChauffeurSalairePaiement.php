<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChauffeurSalairePaiement extends Model
{
    protected $table = 'chauffeur_salaire_paiements';

    protected $fillable = [
        'chauffeur_id',
        'date_paiement',
        'montant_total',
        'libelle',
        'commentaire',
    ];

    protected $casts = [
        'date_paiement' => 'date',
        'montant_total' => 'decimal:2',
    ];

    public function chauffeur(): BelongsTo
    {
        return $this->belongsTo(Chauffeur::class);
    }

    public function periodes(): HasMany
    {
        return $this->hasMany(ChauffeurSalairePaiementPeriode::class, 'chauffeur_salaire_paiement_id');
    }
}
