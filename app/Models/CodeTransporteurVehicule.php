<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CodeTransporteurVehicule extends Model
{
    protected $table = 'code_transporteur_vehicule';

    protected $fillable = [
        'code_transporteur_id',
        'vehicule_id',
        'matricule_vehicule',
    ];

    public function codeTransporteur()
    {
        return $this->belongsTo(CodeTransporteur::class, 'code_transporteur_id');
    }
}
