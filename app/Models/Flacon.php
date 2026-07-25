<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Flacon extends Model
{
    use HasFactory;

    protected $fillable = [
        'nom',
        'contenance_ml',
        'prix',
        'statut',
    ];

    protected function casts(): array
    {
        return [
            'contenance_ml' => 'integer',
            'prix' => 'decimal:2',
        ];
    }

    public function isActif(): bool
    {
        return $this->statut === 'actif';
    }

    public function label(): string
    {
        return $this->nom.' ('.$this->contenance_ml.' ml)';
    }

    public function scopeActif($query)
    {
        return $query->where('statut', 'actif');
    }
}
