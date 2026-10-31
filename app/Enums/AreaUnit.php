<?php

namespace App\Enums;

enum AreaUnit: string
{
    case Marla = 'marla';
    case Kanal = 'kanal';
    case SquareFeet = 'sqft';
    case SquareYards = 'sqyd';

    public function label(): string
    {
        return match ($this) {
            self::Marla => 'Marla',
            self::Kanal => 'Kanal',
            self::SquareFeet => 'Sq. Ft.',
            self::SquareYards => 'Sq. Yd.',
        };
    }

    /**
     * Square feet per unit. Marla follows the 225 sq ft convention used by
     * most Lahore housing schemes; it is configurable in config/meezan.php.
     */
    public function squareFeet(): float
    {
        $marla = (float) config('meezan.marla_sqft', 225);

        return match ($this) {
            self::Marla => $marla,
            self::Kanal => $marla * 20,
            self::SquareFeet => 1,
            self::SquareYards => 9,
        };
    }

    public function toSquareFeet(float $size): int
    {
        return (int) round($size * $this->squareFeet());
    }
}
