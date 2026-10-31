<?php

namespace App\Enums;

enum Purpose: string
{
    case Sale = 'sale';
    case Rent = 'rent';

    public function label(): string
    {
        return match ($this) {
            self::Sale => 'For Sale',
            self::Rent => 'For Rent',
        };
    }

    public function shortLabel(): string
    {
        return match ($this) {
            self::Sale => 'Buy',
            self::Rent => 'Rent',
        };
    }
}
