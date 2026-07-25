<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Pisteur extends Model
{
    use HasFactory;

    protected $table = 'pisteurs';

    protected $fillable = [
        'id_agent',
        'nom',
        'prenoms',
        'contact',
        'prix_unitaire',
        'date_debut',
        'date_fin',
    ];

    protected $casts = [
        'date_debut' => 'date',
        'date_fin' => 'date',
    ];

    public function prixPeriodes()
    {
        return $this->hasMany(PisteurPrix::class, 'id_pisteur')->orderBy('date_debut', 'desc');
    }

    public function paiements()
    {
        return $this->hasMany(PaiementPisteur::class, 'id_pisteur')->orderBy('date_paiement', 'desc');
    }

    public function fichesSortie()
    {
        return $this->hasMany(FicheSortie::class, 'id_pisteur');
    }

    public function getFullNameAttribute(): string
    {
        return $this->nom . ' ' . $this->prenoms;
    }
}
