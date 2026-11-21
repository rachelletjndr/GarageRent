<?php

namespace App\Enums;

/**
 * Rental duration types.
 */
enum TipeSewaEnum: string
{
    case Harian = 'harian';
    case Bulanan = 'bulanan';

    /**
     * Get user-friendly label for rental type.
     */
    public function label(): string
    {
        return match ($this) {
            self::Harian => 'Sewa Harian',
            self::Bulanan => 'Sewa Bulanan (1 Bulan Kalender)',
        };
    }
}
