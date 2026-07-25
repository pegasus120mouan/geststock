<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PontPesage extends Model
{
    use HasFactory;

    protected $table = 'ponts_pesage';

    protected $fillable = [
        'code',
        'nom',
        'localisation',
        'actif',
        'gerable',
    ];

    protected $casts = [
        'actif' => 'boolean',
        'gerable' => 'boolean',
    ];

    public function agents()
    {
        return $this->belongsToMany(User::class, 'agent_pont_pesage', 'pont_pesage_id', 'agent_id')
            ->withPivot(['affecte_le'])
            ->withTimestamps();
    }

    public function pesees()
    {
        return $this->hasMany(Pesee::class, 'pont_pesage_id');
    }
}
