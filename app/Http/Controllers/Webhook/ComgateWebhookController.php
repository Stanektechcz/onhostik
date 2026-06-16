<?php

declare(strict_types=1);

namespace App\Http\Controllers\Webhook;

use App\Domains\Billing\Actions\ProcessComgateWebhookAction;
use App\Domains\Billing\Services\Gateways\ComgateGateway;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Receives Comgate payment notifications.
 *
 * The response is always "code=0&message=OK" — Comgate retries on non-200.
 * Actual payment processing is handled synchronously inside
 * ProcessComgateWebhookAction (idempotent, guarded by UNIQUE transId).
 */
class ComgateWebhookController extends Controller
{
    public function __invoke(Request $request, ProcessComgateWebhookAction $action): Response
    {
        $payload = $request->all();

        try {
            $action->execute($payload, $request->ip());
        } catch (\Throwable) {
            // Already logged inside the action — let Comgate retry by still returning 200.
        }

        return response('code=0&message=OK', 200)
            ->header('Content-Type', 'text/plain');
    }
}
