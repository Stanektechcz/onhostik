<?php

declare(strict_types=1);

namespace App\Http\Controllers\Webhook;

use App\Domains\Billing\Actions\ProcessGopayWebhookAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Receives GoPay IPN (Instant Payment Notification) callbacks.
 *
 * GoPay POSTs to notify_url with query param ?id={paymentId}.
 * Must return 200 — GoPay retries on non-200.
 */
class GopayWebhookController extends Controller
{
    public function __invoke(Request $request, ProcessGopayWebhookAction $action): Response
    {
        $paymentId = $request->query('id', '');

        if (! is_string($paymentId) || $paymentId === '') {
            return response('missing id', 400);
        }

        try {
            $action->execute($paymentId, $request->ip() ?? '');
        } catch (\Throwable) {
            // Logged inside the action — return 200 so GoPay doesn't storm our inbox.
        }

        return response('OK', 200);
    }
}
