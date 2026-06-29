<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\DiscountCode;
use App\Models\DiscountCodeUsage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DiscountCodeController extends Controller
{
    /**
     * Validate a discount code for the current customer.
     * Called via AJAX from the checkout/order create page.
     */
    public function validate(Request $request): JsonResponse
    {
        $request->validate(['code' => ['required', 'string', 'max:32']]);

        $code = DiscountCode::valid()
            ->where('code', strtoupper($request->string('code')->toString()))
            ->first();

        if (!$code) {
            return response()->json([
                'valid'   => false,
                'message' => 'Slevový kód je neplatný, expiroval nebo byl vyčerpán.',
            ], 422);
        }

        $customer = $request->user()?->customer;

        // One-per-customer check
        if ($customer && DiscountCodeUsage::where('discount_code_id', $code->id)
                ->where('customer_id', $customer->id)
                ->exists()) {
            return response()->json([
                'valid'   => false,
                'message' => 'Tento slevový kód jste již použili.',
            ], 422);
        }

        return response()->json([
            'valid'      => true,
            'code'       => $code->code,
            'type'       => $code->type,
            'value'      => (float) $code->value,
            'currency'   => $code->currency,
            'formatted'  => $code->formattedValue(),
            'message'    => "Slevový kód {$code->code} ({$code->formattedValue()}) byl aplikován.",
        ]);
    }
}
