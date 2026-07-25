<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Usine extends Model
{
    protected $table = 'usines';
    protected $primaryKey = 'id_usine';

    protected $fillable = [
        'nom_usine',
        'code_usine',
        'produit_id',
        'gerable',
    ];

    protected $casts = [
        'gerable' => 'boolean',
    ];

    public function produit()
    {
        return $this->belongsTo(Produit::class);
    }
}
