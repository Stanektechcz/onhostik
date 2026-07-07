<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Bi\Enums\CustomerSegment;
use App\Domains\Customer\Models\Customer;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class CustomerSegmentationController extends Controller
{
    public function index(Request $request): View
    {
        $selected = $request->string('segment')->toString();

        // Per-segment stats
        $segments = [];
        foreach (CustomerSegment::cases() as $seg) {
            $query = Customer::query()->where('segment', $seg->value);
            $segments[$seg->value] = [
                'segment'         => $seg,
                'count'           => $query->count(),
                'avg_health'      => (int) round((float) (clone $query)->avg('health_score')),
                'avg_churn_risk'  => (int) round((float) (clone $query)->avg('churn_risk_score')),
            ];
        }

        $unassignedCount = Customer::query()->whereNull('segment')->count();

        // Customer table — filterable by segment
        $customersQuery = Customer::query()
            ->with('user')
            ->orderByDesc('health_score');

        if ($selected !== '' && $selected !== 'unknown') {
            $customersQuery->where('segment', $selected);
        } elseif ($selected === 'unknown') {
            $customersQuery->whereNull('segment');
        }

        $customers = $customersQuery->paginate(25)->withQueryString();

        return view('admin.customer-segmentation', compact('segments', 'unassignedCount', 'customers', 'selected'));
    }
}
