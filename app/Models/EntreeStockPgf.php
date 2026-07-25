<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EntreeStockPgf extends Model
{
    protected $table = 'entrees_stock_pgf';

    protected $fillable = [
        'stock_pgf_id',
        'id_pont',
        'nom_pont',
        'code_pont',
        'quantite',
        'date_entree',
        'commentaire',
    ];

    protected $casts = [
        'quantite' => 'decimal:2',
        'date_entree' => 'date',
    ];

    public function stockPgf()
    {
        return $this->belongsTo(StockPgf::class, 'stock_pgf_id');
    }
}
