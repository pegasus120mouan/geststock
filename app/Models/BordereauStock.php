<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BordereauStock extends Model
{
    protected $table = 'bordereaux_stock';

    protected $fillable = [
        'numero',
        'date_generation',
        'date_debut',
        'date_fin',
        'ponts_data',
        'tickets_data',
        'poids_total',
        'poids_sortie',
    ];

    protected $casts = [
        'date_generation' => 'date',
        'date_debut' => 'date',
        'date_fin' => 'date',
        'ponts_data' => 'array',
        'tickets_data' => 'array',
        'poids_total' => 'decimal:2',
        'poids_sortie' => 'decimal:2',
    ];

    public static function generateNumero()
    {
        $year = date('Y');
        $month = date('m');
        $count = self::whereYear('created_at', $year)->whereMonth('created_at', $month)->count() + 1;
        return 'BRD-' . $year . $month . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);
    }
}
