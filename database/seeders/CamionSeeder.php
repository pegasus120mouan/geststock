<?php

namespace Database\Seeders;

use App\Models\Camion;
use App\Models\User;
use Illuminate\Database\Seeder;

class CamionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $chauffeurs = User::query()->where('role', 'driver')->pluck('id')->all();

        $seed = [
            ['immatriculation' => 'AA-123-AA', 'marque' => 'Mercedes', 'modele' => 'Actros', 'annee' => 2018],
            ['immatriculation' => 'BB-456-BB', 'marque' => 'Volvo', 'modele' => 'FH', 'annee' => 2020],
            ['immatriculation' => 'CC-789-CC', 'marque' => 'Scania', 'modele' => 'R-Series', 'annee' => 2019],
            ['immatriculation' => 'DD-321-DD', 'marque' => 'MAN', 'modele' => 'TGX', 'annee' => 2017],
            ['immatriculation' => 'EE-654-EE', 'marque' => 'DAF', 'modele' => 'XF', 'annee' => 2021],
        ];

        foreach ($seed as $i => $data) {
            $chauffeurId = null;
            if (! empty($chauffeurs)) {
                $chauffeurId = $chauffeurs[$i % count($chauffeurs)];
            }

            Camion::updateOrCreate(
                ['immatriculation' => $data['immatriculation']],
                [
                    'marque' => $data['marque'],
                    'modele' => $data['modele'],
                    'annee' => $data['annee'],
                    'chauffeur_id' => $chauffeurId,
                    'actif' => true,
                ]
            );
        }

        // Camions sans chauffeur
        Camion::updateOrCreate(
            ['immatriculation' => 'ZZ-000-ZZ'],
            ['marque' => 'Iveco', 'modele' => 'Stralis', 'annee' => 2016, 'chauffeur_id' => null, 'actif' => false]
        );
    }
}
