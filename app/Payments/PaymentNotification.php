<?php

namespace App\Payments;

/**
 * A provider's payment notification, translated into what the shop cares
 * about. The provider's own vocabulary (settlement, capture, deny...) stays
 * inside the gateway class.
 */
final readonly class PaymentNotification
{
    public const PAID = 'paid';

    public const FAILED = 'failed';

    /** Still waiting (e.g. a VA number was issued): nothing to do. */
    public const WAITING = 'waiting';

    /** Something we don't act on automatically (refunds...). Logged only. */
    public const OTHER = 'other';

    public function __construct(
        /** Our checkout code. */
        public string $orderId,
        public string $result,
        /** Whole Rupiah, as the provider states it. */
        public int $amount,
        public ?string $method,
        public ?string $reference,
        /** The provider's raw status, for logs. */
        public string $rawStatus,
    ) {}
}
