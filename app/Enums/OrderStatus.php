<?php

namespace App\Enums;

/**
 * Life of one store's order. Fulfilment states (diproses, dikirim,
 * selesai) arrive in Tahap 6.
 */
enum OrderStatus: string
{
    case AwaitingPayment = 'awaiting_payment';

    /** Paid; waiting for the seller to process it. */
    case Paid = 'paid';

    /** Not paid in time, payment failed, or cancelled. Stock was returned. */
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::AwaitingPayment => 'Menunggu pembayaran',
            self::Paid => 'Dibayar',
            self::Cancelled => 'Dibatalkan',
        };
    }
}
