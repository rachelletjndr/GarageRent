<?php

namespace App\Enums;

/**
 * Vehicle physical types.
 */
enum TipeKendaraanEnum: string
{
    case Sepeda = 'sepeda';
    case SepedaMotor = 'sepeda_motor';
    case MobilBensin = 'mobil_bensin';
    case MobilListrik = 'mobil_listrik';

    /**
     * Get user-friendly label for the vehicle type.
     */
    public function label(): string
    {
        return match ($this) {
            self::Sepeda => 'Sepeda',
            self::SepedaMotor => 'Sepeda Motor',
            self::MobilBensin => 'Mobil Bensin / Konvensional',
            self::MobilListrik => 'Mobil Listrik (EV)',
        };
    }
}
