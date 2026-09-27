<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Staff;

use App\Http\Controllers\Api\V1\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Onhost\Domain\Identity\Commands\MfaResetCommand;
use Onhost\Domain\Identity\Commands\OwnerRecoveryCommand;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Platform\Errors\DomainError;

/**
 * Support recovering a customer owner who lost access, and resetting anybody else's second factor (TASK-0042, permission program
 * D21). Everything is decided by the bus: `iam.mfa.reset` in staff mode, the owner recovery CRITICAL (a second person) with its
 * own notice period, the MFA reset HIGH and refused for a customer owner. The controller only validates and dispatches.
 */
final class OwnerRecoveryController extends ApiController
{
    public function open(Request $request, string $organization): JsonResponse
    {
        $org = $this->organization($organization);
        $data = $request->validate([
            'mode' => ['required', 'in:mfa_reset,transfer'], 'new_owner_user_id' => ['required_if:mode,transfer', 'nullable', 'string', 'max:40'],
            'reason' => ['required', 'string', 'min:10', 'max:2000'], 'ticket_ref' => ['required', 'string', 'max:60'],
        ]);
        $payload = ['op' => 'open', 'organization_id' => $org->id, 'mode' => $data['mode'], 'reason' => $data['reason'], 'ticket_ref' => $data['ticket_ref']]
            + ($data['mode'] === 'transfer' ? ['new_owner_user_id' => (string) $data['new_owner_user_id']] : []);

        return $this->dispatch(new OwnerRecoveryCommand($this->idempotencyKey($request, 'owner-recovery.open:'.$org->id), $payload), $this->api->context($request), 201);
    }

    public function complete(Request $request, string $organization): JsonResponse
    {
        $org = $this->organization($organization);

        return $this->dispatch(new OwnerRecoveryCommand($this->onceKey($request, 'owner-recovery.complete:'.$org->id), ['op' => 'complete', 'organization_id' => $org->id]), $this->api->context($request));
    }

    public function cancel(Request $request, string $organization): JsonResponse
    {
        $org = $this->organization($organization);

        return $this->dispatch(new OwnerRecoveryCommand($this->onceKey($request, 'owner-recovery.cancel:'.$org->id), ['op' => 'cancel', 'organization_id' => $org->id]), $this->api->context($request));
    }

    public function mfaReset(Request $request, string $user): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:10', 'max:500']]);

        return $this->dispatch(new MfaResetCommand($this->idempotencyKey($request, 'mfa-reset:'.$user), ['user_id' => $user, 'reason' => $data['reason']]), $this->api->context($request));
    }

    private function organization(string $id): Organization
    {
        return Organization::query()->find($id) ?? throw DomainError::notFound('organization');
    }
}
