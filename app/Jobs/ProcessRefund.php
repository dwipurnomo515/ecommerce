<?php

namespace App\Jobs;

use App\Models\Refund;
use App\Refunds\RefundGateway;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sends one refund through the gateway. Same shape as ProcessPayout:
 * provider call outside DB locks, status re-checked on the locked row,
 * temporary trouble retried with backoff.
 */
class ProcessRefund implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /** @var list<int> */
    public array $backoff = [30, 60, 120, 300];

    public function __construct(public readonly int $refundId) {}

    public function handle(RefundGateway $gateway): void
    {
        $refund = Refund::query()->find($this->refundId);

        if ($refund === null || $refund->status !== Refund::PENDING) {
            return;
        }

        $result = $gateway->refund($refund);

        DB::transaction(function () use ($refund, $result): void {
            $locked = Refund::query()->lockForUpdate()->findOrFail($refund->id);

            if ($locked->status !== Refund::PENDING) {
                return;
            }

            $locked->processed_at = now();
            $locked->status = $result->succeeded ? Refund::REFUNDED : Refund::FAILED;
            $locked->reference = $result->reference;
            $locked->failure_reason = $result->failureReason;
            $locked->save();
        });

        if (! $result->succeeded) {
            // The buyer is still owed money: a person must sort it out.
            Log::error('Refund failed; contact the buyer', ['refund_id' => $refund->id, 'reason' => $result->failureReason]);
        }
    }

    public function failed(Throwable $e): void
    {
        Log::error('Refund stuck after retries; needs a manual check', ['refund_id' => $this->refundId, 'error' => $e->getMessage()]);
    }
}
