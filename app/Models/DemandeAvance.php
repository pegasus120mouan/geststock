<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DemandeAvance extends Model
{
    protected $table = 'demandes_avance';

    public const SOURCE_LOCAL = 'local';

    public const SOURCE_API = 'api';

    public const STATUT_EN_ATTENTE = 'en_attente';

    public const STATUT_PAYEE = 'payee';

    public const STATUT_ANNULEE = 'annulee';

    protected $fillable = [
        'id_agent',
        'agent_nom',
        'agent_numero',
        'montant',
        'date_demande',
        'mode_paiement',
        'reference',
        'commentaire',
        'source',
        'statut',
        'paiement_agent_id',
        'payee_at',
        'payee_par',
    ];

    protected $casts = [
        'montant' => 'decimal:2',
        'date_demande' => 'date',
        'payee_at' => 'datetime',
    ];

    public function paiement(): BelongsTo
    {
        return $this->belongsTo(PaiementAgent::class, 'paiement_agent_id');
    }

    public function isEnAttente(): bool
    {
        return $this->statut === self::STATUT_EN_ATTENTE;
    }
}
