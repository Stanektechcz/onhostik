<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Bi\Enums\CustomerSegment;
use App\Domains\Bi\Services\RevenueForecaster;
use App\Domains\Customer\Models\Customer;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

class BiController extends Controller
{
    public function index(RevenueForecaster $forecaster): View
    {
        $forecast = $forecaster->forecast();

        $segmentCounts = [];
        foreach (CustomerSegment::cases() as $seg) {
            $segmentCounts[$seg->value] = Customer::query()
                ->where('segment', $seg->value)
                ->count();
        }
        $segmentCounts['unknown'] = Customer::query()->whereNull('segment')->count();

        $atRiskCustomers = Customer::query()
            ->whereIn('segment', [CustomerSegment::AtRisk->value, CustomerSegment::Churned->value])
            ->orderByDesc('churn_risk_score')
            ->limit(10)
            ->get();

        $totalWithInsights = Customer::query()->whereNotNull('segment')->count();

        return view('admin.bi', compact('forecast', 'segmentCounts', 'atRiskCustomers', 'totalWithInsights'));
    }
}
