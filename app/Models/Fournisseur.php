<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Fournisseur extends Model
{
    use HasFactory;

    protected $fillable = [
        'nom',
        'service_id',
    ];

    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    public function paiements()
    {
        return $this->hasMany(PaiementFournisseur::class);
    }

    public function depenses()
    {
        return $this->hasMany(Depense::class, 'description', 'nom');
    }
}
