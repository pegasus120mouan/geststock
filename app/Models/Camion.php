<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Camion extends Model
{
    use HasFactory;

    protected $table = 'camions';

    protected $fillable = [
        'immatriculation',
        'reference',
        'image_face',
        'image_profil_gauche',
        'image_profil_droit',
        'image_arriere',
        'marque',
        'modele',
        'annee',
        'chauffeur_id',
        'actif',
    ];

    protected $casts = [
        'annee' => 'integer',
        'actif' => 'boolean',
    ];

    public function chauffeur()
    {
        return $this->belongsTo(User::class, 'chauffeur_id');
    }

    public function pesees()
    {
        return $this->hasMany(Pesee::class, 'camion_id');
    }
}
