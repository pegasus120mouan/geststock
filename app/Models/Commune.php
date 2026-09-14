<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Commune extends Model
{
    use HasFactory;

    protected $fillable = [
        'nom',
        'statut',
    ];

    public function isActif(): bool
    {
        return $this->statut === 'actif';
    }

    public function coutLivraison(): HasOne
    {
        return $this->hasOne(CoutLivraison::class);
    }

    public function scopeActif($query)
    {
        return $query->where('statut', 'actif');
    }
}
