<?php

namespace App\Enums;

/**
 * Lifecycle: Pending --(admin approves)--> Active --(admin)--> Suspended
 */
enum StoreStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Suspended = 'suspended';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu persetujuan',
            self::Active => 'Aktif',
            self::Suspended => 'Ditangguhkan',
        };
    }
}
