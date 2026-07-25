<?php

namespace App\Services;

use App\Models\Transporteur;
use Illuminate\Support\Facades\DB;

class TransporteurCodeService
{
    private const PREFIX = 'TRP-';

    public function prochain(): string
    {
        return $this->prochainCodeDepuisCollection(
            Transporteur::query()->where('code', 'like', self::PREFIX . '%')->pluck('code')
        );
    }

    public function generer(): string
    {
        return DB::transaction(function () {
            $codes = Transporteur::query()
                ->where('code', 'like', self::PREFIX . '%')
                ->lockForUpdate()
                ->pluck('code');

            return $this->prochainCodeDepuisCollection($codes);
        });
    }

    /**
     * @param  iterable<int, string>  $codes
     */
    private function prochainCodeDepuisCollection(iterable $codes): string
    {
        $max = 0;
        foreach ($codes as $code) {
            if (preg_match('/^' . preg_quote(self::PREFIX, '/') . '(\d+)$/', (string) $code, $matches)) {
                $max = max($max, (int) $matches[1]);
            }
        }

        return self::PREFIX . str_pad((string) ($max + 1), 3, '0', STR_PAD_LEFT);
    }
}
