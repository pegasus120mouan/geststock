<?php

namespace Database\Seeders;

use App\Models\PontPesage;
use Illuminate\Database\Seeder;

class PontPesageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $ponts = [
            ['code' => 'PP-ABJ-01', 'nom' => 'Pont Abidjan 01', 'localisation' => 'Abidjan', 'actif' => true],
            ['code' => 'PP-ABJ-02', 'nom' => 'Pont Abidjan 02', 'localisation' => 'Abidjan', 'actif' => true],
            ['code' => 'PP-YAK-01', 'nom' => 'Pont Yamoussoukro 01', 'localisation' => 'Yamoussoukro', 'actif' => true],
            ['code' => 'PP-BKE-01', 'nom' => 'Pont Bouaké 01', 'localisation' => 'Bouaké', 'actif' => true],
            ['code' => 'PP-SAN-01', 'nom' => 'Pont San-Pédro 01', 'localisation' => 'San-Pédro', 'actif' => true],
            ['code' => 'PP-KOR-01', 'nom' => 'Pont Korhogo 01', 'localisation' => 'Korhogo', 'actif' => false],
        ];

        foreach ($ponts as $data) {
            PontPesage::updateOrCreate(
                ['code' => $data['code']],
                $data
            );
        }

        PontPesage::firstOrCreate(
            ['code' => 'PP-TEST-01'],
            ['nom' => 'Pont Test', 'localisation' => null, 'actif' => true]
        );
    }
}
