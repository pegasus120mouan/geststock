<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GroupeAgent extends Model
{
    protected $table = 'groupe_agents';

    protected $fillable = [
        'groupe_id',
        'id_agent',
        'type_agent',
    ];

    public function groupe()
    {
        return $this->belongsTo(Groupe::class, 'groupe_id');
    }
}
