<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Stock extends Model
{
    protected $fillable = [
        'id_pont',
        'parc_id',
        'nom_parc',
        'produit_id',
        'nom_produit',
        'code_pont',
        'nom_pont',
        'type',
        'quantite',
        'prix_unitaire',
        'montant_total',
        'date_mouvement',
        'code_stock',
        'statut',
        'etat',
        'date_fermeture',
        'commentaire',
    ];

    protected $casts = [
        'date_mouvement' => 'date',
        'date_fermeture' => 'date',
        'quantite' => 'decimal:2',
        'prix_unitaire' => 'decimal:2',
        'montant_total' => 'decimal:2',
    ];

    public function isOuvert(): bool
    {
        return $this->statut === 'ouvert';
    }

    public function isFerme(): bool
    {
        return $this->statut === 'ferme';
    }

    public function isActif(): bool
    {
        return ($this->etat ?? 'actif') === 'actif';
    }

    public function isInactif(): bool
    {
        return ($this->etat ?? 'actif') === 'inactif';
    }

    public function accepteEntrees(): bool
    {
        return $this->isOuvert() && $this->isActif();
    }

    public static function generateCodeStock(int $idPont, string $codePont): string
    {
        $year = date('Y');
        $month = date('m');
        $count = self::where('id_pont', $idPont)
            ->where('type', 'entree')
            ->whereYear('created_at', $year)
            ->count() + 1;
        
        return sprintf('STK-%s-%s%s-%03d', strtoupper($codePont), $year, $month, $count);
    }

    public function entreesStock()
    {
        return $this->hasMany(EntreeStock::class);
    }

    public function parc()
    {
        return $this->belongsTo(Parc::class);
    }

    public function getTotalEntreesAttribute(): float
    {
        return (float)$this->quantite + $this->entreesStock()->sum('quantite');
    }
}
