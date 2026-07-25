<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Financement extends Model
{
    protected $table = 'financement';

    protected $primaryKey = 'Numero_financement';

    public $incrementing = true;

    public $timestamps = false;

    protected $fillable = [
        'Numero_financement',
        'code_financement',
        'id_agent',
        'montant',
        'motif',
        'date_financement',
    ];

    protected $casts = [
        'date_financement' => 'datetime',
        'montant' => 'decimal:2',
    ];

    public function getCodeAfficheAttribute(): string
    {
        return (string) ($this->code_financement ?: $this->Numero_financement);
    }

    public function isAdvance(): bool
    {
        return (float) $this->montant > 0;
    }

    public function isRepayment(): bool
    {
        return (float) $this->montant < 0;
    }

    /**
     * Motif lisible : les anciennes lignes "Avance API" deviennent
     * "Avance Unipalm payé par {nom}".
     */
    public function getMotifAfficheAttribute(): string
    {
        return self::formatMotifAffiche($this->motif);
    }

    public static function formatMotifAffiche(?string $motif): string
    {
        $motif = trim((string) ($motif ?? ''));
        if ($motif === '') {
            return '';
        }

        if (preg_match('/^Avance Unipalm payé par\s+(.+?)(?:\s*[—\-]\s*AVANCE-PAIEMENT-\d+)?$/ui', $motif, $matches)) {
            return 'Avance Unipalm payé par '.trim($matches[1]);
        }

        if (str_starts_with($motif, 'Avance API')) {
            $payeur = self::resolveAvanceApiPayeurFromMotif($motif);

            return $payeur !== ''
                ? 'Avance Unipalm payé par '.$payeur
                : 'Avance Unipalm';
        }

        return $motif;
    }

    private static function resolveAvanceApiPayeurFromMotif(string $motif): string
    {
        if (preg_match('/demande\s*#(\d+)/i', $motif, $matches)) {
            $demande = DemandeAvance::query()->find((int) $matches[1]);
            $payeur = trim((string) ($demande->payee_par ?? ''));
            if ($payeur !== '') {
                return $payeur;
            }
        }

        if (preg_match('/AVANCE-PAIEMENT-(\d+)/i', $motif, $matches)) {
            $demande = DemandeAvance::query()
                ->where('paiement_agent_id', (int) $matches[1])
                ->first();
            $payeur = trim((string) ($demande->payee_par ?? ''));
            if ($payeur !== '') {
                return $payeur;
            }
        }

        return '';
    }
}
