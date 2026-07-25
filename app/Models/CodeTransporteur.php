<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class CodeTransporteur extends Model
{
    protected $table = 'code_transporteurs';

    protected $fillable = [
        'nom',
    ];

    public function vehicules()
    {
        return $this->hasMany(CodeTransporteurVehicule::class, 'code_transporteur_id');
    }

    /** Codes encore utilisés : Autre Camion et Camion PGF uniquement. */
    public function scopePrisEnCompte(Builder $query): Builder
    {
        return $query->whereRaw('LOWER(nom) NOT LIKE ?', ['%pisteur%']);
    }

    public function estPrisEnCompte(): bool
    {
        return stripos((string) $this->nom, 'Pisteur') === false;
    }
}
