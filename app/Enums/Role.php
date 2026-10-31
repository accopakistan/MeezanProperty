<?php

namespace App\Enums;

enum Role: string
{
    case Buyer = 'buyer';
    case Dealer = 'dealer';
    case Admin = 'admin';

    public function label(): string
    {
        return match ($this) {
            self::Buyer => 'Buyer',
            self::Dealer => 'Dealer',
            self::Admin => 'Administrator',
        };
    }

    public function homeRoute(): string
    {
        return match ($this) {
            self::Buyer => 'account.dashboard',
            self::Dealer => 'dealer.dashboard',
            self::Admin => 'admin.dashboard',
        };
    }
}
