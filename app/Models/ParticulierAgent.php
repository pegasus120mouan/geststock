<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ParticulierAgent extends Model
{
    protected $table = 'particulier_agents';

    protected $fillable = [
        'particulier_groupe_id',
        'id_agent',
        'numero_agent',
        'nom',
        'prenoms',
        'contact',
    ];

    public function groupe()
    {
        return $this->belongsTo(ParticulierGroupe::class, 'particulier_groupe_id');
    }

    public function prix()
    {
        return $this->hasMany(ParticulierAgentPrix::class, 'particulier_agent_id');
    }

    public function getNomCompletAttribute(): string
    {
        return trim($this->nom . ' ' . $this->prenoms);
    }
}
