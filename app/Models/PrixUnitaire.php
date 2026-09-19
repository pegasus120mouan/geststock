<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
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

    /**
     * Parfums ayant un tarif d'une catégorie mais pas de l'autre pour la même contenance.
     *
     * @return Collection<int, object{
     *     produit_id: int,
     *     produit_nom: string,
     *     statut: string,
     *     flacon_id: int,
     *     contenance_ml: int,
     *     categorie_presente: string,
     *     categorie_manquante: string,
     *     prix_id: int,
     *     reference: string
     * }>
     */
    public static function ecartsParfum(): Collection
    {
        $associations = DB::table('produit_prix_unitaire as ppu')
            ->join('prix_unitaires as pu', 'pu.id', '=', 'ppu.prix_unitaire_id')
            ->join('produits as p', 'p.id', '=', 'ppu.produit_id')
            ->join('flacons as f', 'f.id', '=', 'pu.flacon_id')
            ->select(
                'p.id as produit_id',
                'p.nom as produit_nom',
                'p.statut',
                'f.id as flacon_id',
                'f.contenance_ml',
                'pu.id as prix_id',
                'pu.reference',
                'pu.categorie'
            )
            ->get();

        $ecarts = collect();

        foreach ($associations->groupBy(fn ($row) => $row->produit_id.'-'.$row->flacon_id) as $rows) {
            $categories = $rows->pluck('categorie')->unique();

            if ($categories->count() >= 2) {
                continue;
            }

            $present = $rows->first();
            $manquante = $present->categorie === self::CATEGORIE_EN_GROS
                ? self::CATEGORIE_DETAIL
                : self::CATEGORIE_EN_GROS;

            $ecarts->push((object) [
                'produit_id' => (int) $present->produit_id,
                'produit_nom' => Produit::formatNomParfum((string) $present->produit_nom),
                'statut' => (string) $present->statut,
                'flacon_id' => (int) $present->flacon_id,
                'contenance_ml' => (int) $present->contenance_ml,
                'categorie_presente' => (string) $present->categorie,
                'categorie_manquante' => $manquante,
                'prix_id' => (int) $present->prix_id,
                'reference' => (string) $present->reference,
            ]);
        }

        return $ecarts
            ->sortBy(function ($ecart) {
                $priorite = $ecart->statut === 'actif' ? '1' : '0';

                return $priorite.'|'.mb_strtolower($ecart->produit_nom).'|'.str_pad((string) $ecart->contenance_ml, 4, '0', STR_PAD_LEFT);
            })
            ->values();
    }
}
