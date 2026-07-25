<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Transporteur extends Model
{
    protected $fillable = [
        'code',
        'nom',
        'prenoms',
    ];

    protected function nom(): Attribute
    {
        return Attribute::get(fn ($value) => Str::title(trim((string) $value)));
    }

    protected function prenoms(): Attribute
    {
        return Attribute::get(fn ($value) => Str::title(trim((string) $value)));
    }

    public function vehicules(): HasMany
    {
        return $this->hasMany(TransporteurVehicule::class);
    }

    public function paiementsGestion(): HasMany
    {
        return $this->hasMany(PaiementTransporteurGestion::class)->orderBy('date_paiement', 'desc');
    }

    public function avances(): HasMany
    {
        return $this->hasMany(AvanceTransporteur::class)->orderByDesc('date_avance');
    }

    public function bordereaux(): HasMany
    {
        return $this->hasMany(BordereauTransporteur::class)->orderByDesc('date_generation');
    }
}
