<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Marks the first-login guided panel tour as seen (finished or skipped), so it
 * never runs twice for the same user.
 */
final class OnboardingTourController extends Controller
{
    public function complete(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user !== null && $user->onboarding_tour_completed_at === null) {
            $user->forceFill(['onboarding_tour_completed_at' => now()])->save();
        }

        return response()->json(['ok' => true]);
    }
}
