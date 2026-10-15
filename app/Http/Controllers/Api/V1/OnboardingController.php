<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Onhost\Domain\Organizations\OnboardingChecklist;
use Onhost\Platform\Commands\CommandScope;

/** N13: the derived onboarding checklist of the current organization (read only; hiding goes through PATCH /v1/me preferences). */
final class OnboardingController extends ApiController
{
    public function show(Request $request, OnboardingChecklist $checklist): JsonResponse
    {
        $organization = $this->api->organization($request);
        $this->api->authorize($request, 'organization.read', CommandScope::organization($organization->id));
        $user = $this->api->user($request);

        return response()->json(['data' => $checklist->build($organization->id, $user, (string) ($user->locale ?? 'cs'))]);
    }
}
