<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    /** The deadline passed, or the payment failed/was cancelled. */
    case Expired = 'expired';
    /**
     * Money arrived AFTER we had cancelled the checkout and released its
     * stock. We can't simply ship it (the stock may be sold), so a human
     * must refund it.
     */
    case NeedsRefund = 'needs_refund';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu pembayaran',
            self::Paid => 'Lunas',
            self::Expired => 'Kedaluwarsa',
            self::NeedsRefund => 'Perlu refund',
        };
    }
}
