<?php

namespace App\Enums;

/**
 * Rental contract lifecycle status.
 */
enum StatusSewaEnum: string
{
    case Aktif = 'aktif';
    case Selesai = 'selesai';
    case Dibatalkan = 'dibatalkan';

    /**
     * Get user-friendly label for rental status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Aktif => 'Aktif Disewa',
            self::Selesai => 'Selesai',
            self::Dibatalkan => 'Dibatalkan',
        };
    }
}
