<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Produit extends Model
{
    use HasFactory;

    protected $fillable = [
        'nom',
        'marque',
        'famille',
        'description',
        'prix_achat_ml',
        'image',
        'stock_ml',
        'statut',
    ];

    protected function casts(): array
    {
        return [
            'prix_achat_ml' => 'decimal:4',
            'stock_ml' => 'decimal:2',
        ];
    }

    public function isActif(): bool
    {
        return $this->statut === 'actif';
    }

    public function getImageUrlAttribute(): string
    {
        $image = is_string($this->image) ? trim($this->image) : '';

        if ($image === '') {
            return asset('img/avatars/default.png');
        }

        if (str_contains($image, '/')) {
            return Storage::disk('public')->url($image);
        }

        return asset('img/produits/'.$image);
    }

    public function valeurStock(): float
    {
        return (float) $this->stock_ml * (float) $this->prix_achat_ml;
    }

    public function stockMouvements()
    {
        return $this->hasMany(StockMouvement::class);
    }
}
