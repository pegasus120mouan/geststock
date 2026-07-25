<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SortieStockPgf extends Model
{
    protected $table = 'sorties_stock_pgf';

    protected $fillable = [
        'stock_pgf_id',
        'fiche_sortie_id',
        'id_pont',
        'nom_pont',
        'code_pont',
        'quantite',
        'date_sortie',
        'commentaire',
    ];

    protected $casts = [
        'quantite' => 'decimal:2',
        'date_sortie' => 'date',
    ];

    public function stockPgf()
    {
        return $this->belongsTo(StockPgf::class, 'stock_pgf_id');
    }

    public function ficheSortie()
    {
        return $this->belongsTo(FicheSortie::class, 'fiche_sortie_id');
    }
}
