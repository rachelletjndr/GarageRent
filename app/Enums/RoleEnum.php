<?php

namespace App\Enums;

/**
 * Roles available in SIM-Garasi system.
 */
enum RoleEnum: string
{
    case Admin = 'admin';
    case Penyewa = 'penyewa';

    /**
     * Get user-friendly label for the role.
     */
    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrator',
            self::Penyewa => 'Penyewa Garasi',
        };
    }
}
