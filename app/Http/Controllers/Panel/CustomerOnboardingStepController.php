<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\CustomerOnboardingStep;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerOnboardingStepController extends Controller
{
    public function index(Request $request): View
    {
        $customerId = $request->user()->customer?->id;
        abort_unless($customerId !== null, 403);

        $steps = CustomerOnboardingStep::where('customer_id', $customerId)
            ->orderBy('is_required', 'desc')
            ->orderBy('step')
            ->get();

        $completed = $steps->filter(fn($s) => $s->completed_at !== null)->count();
        $total     = $steps->count();

        return view('panel.customer-onboarding-steps.index', compact('steps', 'completed', 'total'));
    }
}
