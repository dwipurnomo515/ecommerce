<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    /** The payment deadline passed. */
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu pembayaran',
            self::Paid => 'Lunas',
            self::Expired => 'Kedaluwarsa',
        };
    }
}
