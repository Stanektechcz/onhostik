<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Staff;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Controllers\Api\V1\BillingController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Onhost\Domain\Billing\DunningService;
use Onhost\Domain\Billing\Models\DunningCase;
use Onhost\Domain\Billing\ReportService;
use Onhost\Platform\Commands\CommandScope;

/** Admin `#/reporty`, `#/fakturace`: MRR/ARR, collections and ageing, churn, ledger revenue, dunning queue. */
final class ReportController extends ApiController
{
    public function mrr(Request $request, ReportService $reports): JsonResponse
    {
        $this->api->authorize($request, 'report.read', CommandScope::global());

        return response()->json(['data' => $reports->mrr()]);
    }

    public function collections(Request $request, ReportService $reports): JsonResponse
    {
        $this->api->authorize($request, 'report.read', CommandScope::global());

        return response()->json(['data' => $reports->collections((int) $request->query('days', '30'))]);
    }

    public function churn(Request $request, ReportService $reports): JsonResponse
    {
        $this->api->authorize($request, 'report.read', CommandScope::global());

        return response()->json(['data' => $reports->churn((int) $request->query('months', '6'))]);
    }

    public function revenue(Request $request, ReportService $reports): JsonResponse
    {
        $this->api->authorize($request, 'report.read', CommandScope::global());

        return response()->json(['data' => $reports->revenueByMonth((int) $request->query('months', '12'))]);
    }

    public function dunning(Request $request): JsonResponse
    {
        $this->api->authorize($request, 'billing.dunning.manage', CommandScope::global());
        $query = DunningCase::query();
        if ($request->boolean('open', true)) {
            $query->whereNotIn('state', [DunningCase::RESOLVED, DunningCase::TERMINATED]);
        }

        return $this->api->paginate($request, $query, fn (DunningCase $c) => BillingController::case($c) + ['organization_id' => $c->organization_id], 'due_at');
    }

    public function runDunning(Request $request, DunningService $dunning): JsonResponse
    {
        $this->api->authorizeAction($request, 'billing.dunning.manage', CommandScope::global()); // suspends and terminates: the step-up the bus would ask

        return response()->json(['data' => $dunning->tick($this->api->context($request))]);
    }
}
