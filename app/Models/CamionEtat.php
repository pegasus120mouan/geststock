<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CamionEtat extends Model
{
    use HasFactory;

    protected $table = 'camion_etats';

    protected $fillable = [
        'vehicule_id',
        'matricule',
        'etat',
    ];
}
