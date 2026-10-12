<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ListingType: string implements HasColor, HasLabel
{
    case Sell = 'sell';
    case Buy = 'buy';

    public function getLabel(): string
    {
        return match ($this) {
            self::Sell => 'Satılık',
            self::Buy => 'Alınacak',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Sell => 'success',
            self::Buy => 'info',
        };
    }
}
