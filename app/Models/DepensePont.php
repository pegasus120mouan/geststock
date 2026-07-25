<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DepensePont extends Model
{
    protected $table = 'depenses_pont';

    protected $fillable = [
        'id_pont',
        'nom_pont',
        'code_pont',
        'libelle',
        'montant',
        'date_depense',
        'categorie',
        'description',
        'user_id',
    ];

    protected $casts = [
        'date_depense' => 'date',
        'montant' => 'decimal:2',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
