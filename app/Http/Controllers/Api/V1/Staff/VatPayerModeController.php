<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Staff;

use App\Http\Controllers\Api\V1\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Onhost\Domain\Tax\Commands\SetVatPayerModeCommand;
use Onhost\Domain\Tax\VatPayerMode;
use Onhost\Platform\Commands\CommandScope;
use Onhost\Platform\Errors\DomainError;

/**
 * The seller's VAT mode (G2): finance reads it and switches it. The switch is CRITICAL (SetVatPayerModeCommand): a fresh step-up
 * and a second person — the first request answers 403 `approval_required` with the request's `approval_id`, somebody else
 * approves it (`POST /v1/staff/approvals/{id}/decision`), and the same request repeated with `approval_ids: [id]` runs once. The
 * approval binds this very body; the idempotency key is the approval being consumed, never a timestamp.
 */
final class VatPayerModeController extends ApiController
{
    public function show(Request $request, VatPayerMode $mode): JsonResponse
    {
        if (! $this->api->can($request, 'billing.tax_rule.manage', CommandScope::global())) {
            throw DomainError::forbidden('Finance only.');
        }

        return $this->ok($mode->report());
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate(['payer' => ['required', 'boolean'], 'reason' => ['required', 'string', 'min:10', 'max:500']]);
        $payload = ['payer' => (bool) $data['payer'], 'reason' => (string) $data['reason']];
        $approvals = array_values(array_filter((array) $request->input('approval_ids', []), 'is_string'));
        sort($approvals);
        // the request that asks (no approval yet) is refused before anything runs; the one that consumes an approval is keyed by it
        $key = 'vat.payer-mode:'.($approvals === [] ? 'ask:'.substr(hash('sha256', (string) json_encode($payload)), 0, 24) : substr(hash('sha256', implode(',', $approvals)), 0, 32));

        return $this->dispatch(new SetVatPayerModeCommand($key, $payload), $this->api->context($request, null, $payload['reason']));
    }
}
