<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CoutLivraison extends Model
{
    use HasFactory;

    protected $table = 'couts_livraison';

    protected $fillable = [
        'commune_id',
        'montant',
        'statut',
    ];

    protected function casts(): array
    {
        return [
            'montant' => 'decimal:2',
        ];
    }

    public function commune(): BelongsTo
    {
        return $this->belongsTo(Commune::class);
    }

    public function isActif(): bool
    {
        return $this->statut === 'actif';
    }

    public function scopeActif($query)
    {
        return $query->where('statut', 'actif');
    }
}
