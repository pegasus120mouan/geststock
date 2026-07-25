<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChauffeurSalaireAvance extends Model
{
    protected $table = 'chauffeur_salaire_avances';

    protected $fillable = [
        'chauffeur_id',
        'chauffeur_salaire_periode_id',
        'date_avance',
        'montant',
        'libelle',
    ];

    protected $casts = [
        'date_avance' => 'date',
        'montant' => 'decimal:2',
    ];

    public function chauffeur(): BelongsTo
    {
        return $this->belongsTo(Chauffeur::class);
    }

    public function periode(): BelongsTo
    {
        return $this->belongsTo(ChauffeurSalairePeriode::class, 'chauffeur_salaire_periode_id');
    }
}
