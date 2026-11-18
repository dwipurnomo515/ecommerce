<?php

namespace App\Enums;

/**
 * Life of one store's order:
 *
 *   AwaitingPayment ──(payment)──▶ Paid ──seller──▶ Processing ──seller + resi──▶ Shipped
 *          │                                                                         │
 *          └──(not paid / failed)──▶ Cancelled          buyer, or automatically ──▶ Completed
 *
 * Payment moves (AwaitingPayment → Paid / Cancelled) are made by the
 * payment code under the checkout lock. canTransitionTo() covers the
 * fulfilment moves that sellers and buyers make.
 */
enum OrderStatus: string
{
    case AwaitingPayment = 'awaiting_payment';

    /** Paid; waiting for the seller to start. */
    case Paid = 'paid';

    /** The seller is packing it. */
    case Processing = 'processing';

    /** Handed to the courier, with a tracking number. */
    case Shipped = 'shipped';

    /** The buyer confirmed receipt, or the confirmation window passed. */
    case Completed = 'completed';

    /** Not paid in time, payment failed, or cancelled. Stock was returned. */
    case Cancelled = 'cancelled';

    /** As the BUYER reads it. */
    public function label(): string
    {
        return match ($this) {
            self::AwaitingPayment => 'Menunggu pembayaran',
            self::Paid => 'Dibayar, menunggu diproses',
            self::Processing => 'Sedang dikemas',
            self::Shipped => 'Dikirim',
            self::Completed => 'Selesai',
            self::Cancelled => 'Dibatalkan',
        };
    }

    /** As the SELLER reads it: what they have to do. */
    public function sellerLabel(): string
    {
        return match ($this) {
            self::Paid => 'Perlu diproses',
            self::Processing => 'Siap dikirim',
            default => $this->label(),
        };
    }

    /**
     * The single source of truth for fulfilment moves. Steps can't be
     * skipped (Paid → Shipped) or undone (Shipped → Processing).
     */
    public function canTransitionTo(self $next): bool
    {
        return match ($this) {
            // The seller may still cancel (with a refund) until it ships.
            // After that the parcel is on its way: that's a dispute, not a
            // cancellation.
            self::Paid => $next === self::Processing || $next === self::Cancelled,
            self::Processing => $next === self::Shipped || $next === self::Cancelled,
            self::Shipped => $next === self::Completed,
            default => false,
        };
    }

    /**
     * Statuses a seller works with (unpaid and cancelled-before-payment
     * orders are none of the seller's business).
     *
     * @return list<self>
     */
    public static function forSellers(): array
    {
        return [self::Paid, self::Processing, self::Shipped, self::Completed];
    }
}
