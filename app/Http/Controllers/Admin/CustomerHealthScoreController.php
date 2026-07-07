<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Customer\Models\Customer;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class CustomerHealthScoreController extends \App\Http\Controllers\Controller
{
    public function index(Request $request): View
    {
        $tier = $request->input('tier');

        $query = Customer::query()
            ->withCount(['services', 'invoices'])
            ->with(['user'])
            ->whereNotNull('health_score')
            ->when($tier === 'healthy',  fn ($q) => $q->where('health_score', '>=', 80))
            ->when($tier === 'warning',  fn ($q) => $q->where('health_score', '>=', 50)->where('health_score', '<', 80))
            ->when($tier === 'critical', fn ($q) => $q->where('health_score', '<', 50))
            ->orderBy('health_score');

        $customers = $query->paginate(30)->withQueryString();

        $counts = [
            'healthy'  => Customer::whereNotNull('health_score')->where('health_score', '>=', 80)->count(),
            'warning'  => Customer::whereNotNull('health_score')->where('health_score', '>=', 50)->where('health_score', '<', 80)->count(),
            'critical' => Customer::whereNotNull('health_score')->where('health_score', '<', 50)->count(),
        ];

        return view('admin.customer-health-scores', compact('customers', 'counts', 'tier'));
    }
}
