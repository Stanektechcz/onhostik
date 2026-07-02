<?php

declare(strict_types=1);

namespace App\Http\Controllers\Webhook;

use App\Domains\Billing\Actions\ProcessStripeWebhookAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Receives Stripe webhook events.
 *
 * Must be excluded from CSRF middleware — see bootstrap/app.php.
 * Stripe requires a 200 response to stop retrying.
 *
 * IMPORTANT: pass the raw body, NOT the parsed request — Stripe's HMAC
 * signature is computed over the raw bytes. Do not call $request->all()
 * before constructEvent().
 */
class StripeWebhookController extends Controller
{
    public function __invoke(Request $request, ProcessStripeWebhookAction $action): Response
    {
        $rawPayload = $request->getContent();
        $signature  = $request->header('Stripe-Signature', '');

        try {
            $action->execute($rawPayload, $signature, $request->ip() ?? '');
        } catch (\Throwable) {
            // Logged inside the action. Return 200 so Stripe doesn't retry
            // signature/body corruption — non-200 would cause infinite retries.
        }

        return response('{"received":true}', 200)
            ->header('Content-Type', 'application/json');
    }
}
