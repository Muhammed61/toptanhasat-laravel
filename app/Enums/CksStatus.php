<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

/** Üyenin ÇKS (Çiftçi Kayıt Sistemi) belgesinin durumu; satılık ilan yayını için onaylı olmalı */
enum CksStatus: string implements HasColor, HasIcon, HasLabel
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => 'Onay bekliyor',
            self::Approved => 'Onaylı',
            self::Rejected => 'Reddedildi',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Approved => 'success',
            self::Rejected => 'danger',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::Pending => Heroicon::OutlinedClock,
            self::Approved => Heroicon::OutlinedCheckBadge,
            self::Rejected => Heroicon::OutlinedXCircle,
        };
    }
}
