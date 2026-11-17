<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Payments\HandlePaymentNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * POST /webhooks/midtrans: Midtrans' server telling ours about a payment.
 * Set as "Payment Notification URL" in the Midtrans dashboard.
 */
class MidtransWebhookController extends Controller
{
    public function __invoke(Request $request, HandlePaymentNotification $handle): JsonResponse
    {
        /** @var array<string, mixed> $payload */
        $payload = $request->json()->all();

        $outcome = $handle->handle($payload);

        // 403 for a bad signature (Midtrans retries 4xx only twice).
        // Everything else is 200, including "unknown order" or "ignored":
        // retrying would not change the answer, only spam us.
        return response()->json(['outcome' => $outcome], $outcome === HandlePaymentNotification::BAD_SIGNATURE ? 403 : 200);
    }
}
