<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CustomerOnboardingStep;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerOnboardingStepController extends Controller
{
    public function index(Request $request): View
    {
        $customerId  = $request->query('customer_id');
        $onlyPending = $request->boolean('pending');
        $steps       = CustomerOnboardingStep::when($customerId, fn($q) => $q->where('customer_id', $customerId))
            ->when($onlyPending, fn($q) => $q->whereNull('completed_at'))
            ->orderBy('customer_id')
            ->orderBy('step')
            ->paginate(20);
        return view('admin.customer-onboarding-steps.index', compact('steps', 'customerId', 'onlyPending'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'customer_id' => 'required|integer',
            'step'        => 'required|string|max:100',
            'description' => 'nullable|string|max:500',
            'is_required' => 'boolean',
        ]);
        $validated['is_required'] = $request->boolean('is_required', true);
        CustomerOnboardingStep::updateOrCreate(
            ['customer_id' => $validated['customer_id'], 'step' => $validated['step']],
            $validated
        );
        return back()->with('status', 'Krok onboardingu uložen.');
    }

    public function update(Request $request, CustomerOnboardingStep $customerOnboardingStep): RedirectResponse
    {
        $customerOnboardingStep->update([
            'completed_at' => $customerOnboardingStep->completed_at ? null : now(),
        ]);
        return back()->with('status', 'Krok aktualizován.');
    }
}
