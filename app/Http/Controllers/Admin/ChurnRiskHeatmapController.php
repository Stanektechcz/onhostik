<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Bi\Enums\CustomerSegment;
use App\Domains\Customer\Models\Customer;
use App\Http\Controllers\Controller;
use Illuminate\View\View;

class ChurnRiskHeatmapController extends Controller
{
    public function index(): View
    {
        $segments = CustomerSegment::cases();
        $buckets  = ['low' => '0–30', 'medium' => '31–60', 'high' => '61–100'];

        $data = [];
        foreach ($segments as $segment) {
            $data[$segment->value] = [
                'low'    => Customer::where('segment', $segment->value)->whereBetween('churn_risk_score', [0, 30])->count(),
                'medium' => Customer::where('segment', $segment->value)->whereBetween('churn_risk_score', [31, 60])->count(),
                'high'   => Customer::where('segment', $segment->value)->whereBetween('churn_risk_score', [61, 100])->count(),
            ];
        }

        $topRisk = Customer::with('user')
            ->whereNotNull('churn_risk_score')
            ->orderByDesc('churn_risk_score')
            ->limit(10)
            ->get();

        return view('admin.churn-risk-heatmap', compact('data', 'segments', 'buckets', 'topRisk'));
    }
}
