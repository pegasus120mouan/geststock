<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MouvementSolde extends Model
{
    protected $table = 'mouvements_soldes';

    protected $fillable = [
        'user_id',
        'type',
        'montant',
        'note',
    ];

    protected $casts = [
        'montant' => 'decimal:2',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
