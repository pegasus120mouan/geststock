<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Ticket extends Model
{
    protected $table = 'tickets';
    protected $primaryKey = 'id_ticket';

    public $incrementing = false;

    protected $keyType = 'int';

    protected $fillable = [
        'id_usine',
        'date_ticket',
        'id_agent',
        'particulier_agent_id',
        'numero_ticket',
        'vehicule_id',
        'matricule_vehicule',
        'poids',
        'id_utilisateur',
        'prix_unitaire',
        'prix_saisi_manuel',
        'date_validation_boss',
        'montant_paie',
        'montant_payer',
        'montant_reste',
        'date_paie',
        'statut_ticket',
        'numero_bordereau',
        'conformite',
        'bordereau_agent_id',
        'bordereau_pgf_id',
        'poids_unipalm',
        'date_confirmation_unipalm',
    ];

    protected $casts = [
        'date_ticket' => 'date',
        'date_validation_boss' => 'datetime',
        'date_paie' => 'datetime',
        'poids' => 'float',
        'prix_unitaire' => 'decimal:2',
        'prix_saisi_manuel' => 'boolean',
        'montant_paie' => 'decimal:2',
        'montant_payer' => 'decimal:2',
        'montant_reste' => 'decimal:2',
    ];

    public function ficheSortie()
    {
        return $this->hasOne(FicheSortie::class, 'id_ticket', 'id_ticket');
    }

    public function validation(): HasOne
    {
        return $this->hasOne(TicketValidation::class, 'id_ticket', 'id_ticket');
    }

    public function particulierAgent()
    {
        return $this->belongsTo(ParticulierAgent::class, 'particulier_agent_id');
    }

    public function estValide(): bool
    {
        if ($this->relationLoaded('validation')) {
            return $this->validation !== null;
        }

        return $this->validation()->exists();
    }

    public function scopeValide($query)
    {
        return $query->whereHas('validation');
    }
}
