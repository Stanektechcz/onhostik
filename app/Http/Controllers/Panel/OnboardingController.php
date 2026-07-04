<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class OnboardingController extends Controller
{
    /** Customer dismisses the onboarding checklist. */
    public function dismiss(Request $request): RedirectResponse
    {
        $customer = $request->user()?->customer;
        abort_if($customer === null, 403);

        if ($customer->onboarding_completed_at === null) {
            $customer->update(['onboarding_completed_at' => now()]);
        }

        return redirect()->route('panel.dashboard');
    }
}
