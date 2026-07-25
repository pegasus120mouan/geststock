<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Agent extends Model
{
    protected $table = 'agents';
    protected $primaryKey = 'id_agent';

    protected $fillable = [
        'nom_complet',
        'numero_agent',
    ];
}
