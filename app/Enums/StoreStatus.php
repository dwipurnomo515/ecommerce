<?php

namespace App\Enums;

/**
 * Lifecycle (every move is an admin action):
 *
 *   Pending ──approve──▶ Active ──suspend──▶ Suspended
 *      │                   ▲                     │
 *      └──────suspend──────┼─────────────────────┘
 *                          └──────approve────────┘ (reactivate)
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

    /**
     * The single source of truth for which status changes are allowed.
     */
    public function canTransitionTo(self $next): bool
    {
        $allowed = match ($this) {
            self::Pending => [self::Active, self::Suspended],
            self::Active => [self::Suspended],
            self::Suspended => [self::Active],
        };

        return in_array($next, $allowed, true);
    }
}
