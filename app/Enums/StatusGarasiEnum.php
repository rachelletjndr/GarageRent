<?php

namespace App\Enums;

/**
 * Garage operational availability status.
 */
enum StatusGarasiEnum: string
{
    case Available = 'available';
    case Used = 'used';

    /**
     * Get user-friendly label for status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Available => 'Tersedia',
            self::Used => 'Sedang Digunakan',
        };
    }
}
