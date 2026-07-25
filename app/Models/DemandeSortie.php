<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DemandeSortie extends Model
{
    protected $table = 'demandes_sorties';

    protected $primaryKey = 'id_demande';

    public $incrementing = true;

    protected $keyType = 'int';

    protected $fillable = [
        'numero_demande',
        'date_demande',
        'montant',
        'motif',
        'statut',
        'date_approbation',
        'approuve_par',
        'date_paiement',
        'paye_par',
        'montant_payer',
        'montant_reste',
    ];

    protected $casts = [
        'date_demande' => 'datetime',
        'date_approbation' => 'datetime',
        'date_paiement' => 'datetime',
        'montant' => 'decimal:2',
        'montant_payer' => 'decimal:2',
        'montant_reste' => 'decimal:2',
    ];

    public function approuvePar()
    {
        return $this->belongsTo(User::class, 'approuve_par');
    }

    public function payePar()
    {
        return $this->belongsTo(User::class, 'paye_par');
    }
}
