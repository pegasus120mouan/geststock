<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class PrixUnitaire extends Model
{
    use HasFactory;

    public const CATEGORIE_DETAIL = 'detail';

    public const CATEGORIE_EN_GROS = 'en_gros';

    protected $table = 'prix_unitaires';

    protected $fillable = [
        'reference',
        'flacon_id',
        'categorie',
        'prix',
    ];

    protected function casts(): array
    {
        return [
            'prix' => 'decimal:2',
        ];
    }

    public function flacon(): BelongsTo
    {
        return $this->belongsTo(Flacon::class);
    }

    public function produits(): BelongsToMany
    {
        return $this->belongsToMany(Produit::class, 'produit_prix_unitaire')
            ->withTimestamps();
    }

    public function categorieLabel(): string
    {
        return match ($this->categorie) {
            self::CATEGORIE_EN_GROS => 'En gros',
            default => 'Détail',
        };
    }

    public static function categories(): array
    {
        return [
            self::CATEGORIE_DETAIL => 'Détail',
            self::CATEGORIE_EN_GROS => 'En gros',
        ];
    }

    public static function generateReference(): string
    {
        do {
            $reference = 'PU-'.now()->format('Ymd').'-'.Str::upper(Str::random(4));
        } while (static::query()->where('reference', $reference)->exists());

        return $reference;
    }

    public static function trouver(int $produitId, int $flaconId, string $categorie = self::CATEGORIE_DETAIL): ?self
    {
        return static::query()
            ->where('flacon_id', $flaconId)
            ->where('categorie', $categorie)
            ->whereHas('produits', fn ($q) => $q->where('produits.id', $produitId))
            ->first();
    }

    public static function trouverOuCreer(int $flaconId, string $categorie, float $prix): self
    {
        $prix = round($prix, 2);

        $existant = static::query()
            ->where('flacon_id', $flaconId)
            ->where('categorie', $categorie)
            ->where('prix', $prix)
            ->first();

        if ($existant) {
            return $existant;
        }

        return static::query()->create([
            'reference' => static::generateReference(),
            'flacon_id' => $flaconId,
            'categorie' => $categorie,
            'prix' => $prix,
        ]);
    }
}
