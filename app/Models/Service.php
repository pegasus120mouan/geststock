<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Service extends Model
{
    use HasFactory;

    protected $fillable = [
        'nom_service',
    ];

    public function fournisseurs()
    {
        return $this->hasMany(Fournisseur::class);
    }
}
