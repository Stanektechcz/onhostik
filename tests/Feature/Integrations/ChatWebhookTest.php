<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Onhost\Domain\Integrations\ChatMessage;
use Onhost\Domain\Notifications\WebhookDispatcher;
use Onhost\Platform\Events\GenericEvent;
use Onhost\Platform\Outbox\OutboxPublisher;

/*
 * Integrations: one webhook endpoint, three chat tools. The URL decides the format — Slack incoming webhooks get
 * Block Kit, Microsoft Teams incoming webhooks a MessageCard, Discord an embed — and every other endpoint keeps the
 * signed platform envelope. The wording is shared, so the same event reads the same everywhere.
 */

it('recognises the chat tool behind a webhook URL', function () {
    expect(ChatMessage::kind('https://hooks.slack.com/services/T000/B000/xyz'))->toBe('slack')
        ->and(ChatMessage::kind('https://hooks.slack.com/workflows/T000/A000/1/abc'))->toBe('slack')
        ->and(ChatMessage::kind('https://contoso.webhook.office.com/webhookb2/guid@guid/IncomingWebhook/abc/def'))->toBe('teams')
        ->and(ChatMessage::kind('https://prod-12.westeurope.logic.azure.com/workflows/abc/triggers/manual/paths/invoke'))->toBe('teams')
        ->and(ChatMessage::kind('https://discord.com/api/webhooks/123/abcDEF_token'))->toBe('discord')
        ->and(ChatMessage::kind('https://example.com/hooks/onhost'))->toBeNull()
        ->and(ChatMessage::kind('https://evil.example/hooks.slack.com/services/x'))->toBeNull();
});

it('delivers platform events to Slack as Block Kit and to Teams as a MessageCard, keeping the signed envelope for plain endpoints', function () {
    [$user, $org] = $this->customerWithOrganization();
    Http::fake(['hooks.slack.com/*' => Http::response('ok', 200), '*.webhook.office.com/*' => Http::response('1', 200), 'example.com/*' => Http::response('', 204)]);
    $this->actingAs($user, 'sanctum');
    $this->postJson('/v1/webhooks', ['url' => 'https://hooks.slack.com/services/T000/B000/xyz', 'events' => ['*']])->assertCreated();
    $this->postJson('/v1/webhooks', ['url' => 'https://contoso.webhook.office.com/webhookb2/guid@guid/IncomingWebhook/abc/def', 'events' => ['*']])->assertCreated();
    $this->postJson('/v1/webhooks', ['url' => 'https://example.com/hooks/onhost', 'events' => ['*']])->assertCreated();

    app(OutboxPublisher::class)->publish(GenericEvent::of('invoice.issued', 'invoice', 'inv_x', ['number' => 'F-2026-0042', 'type' => 'invoice', 'total' => ['minor' => 121000, 'currency' => 'CZK'], 'notify' => true], $org->id));
    app(OutboxPublisher::class)->relayPending();
    app(WebhookDispatcher::class)->retryDue();

    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), 'hooks.slack.com')) {
            return false;
        }
        $body = $request->data();
        $header = $body['blocks'][0]['text']['text'] ?? '';
        $fields = collect($body['blocks'])->firstWhere('type', 'section')['fields'] ?? [];

        return str_contains($header, 'Doklad F-2026-0042') && str_contains((string) ($body['text'] ?? ''), 'F-2026-0042')
            && collect($fields)->contains(fn ($f) => str_contains($f['text'], '*total*') && str_contains($f['text'], '1 210,00 CZK'))
            && $request->hasHeader('X-ONhost-Signature');
    });
    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), 'webhook.office.com')) {
            return false;
        }
        $body = $request->data();

        return ($body['@type'] ?? '') === 'MessageCard' && str_contains((string) $body['title'], 'Doklad F-2026-0042')
            && collect($body['sections'][0]['facts'] ?? [])->contains(fn ($f) => $f['name'] === 'number' && $f['value'] === 'F-2026-0042')
            && preg_match('/^[0-9A-F]{6}$/', (string) $body['themeColor']) === 1;
    });
    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), 'example.com')) {
            return false;
        }
        $body = $request->data();

        return ($body['event'] ?? '') === 'invoice.issued' && ($body['data']['payload']['number'] ?? '') === 'F-2026-0042' && isset($body['id']);
    });
});
