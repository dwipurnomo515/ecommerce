<?php

namespace App\Payouts;

/**
 * The provider's final answer about one transfer. Temporary trouble is NOT
 * a result: it is a PayoutUnavailable exception, and the job tries again.
 */
final readonly class TransferResult
{
    private function __construct(
        public bool $succeeded,
        public ?string $reference,
        public ?string $failureReason,
    ) {}

    public static function succeeded(string $reference): self
    {
        return new self(true, $reference, null);
    }

    /** A permanent refusal (e.g. the account doesn't exist): money goes back. */
    public static function failed(string $reason): self
    {
        return new self(false, null, $reason);
    }
}
