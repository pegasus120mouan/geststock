<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransporteurVehicule extends Model
{
    protected $fillable = [
        'transporteur_id',
        'vehicule_id',
        'matricule_vehicule',
    ];

    protected $casts = [
        'vehicule_id' => 'integer',
        'transporteur_id' => 'integer',
    ];

    public function transporteur(): BelongsTo
    {
        return $this->belongsTo(Transporteur::class);
    }
}
