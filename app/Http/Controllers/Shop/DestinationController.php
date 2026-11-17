<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Shipping\Destination;
use App\Shipping\ShippingProvider;
use App\Shipping\ShippingUnavailable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * GET /wilayah?q=kebayoran: autocomplete for address forms.
 *
 * Plain JSON, not an Inertia page: the address form asks for suggestions
 * while the buyer types, without navigating anywhere.
 */
class DestinationController extends Controller
{
    public function __invoke(Request $request, ShippingProvider $shipping): JsonResponse
    {
        /** @var array{q: string} $data */
        $data = $request->validate([
            'q' => ['required', 'string', 'min:3', 'max:50'],
        ]);

        try {
            $destinations = $shipping->searchDestinations($data['q']);
        } catch (ShippingUnavailable $e) {
            // Details (missing key, HTTP 500...) go to the log, not to the buyer.
            Log::warning('Destination search failed', ['error' => $e->getMessage()]);

            return response()->json([
                'message' => 'Pencarian wilayah sedang tidak tersedia. Coba lagi sebentar lagi.',
            ], 503);
        }

        return response()->json([
            'data' => array_map(fn (Destination $d) => $d->toArray(), $destinations),
        ]);
    }
}
