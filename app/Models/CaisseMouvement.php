<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CaisseMouvement extends Model
{
    protected $table = 'caisse_mouvements';

    public const TYPE_APPROVISIONNEMENT = 'approvisionnement';

    public const TYPE_PAIEMENT = 'paiement';

    protected $fillable = [
        'type',
        'montant',
        'source',
        'motifs',
        'solde_apres',
        'user_id',
        'date_mouvement',
    ];

    protected $casts = [
        'montant' => 'decimal:2',
        'solde_apres' => 'decimal:2',
        'date_mouvement' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isApprovisionnement(): bool
    {
        return $this->type === self::TYPE_APPROVISIONNEMENT;
    }
}
