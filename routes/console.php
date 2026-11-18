<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Unpaid checkouts past their deadline give their stock back. Every few
// minutes is precise enough for a 24-hour window. withoutOverlapping: if a
// run is slow, the next one skips instead of piling up. (ExpireCheckout is
// safe to repeat anyway; this just avoids wasted work.)
Schedule::command('orders:expire')->everyFiveMinutes()->withoutOverlapping();

// Safety net for lost payment webhooks: ask Midtrans about pending
// checkouts that have a payment started.
Schedule::command('payments:sync')->everyTenMinutes()->withoutOverlapping();
