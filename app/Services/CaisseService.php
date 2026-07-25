<?php

namespace App\Services;

use App\Models\CaisseMouvement;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class CaisseService
{
    public function getSolde(): float
    {
        $row = CaisseMouvement::query()
            ->selectRaw("COALESCE(SUM(CASE
                WHEN type = 'approvisionnement' THEN montant
                WHEN type = 'paiement' THEN -montant
                ELSE 0
            END), 0) AS solde")
            ->first();

        return (float) ($row->solde ?? 0);
    }

    /**
     * @return array{solde_caisse: float, total_mouvements: int, total_approvisionnements: int, total_paiements: int, total_montant_appro: float, total_montant_paiements: float}
     */
    public function stats(): array
    {
        $row = CaisseMouvement::query()
            ->selectRaw("
                COUNT(*) as total,
                COALESCE(SUM(CASE WHEN type = 'approvisionnement' THEN 1 ELSE 0 END), 0) as total_appro,
                COALESCE(SUM(CASE WHEN type = 'paiement' THEN 1 ELSE 0 END), 0) as total_paiements,
                COALESCE(SUM(CASE WHEN type = 'approvisionnement' THEN montant ELSE 0 END), 0) as montant_appro,
                COALESCE(SUM(CASE WHEN type = 'paiement' THEN montant ELSE 0 END), 0) as montant_paiements
            ")
            ->first();

        return [
            'solde_caisse' => $this->getSolde(),
            'total_mouvements' => (int) ($row->total ?? 0),
            'total_approvisionnements' => (int) ($row->total_appro ?? 0),
            'total_paiements' => (int) ($row->total_paiements ?? 0),
            'total_montant_appro' => (float) ($row->montant_appro ?? 0),
            'total_montant_paiements' => (float) ($row->montant_paiements ?? 0),
        ];
    }

    public function createApprovisionnement(float $montant, string $source, ?User $user, ?string $motifs = null): CaisseMouvement
    {
        return DB::transaction(function () use ($montant, $source, $user, $motifs) {
            $solde = $this->getSolde() + $montant;

            return CaisseMouvement::create([
                'type' => CaisseMouvement::TYPE_APPROVISIONNEMENT,
                'montant' => $montant,
                'source' => $source !== '' ? $source : 'Manuel',
                'motifs' => $motifs ?: 'Approvisionnement de la caisse',
                'solde_apres' => $solde,
                'user_id' => $user?->id,
                'date_mouvement' => now(),
            ]);
        });
    }

    public function debiter(
        float $montant,
        string $motifs,
        ?User $user = null,
        ?string $source = null,
    ): CaisseMouvement {
        if ($montant <= 0) {
            throw new \InvalidArgumentException('Le montant à débiter doit être supérieur à 0.');
        }

        return DB::transaction(function () use ($montant, $motifs, $user, $source) {
            $solde = $this->getSolde();
            if ($montant > $solde) {
                throw new \InvalidArgumentException(
                    'Solde de la caisse locale insuffisant. Disponible : '
                    .number_format($solde, 0, ',', ' ')
                    .' FCFA.'
                );
            }

            return CaisseMouvement::create([
                'type' => CaisseMouvement::TYPE_PAIEMENT,
                'montant' => $montant,
                'source' => $source ?: 'Local',
                'motifs' => $motifs,
                'solde_apres' => $solde - $montant,
                'user_id' => $user?->id,
                'date_mouvement' => now(),
            ]);
        });
    }

    /**
     * @param  array{type?: string, origine?: string, search?: string, date_debut?: string|null, date_fin?: string|null}  $filters
     */
    public function paginatedMouvements(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        $query = CaisseMouvement::query()
            ->with('user')
            ->orderByDesc('date_mouvement')
            ->orderByDesc('id');

        $type = $filters['type'] ?? 'all';
        if ($type === 'approvisionnement') {
            $query->where('type', CaisseMouvement::TYPE_APPROVISIONNEMENT);
        } elseif ($type === 'paiement') {
            $query->where('type', CaisseMouvement::TYPE_PAIEMENT);
        }

        $origine = $filters['origine'] ?? 'all';
        if ($origine === 'manuel') {
            $query->where(function ($q) {
                $q->whereNull('source')
                    ->orWhere(function ($inner) {
                        $inner->where('source', 'not like', 'Usine:%')
                            ->where('source', 'not like', 'Banque:%')
                            ->where('source', '!=', 'Local');
                    });
            });
        } elseif ($origine === 'banque') {
            $query->where('source', 'like', 'Banque:%');
        } elseif ($origine === 'usine') {
            $query->where('source', 'like', 'Usine:%');
        } elseif ($origine === 'local') {
            $query->where(function ($q) {
                $q->where('source', 'Local')
                    ->orWhere('type', CaisseMouvement::TYPE_PAIEMENT);
            });
        }

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('source', 'like', "%{$search}%")
                    ->orWhere('motifs', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['date_debut'])) {
            $query->whereDate('date_mouvement', '>=', $filters['date_debut']);
        }
        if (! empty($filters['date_fin'])) {
            $query->whereDate('date_mouvement', '<=', $filters['date_fin']);
        }

        return $query->paginate($perPage)->withQueryString();
    }
}
