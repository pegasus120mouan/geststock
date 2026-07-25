<?php

namespace App\Enums;

enum VolumeUnit: string
{
    case Ml = 'ml';
    case Cl = 'cl';
    case Litre = 'litre';

    public function label(): string
    {
        return match ($this) {
            self::Ml => 'Millilitre (ml)',
            self::Cl => 'Centilitre (cl)',
            self::Litre => 'Litre',
        };
    }

    public function toMl(float|int|string $quantity): float
    {
        $quantity = (float) $quantity;

        return match ($this) {
            self::Ml => $quantity,
            self::Cl => $quantity * 10,
            self::Litre => $quantity * 1000,
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $unit) => [$unit->value => $unit->label()])
            ->all();
    }
}
