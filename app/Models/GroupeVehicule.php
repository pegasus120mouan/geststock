<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GroupeVehicule extends Model
{
    protected $table = 'groupe_vehicules';

    protected $fillable = [
        'groupe_id',
        'vehicule_id',
        'matricule_vehicule',
    ];

    public function groupe()
    {
        return $this->belongsTo(Groupe::class, 'groupe_id');
    }
}
