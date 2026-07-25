<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChauffeurGroupe extends Model
{
    protected $table = 'chauffeur_groupes';

    protected $fillable = [
        'nom_groupe',
    ];

    public function chauffeurs(): HasMany
    {
        return $this->hasMany(Chauffeur::class, 'chauffeur_groupe_id');
    }
}
