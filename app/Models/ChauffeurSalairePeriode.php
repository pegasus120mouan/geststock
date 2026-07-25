<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChauffeurSalairePeriode extends Model
{
    protected $table = 'chauffeur_salaire_periodes';

    protected $fillable = [
        'chauffeur_id',
        'annee',
        'mois',
        'montant_salaire',
    ];

    protected $casts = [
        'annee' => 'integer',
        'mois' => 'integer',
        'montant_salaire' => 'decimal:2',
    ];

    public function chauffeur(): BelongsTo
    {
        return $this->belongsTo(Chauffeur::class);
    }

    public function avances(): HasMany
    {
        return $this->hasMany(ChauffeurSalaireAvance::class, 'chauffeur_salaire_periode_id');
    }

    public function paiementLignes(): HasMany
    {
        return $this->hasMany(ChauffeurSalairePaiementPeriode::class, 'chauffeur_salaire_periode_id');
    }
}
