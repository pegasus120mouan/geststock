<?php

namespace App\Services;

use App\Models\FicheSortie;
use App\Models\PontEtat;
use Illuminate\Support\Facades\DB;

class FicheSortieNumeroService
{
    public function lettresPont(string $nomPont): string
    {
        $letters = preg_replace('/[^A-Z]/u', '', mb_strtoupper(trim($nomPont), 'UTF-8')) ?? '';

        if (mb_strlen($letters) >= 2) {
            return mb_substr($letters, 0, 2);
        }

        if ($letters !== '') {
            return str_pad($letters, 2, 'X');
        }

        return 'XX';
    }

    public function generer(?string $nomPont, ?int $idPont = null): string
    {
        if (!$nomPont && $idPont) {
            $nomPont = PontEtat::query()->where('id_pont', $idPont)->value('nom_pont');
        }

        $prefix = 'FICH-' . $this->lettresPont((string) ($nomPont ?? ''));

        return DB::transaction(function () use ($prefix) {
            $numeros = FicheSortie::query()
                ->where('numero_fiche', 'like', $prefix . '%')
                ->lockForUpdate()
                ->pluck('numero_fiche');

            $max = 0;
            foreach ($numeros as $numero) {
                if (preg_match('/^' . preg_quote($prefix, '/') . '(\d+)$/', (string) $numero, $matches)) {
                    $max = max($max, (int) $matches[1]);
                }
            }

            return $prefix . ($max + 1);
        });
    }
}
