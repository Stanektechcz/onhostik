<?php

declare(strict_types=1);

namespace App\Domains\Communication\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Operator alerting for incidents that must not wait for someone to open
 * their inbox (audit I126).
 *
 * Two deliberate constraints:
 *
 *  - It is off unless a webhook is configured. An alerting path that silently
 *    does nothing is worse than none at all, because it gets trusted.
 *  - A failure to deliver never propagates. This is called from the middle of
 *    incident handling; an alert that throws would turn one outage into two.
 *    It logs instead, so the miss is at least visible afterwards.
 */
final class CriticalAlertDispatcher
{
    public function isConfigured(): bool
    {
        return $this->webhook() !== null;
    }

    /**
     * @param  array<string, scalar|null>  $context  Short key/value facts. Never
     *                                               secrets — this leaves the
     *                                               network to a third party.
     */
    public function send(string $title, string $message, array $context = []): bool
    {
        $webhook = $this->webhook();

        if ($webhook === null) {
            // Debug, not warning: on a deployment that never configured Slack
            // this is the expected state, and warning about it every time
            // trains people to filter the log.
            Log::debug('critical_alert.skipped_no_webhook', ['title' => $title]);

            return false;
        }

        try {
            $response = Http::timeout(5)->post($webhook, [
                'text'   => "*{$title}*\n{$message}",
                'blocks' => $this->blocks($title, $message, $context),
            ]);

            if ($response->failed()) {
                Log::warning('critical_alert.rejected', [
                    'title'  => $title,
                    'status' => $response->status(),
                ]);

                return false;
            }

            return true;
        } catch (Throwable $e) {
            Log::warning('critical_alert.failed', [
                'title' => $title,
                // Message only. The exception's trace can contain the webhook
                // URL, which is a credential.
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * @param  array<string, scalar|null>  $context
     * @return list<array<string, mixed>>
     */
    private function blocks(string $title, string $message, array $context): array
    {
        $blocks = [
            ['type' => 'header', 'text' => ['type' => 'plain_text', 'text' => mb_substr($title, 0, 150)]],
            ['type' => 'section', 'text' => ['type' => 'mrkdwn', 'text' => $message]],
        ];

        if ($context !== []) {
            $fields = [];

            foreach ($context as $key => $value) {
                $fields[] = ['type' => 'mrkdwn', 'text' => "*{$key}*\n" . (string) ($value ?? '—')];
            }

            // Slack rejects a section with more than ten fields.
            $blocks[] = ['type' => 'section', 'fields' => array_slice($fields, 0, 10)];
        }

        $blocks[] = [
            'type'     => 'context',
            'elements' => [[
                'type' => 'mrkdwn',
                'text' => sprintf('%s · %s', (string) config('app.name'), now()->format('d.m.Y H:i')),
            ]],
        ];

        return $blocks;
    }

    private function webhook(): ?string
    {
        $webhook = config('notifications.critical.slack_webhook');

        return is_string($webhook) && $webhook !== '' ? $webhook : null;
    }
}
