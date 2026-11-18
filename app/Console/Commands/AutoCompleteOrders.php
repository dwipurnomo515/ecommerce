<?php

namespace App\Console\Commands;

use App\Actions\Orders\FulfilOrder;
use App\Enums\OrderStatus;
use App\Exceptions\InvalidStatusTransition;
use App\Models\Order;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('orders:auto-complete')]
#[Description('Complete shipped orders whose buyer did not confirm receipt in time')]
class AutoCompleteOrders extends Command
{
    /**
     * Buyers often forget to press "Pesanan diterima". Without this, the
     * seller would wait forever (and, from Tahap 7, never get paid out).
     */
    public function handle(FulfilOrder $fulfil): int
    {
        $completed = 0;

        Order::query()
            ->where('status', OrderStatus::Shipped)
            ->where('shipped_at', '<=', now()->subDays(Order::AUTO_COMPLETE_DAYS))
            ->chunkById(100, function ($orders) use ($fulfil, &$completed): void {
                foreach ($orders as $order) {
                    try {
                        $fulfil->complete($order);
                        $completed++;
                    } catch (InvalidStatusTransition) {
                        // The buyer confirmed it a moment ago. Nothing to do.
                    }
                }
            });

        $this->info("{$completed} pesanan diselesaikan otomatis.");

        return self::SUCCESS;
    }
}
