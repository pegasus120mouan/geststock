<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
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

    /**
     * Affiche toujours le nom en casse lisible :
     * "LA VIE EST BELLE ELIXIR" → "La vie est belle Elixir"
     */
    protected function nom(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => static::formatNomParfum($value),
            set: fn (?string $value) => static::formatNomParfum($value),
        );
    }

    public static function formatNomParfum(?string $nom): string
    {
        $nom = trim(preg_replace('/\s+/u', ' ', (string) $nom) ?? '');

        if ($nom === '') {
            return '';
        }

        $words = preg_split('/\s+/u', mb_strtolower($nom, 'UTF-8')) ?: [];

        if (count($words) === 1) {
            return static::mbUcfirst($words[0]);
        }

        $first = static::mbUcfirst($words[0]);
        $last = static::mbUcfirst($words[array_key_last($words)]);
        $middle = array_slice($words, 1, -1);

        return trim(implode(' ', array_merge([$first], $middle, [$last])));
    }

    protected static function mbUcfirst(string $value): string
    {
        if ($value === '') {
            return '';
        }

        return mb_strtoupper(mb_substr($value, 0, 1, 'UTF-8'), 'UTF-8')
            .mb_substr($value, 1, null, 'UTF-8');
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
