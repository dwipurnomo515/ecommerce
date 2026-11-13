<?php

namespace App\Enums;

enum ProductStatus: string
{
    /** Being prepared by the seller, not visible to buyers. */
    case Draft = 'draft';

    /** Visible and purchasable. */
    case Active = 'active';

    /** Hidden by the seller (e.g. discontinued), kept for order history. */
    case Archived = 'archived';

    /** Hidden by an admin (policy violation). Sellers cannot undo this. */
    case Banned = 'banned';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draf',
            self::Active => 'Aktif',
            self::Archived => 'Diarsipkan',
            self::Banned => 'Diblokir',
        };
    }

    /**
     * Statuses a seller may choose. Banned is admin-only.
     *
     * @return list<self>
     */
    public static function sellerSelectable(): array
    {
        return [self::Draft, self::Active, self::Archived];
    }
}
