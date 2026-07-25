<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Groupe extends Model
{
    protected $table = 'groupes';

    protected $fillable = [
        'nom_groupe',
    ];

    public function agents()
    {
        return $this->hasMany(GroupeAgent::class, 'groupe_id');
    }

    public function agentsOrdinaires()
    {
        return $this->hasMany(GroupeAgent::class, 'groupe_id')->where('type_agent', 'ordinaire');
    }

    public function agentsPisteurs()
    {
        return $this->hasMany(GroupeAgent::class, 'groupe_id')->where('type_agent', 'pisteur');
    }
}
