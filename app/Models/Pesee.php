<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Pesee extends Model
{
    use HasFactory;

    protected $table = 'pesees';

    protected $fillable = [
        'pont_pesage_id',
        'camion_id',
        'produit_id',
        'agent_id',
        'chauffeur_id',
        'poids_brut',
        'tare',
        'poids_apres_refraction',
        'poids_vide',
        'poids_net',
        'pese_le',
        'reference',
        'notes',
        'status',
        'cancel_reason',
        'cancelled_at',
        'cancelled_by',
    ];

    protected $casts = [
        'poids_brut' => 'decimal:3',
        'tare' => 'decimal:3',
        'poids_apres_refraction' => 'decimal:3',
        'poids_vide' => 'decimal:3',
        'poids_net' => 'decimal:3',
        'pese_le' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function pontPesage()
    {
        return $this->belongsTo(PontPesage::class, 'pont_pesage_id');
    }

    public function camion()
    {
        return $this->belongsTo(Camion::class, 'camion_id');
    }

    public function produit()
    {
        return $this->belongsTo(Produit::class, 'produit_id');
    }

    public function agent()
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    public function chauffeur()
    {
        return $this->belongsTo(User::class, 'chauffeur_id');
    }

    public function cancelledBy()
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }
}
