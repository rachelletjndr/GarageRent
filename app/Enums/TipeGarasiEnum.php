<?php

namespace App\Enums;

/**
 * Garage physical unit types.
 */
enum TipeGarasiEnum: string
{
    case Sepeda = 'sepeda';
    case Mobil = 'mobil';
    case MobilListrik = 'mobil_listrik';

    /**
     * Get ID prefix according to SRS BR-08 (S, C, E).
     */
    public function prefix(): string
    {
        return match ($this) {
            self::Sepeda => 'S',
            self::Mobil => 'C',
            self::MobilListrik => 'E',
        };
    }

    /**
     * Get user-friendly label for the garage type.
     */
    public function label(): string
    {
        return match ($this) {
            self::Sepeda => 'Garasi Sepeda / Motor',
            self::Mobil => 'Garasi Mobil Bensin',
            self::MobilListrik => 'Garasi Mobil Listrik (EV)',
        };
    }
}
