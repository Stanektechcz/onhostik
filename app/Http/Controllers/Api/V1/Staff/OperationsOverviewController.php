<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Staff;

use App\Http\Controllers\Api\V1\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Onhost\Domain\Platform\OperationsOverview;
use Onhost\Platform\Commands\CommandScope;
use Onhost\Platform\Errors\DomainError;

/**
 * P16 (phase R): `GET /v1/staff/operations/overview` — the read-only shift overview of the page "Provoz". Each section shows only
 * to whoever holds its permission (OperationsOverview::SECTIONS); somebody holding none of them is refused.
 */
final class OperationsOverviewController extends ApiController
{
    public function show(Request $request, OperationsOverview $overview): JsonResponse
    {
        $can = fn (string $permission): bool => $this->api->can($request, $permission, CommandScope::global());
        if (array_filter(OperationsOverview::SECTIONS, $can) === []) {
            throw DomainError::forbidden('Missing permission '.implode(' or ', array_unique(OperationsOverview::SECTIONS)));
        }

        return response()->json(['data' => $overview->for($can)], 200, ['Cache-Control' => 'no-store']);
    }
}
