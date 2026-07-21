<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Domains\Billing\Actions\CalculatePlanChangeProrationAction;
use App\Domains\Products\Models\PricingPlan;
use App\Domains\Provisioning\Models\Service;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Live proration preview for a plan change.
 *
 * The maths lives in CalculatePlanChangeProrationAction so the figure the
 * customer is shown and the amount actually charged can never disagree.
 * The previous inline calculation credited the NEW plan's price for the days
 * already elapsed, which made an upgrade look cheaper than a downgrade.
 */
class PlanChangeProrateController extends Controller
{
    public function calculate(
        Request $request,
        Service $service,
        CalculatePlanChangeProrationAction $prorate,
    ): JsonResponse {
        $this->authorize('view', $service);

        $validated = $request->validate([
            'new_plan_id' => ['required', 'integer', 'exists:pricing_plans,id'],
        ]);

        /** @var PricingPlan $newPlan */
        $newPlan = PricingPlan::findOrFail($validated['new_plan_id']);

        $result     = $prorate->execute($service, $newPlan);
        $difference = $result['difference']->getMinorAmount()->toInt();

        return response()->json([
            'new_plan_name'   => $newPlan->name,
            'currency'        => $result['currency'],
            'days_remaining'  => $result['days_remaining'],
            'days_in_period'  => $result['days_in_period'],
            'new_monthly'     => $result['new_plan_price']->getMinorAmount()->toInt(),
            // Unused remainder of the CURRENT plan, credited back.
            'prorated_credit' => $result['unused_credit']->getMinorAmount()->toInt(),
            // New plan charged only for the days that remain.
            'prorated_charge' => $result['prorated_charge']->getMinorAmount()->toInt(),
            // Positive → pay now; negative → returned to the credit balance.
            'difference'      => $difference,
            'amount_due_now'  => max(0, $difference),
            'is_upgrade'      => $result['is_upgrade'],
        ]);
    }
}
