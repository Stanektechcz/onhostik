<?php

declare(strict_types=1);

namespace App\Domains\Integration\Services;

use App\Domains\Integration\Models\InboundWebhookLog;
use Illuminate\Support\Facades\Log;

final class WebhookDispatcher
{
    public function dispatch(InboundWebhookLog $log): void
    {
        try {
            $handled = $this->handle($log);

            $log->update(['status' => $handled ? 'processed' : 'ignored']);
        } catch (\Throwable $e) {
            Log::error('WebhookDispatcher failed', [
                'log_id' => $log->id,
                'source' => $log->source,
                'error'  => $e->getMessage(),
            ]);

            $log->update([
                'status'        => 'failed',
                'error_message' => mb_substr($e->getMessage(), 0, 255),
            ]);
        }
    }

    private function handle(InboundWebhookLog $log): bool
    {
        return match ($log->source) {
            'stripe'   => $this->handleStripe($log),
            'comgate'  => $this->handleComgate($log),
            'github'   => $this->handleGithub($log),
            default    => false,
        };
    }

    private function handleStripe(InboundWebhookLog $log): bool
    {
        $eventType = $log->event_type;

        return match ($eventType) {
            'payment_intent.succeeded',
            'charge.succeeded'         => $this->stripePaymentSucceeded($log),
            'invoice.payment_failed'   => $this->stripeInvoiceFailed($log),
            default                    => false,
        };
    }

    private function stripePaymentSucceeded(InboundWebhookLog $log): bool
    {
        // Stripe payment succeeded — log for audit; actual processing happens
        // through our own payment confirmation flow.
        Log::info('Stripe payment succeeded', [
            'webhook_log_id' => $log->id,
            'event_type'     => $log->event_type,
        ]);
        return true;
    }

    private function stripeInvoiceFailed(InboundWebhookLog $log): bool
    {
        Log::warning('Stripe invoice payment failed', [
            'webhook_log_id' => $log->id,
            'event_type'     => $log->event_type,
        ]);
        return true;
    }

    private function handleComgate(InboundWebhookLog $log): bool
    {
        // Comgate IPN notifications are processed separately via ComgateController.
        // Here we just log and acknowledge receipt.
        Log::info('Comgate webhook received', [
            'webhook_log_id' => $log->id,
        ]);
        return true;
    }

    private function handleGithub(InboundWebhookLog $log): bool
    {
        $event = $log->event_type;

        return match ($event) {
            'push',
            'release' => $this->githubDeploymentEvent($log),
            default   => false,
        };
    }

    private function githubDeploymentEvent(InboundWebhookLog $log): bool
    {
        Log::info('GitHub deployment event', [
            'webhook_log_id' => $log->id,
            'event'          => $log->event_type,
        ]);
        return true;
    }
}
