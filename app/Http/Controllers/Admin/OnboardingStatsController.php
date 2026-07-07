<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Customer\Models\Customer;
use App\Domains\Customer\Services\OnboardingService;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class OnboardingStatsController extends Controller
{
    public function __construct(private readonly OnboardingService $service)
    {
    }

    public function index(Request $request): View
    {
        $stats = $this->service->globalStats();

        // Customers who haven't finished — paginated
        $incomplete = Customer::with('user')
            ->where(function ($q): void {
                $q->whereNull('onboarding_completed_at');
            })
            ->orderByDesc('created_at')
            ->paginate(25);

        return view('admin.onboarding-stats', [
            'stats'      => $stats,
            'incomplete' => $incomplete,
            'stepLabels' => OnboardingService::STEP_LABELS,
        ]);
    }
}
