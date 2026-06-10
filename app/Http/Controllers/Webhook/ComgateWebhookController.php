<?php

declare(strict_types=1);

namespace App\Http\Controllers\Webhook;

use App\Domains\Billing\Models\PaymentWebhookLog;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Receives Comgate payment notifications.
 *
 * Phase 1: the webhook is only RECEIVED and logged (sanitized) so no
 * notification is ever lost. Actual processing is wired in Phase 5 by
 * dispatching a queued job that runs ProcessComgateWebhookAction —
 * which re-fetches the status server-to-server and is fully idempotent.
 * No gateway call happens here, and never will (controllers stay thin).
 */
class ComgateWebhookController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $payload = $request->all();
        unset($payload['secret'], $payload['password'], $payload['merchant']);

        PaymentWebhookLog::create([
            'provider'   => 'comgate',
            'event_id'   => $payload['transId'] ?? null,
            'payload'    => $payload,
            'ip_address' => $request->ip(),
        ]);

        // Comgate expects a plain-text "code=0&message=OK" acknowledgement.
        return response('code=0&message=OK', 200)
            ->header('Content-Type', 'text/plain');
    }
}
