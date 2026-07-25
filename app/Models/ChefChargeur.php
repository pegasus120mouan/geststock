<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChefChargeur extends Model
{
    use HasFactory;

    protected $table = 'chef_chargeurs';

    protected $fillable = [
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

    public function chargeurs()
    {
        return $this->hasMany(Chargeur::class, 'id_chef_chargeur');
    }

    public function prixPeriodes()
    {
        return $this->hasMany(ChefChargeurPrix::class, 'id_chef_chargeur')->orderBy('date_debut', 'desc');
    }

    public function paiements()
    {
        return $this->hasMany(PaiementChefChargeur::class, 'id_chef_chargeur')->orderBy('date_paiement', 'desc');
    }

    public function fichesSortie()
    {
        return $this->hasMany(FicheSortie::class, 'id_chef_chargeur');
    }
}
