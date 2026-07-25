<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Chargeur extends Model
{
    protected $table = 'chargeurs';

    protected $fillable = [
        'nom',
        'prenoms',
        'contact',
        'id_chef_chargeur',
    ];

    public function chefChargeur()
    {
        return $this->belongsTo(ChefChargeur::class, 'id_chef_chargeur');
    }
}
