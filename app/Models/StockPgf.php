<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockPgf extends Model
{
    protected $table = 'stocks_pgf';

    protected $fillable = [
        'code',
        'date_debut',
        'date_fin',
        'statut',
    ];

    protected $casts = [
        'date_debut' => 'date',
        'date_fin' => 'date',
    ];

    public function entrees()
    {
        return $this->hasMany(EntreeStockPgf::class, 'stock_pgf_id');
    }

    public function sorties()
    {
        return $this->hasMany(SortieStockPgf::class, 'stock_pgf_id');
    }

    public function getTotalEntreesAttribute()
    {
        return $this->entrees()->sum('quantite');
    }

    public function getTotalSortiesAttribute()
    {
        return $this->sorties()->sum('quantite');
    }

    public function getStockDisponibleAttribute()
    {
        return $this->total_entrees - $this->total_sorties;
    }

    public static function generateCode()
    {
        $year = date('Y');
        $month = date('m');
        $count = self::whereYear('created_at', $year)->whereMonth('created_at', $month)->count() + 1;
        return 'STK-' . $year . $month . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);
    }
}
