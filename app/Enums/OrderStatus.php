<?php

namespace App\Enums;

/**
 * Life of one store's order. Only the first and the cancelled state exist
 * in code for now; payment (Tahap 5) and fulfilment (Tahap 6) add the rest.
 */
enum OrderStatus: string
{
    case AwaitingPayment = 'awaiting_payment';

    /** Not paid in time, or cancelled before payment. Stock was returned. */
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::AwaitingPayment => 'Menunggu pembayaran',
            self::Cancelled => 'Dibatalkan',
        };
    }
}
