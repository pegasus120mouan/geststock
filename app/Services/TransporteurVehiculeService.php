<?php

namespace App\Services;

use App\Models\Transporteur;
use App\Models\TransporteurVehicule;

class TransporteurVehiculeService
{
    public function transporteurPourVehicule(?int $vehiculeId, ?string $matricule): ?Transporteur
    {
        $query = TransporteurVehicule::query()->with('transporteur');

        if ($vehiculeId) {
            $link = (clone $query)->where('vehicule_id', $vehiculeId)->first();
            if ($link?->transporteur) {
                return $link->transporteur;
            }
        }

        $matricule = trim((string) $matricule);
        if ($matricule === '') {
            return null;
        }

        $link = $query->where('matricule_vehicule', $matricule)->first();

        return $link?->transporteur;
    }

    public function vehiculeEstAffecteTransporteur(?int $vehiculeId, ?string $matricule): bool
    {
        return $this->transporteurPourVehicule($vehiculeId, $matricule) !== null;
    }
}
