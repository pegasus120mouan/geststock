<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ParticulierGroupe extends Model
{
    protected $table = 'particulier_groupes';

    protected $fillable = [
        'nom_groupe',
    ];

    public function agents()
    {
        return $this->hasMany(ParticulierAgent::class, 'particulier_groupe_id');
    }
}
