<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PontEtat extends Model
{
    use HasFactory;

    protected $table = 'pont_etats';

    protected $fillable = [
        'id_pont',
        'nom_pont',
        'code_pont',
        'etat',
        'gerable',
    ];

    protected $casts = [
        'gerable' => 'boolean',
    ];

    public static function etatDepuisApi(?string $statutApi): string
    {
        return strtolower(trim((string) $statutApi)) === 'inactif' ? 'inactif' : 'actif';
    }

    public function accepteEntreesStock(): bool
    {
        return in_array($this->etat, ['actif', 'inactif'], true);
    }
}
