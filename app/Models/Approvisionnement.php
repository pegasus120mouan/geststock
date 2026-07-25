<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Approvisionnement extends Model
{
    protected $fillable = [
        'pont_id',
        'nom_pont',
        'code_pont',
        'montant',
        'date_approvisionnement',
        'mode_paiement',
        'nom_banque',
        'numero_cheque',
        'operateur',
        'reference',
        'user_id',
    ];

    protected $casts = [
        'date_approvisionnement' => 'date',
        'montant' => 'decimal:2',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
