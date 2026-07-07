<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Domains\Products\Models\PricingPlan;
use App\Domains\Provisioning\Models\Service;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlanChangeProrateController extends Controller
{
    public function calculate(Request $request, Service $service): JsonResponse
    {
        $customer = $request->user()->customer;
        abort_if($service->customer_id !== $customer?->id, 403);

        $validated = $request->validate([
            'new_plan_id' => ['required', 'integer', 'exists:pricing_plans,id'],
        ]);

        /** @var PricingPlan $newPlan */
        $newPlan  = PricingPlan::findOrFail($validated['new_plan_id']);
        $currency = $customer->preferred_currency->value;

        $priceField   = 'price_' . strtolower($currency);
        $newMonthly   = (int) ($newPlan->$priceField ?? 0);

        $daysLeft    = (int) now()->diffInDays($service->next_due_date, false);
        $daysLeft    = max(0, $daysLeft);
        $daysInMonth = (int) now()->daysInMonth;

        $credit  = $daysLeft > 0 ? (int) round($newMonthly * $daysLeft / $daysInMonth) : 0;
        $due     = max(0, $newMonthly - $credit);

        return response()->json([
            'new_plan_name'   => $newPlan->name,
            'new_monthly'     => $newMonthly,
            'days_remaining'  => $daysLeft,
            'prorated_credit' => $credit,
            'amount_due_now'  => $due,
            'currency'        => $currency,
        ]);
    }
}
