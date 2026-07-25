<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Chauffeur extends Model
{
    protected $table = 'chauffeurs';

    protected $fillable = [
        'nom',
        'prenoms',
        'chauffeur_groupe_id',
        'contact',
        'matricule_vehicule',
        'vehicule_id',
        'salaire',
    ];

    protected $casts = [
        'salaire' => 'decimal:2',
        'vehicule_id' => 'integer',
        'chauffeur_groupe_id' => 'integer',
    ];

    public function groupe(): BelongsTo
    {
        return $this->belongsTo(ChauffeurGroupe::class, 'chauffeur_groupe_id');
    }

    public function salairePeriodes(): HasMany
    {
        return $this->hasMany(ChauffeurSalairePeriode::class);
    }

    public function salaireAvances(): HasMany
    {
        return $this->hasMany(ChauffeurSalaireAvance::class);
    }

    public function salairePaiements(): HasMany
    {
        return $this->hasMany(ChauffeurSalairePaiement::class);
    }

    /**
     * Chauffeur associé au camion (matricule ou ID véhicule).
     * La date de référence sert au contexte affiché ; l'affectation est l'état actuel en base.
     */
    public static function findForCamionAtDate(?string $matricule, ?int $vehiculeId, mixed $dateReference = null): ?self
    {
        $matricule = trim((string) $matricule);
        if ($matricule === '' && !$vehiculeId) {
            return null;
        }

        if ($matricule !== '') {
            $chauffeur = static::with('groupe')
                ->whereRaw('LOWER(TRIM(matricule_vehicule)) = ?', [mb_strtolower($matricule)])
                ->first();

            if ($chauffeur) {
                return $chauffeur;
            }
        }

        if ($vehiculeId) {
            return static::with('groupe')->where('vehicule_id', $vehiculeId)->first();
        }

        return null;
    }
}
