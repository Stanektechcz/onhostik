<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Bi\Actions\ChurnRiskScorer;
use App\Domains\Bi\Enums\CustomerSegment;
use App\Domains\Bi\Services\CohortAnalyser;
use App\Domains\Bi\Services\MrrService;
use App\Domains\Bi\Services\RevenueForecaster;
use App\Domains\Customer\Models\Customer;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

class BiV2Controller extends Controller
{
    public function __construct(
        private readonly MrrService       $mrr,
        private readonly CohortAnalyser   $cohort,
        private readonly RevenueForecaster $forecaster,
    ) {}

    public function index(): View
    {
        $mrrSummary   = $this->mrr->summary();
        $mrrTrend     = $this->mrr->mrrTrend(6);
        $clvBySegment = $this->mrr->clvBySegment();
        $cohortData   = $this->cohort->analyse(6, 4);
        $newPerMonth  = $this->cohort->newCustomersPerMonth(6);
        $forecast     = $this->forecaster->forecast();

        $topAtRisk = Customer::query()
            ->whereIn('segment', [CustomerSegment::AtRisk->value, CustomerSegment::Churned->value])
            ->whereNotNull('churn_risk_score')
            ->orderByDesc('churn_risk_score')
            ->with('user')
            ->limit(10)
            ->get();

        return view('admin.bi-v2', compact(
            'mrrSummary',
            'mrrTrend',
            'clvBySegment',
            'cohortData',
            'newPerMonth',
            'forecast',
            'topAtRisk',
        ));
    }
}
