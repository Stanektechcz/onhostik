<?php

declare(strict_types=1);

namespace Onhost\Domain\Notifications\Webhooks;

use Illuminate\Support\Str;
use Onhost\Domain\Notifications\Models\WebhookDelivery;
use Onhost\Domain\Notifications\Models\WebhookEndpoint;
use Onhost\Domain\Notifications\WebhookDispatcher;
use Onhost\Platform\Commands\Command;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Commands\CommandHandler;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Http\EgressGuard;

/** @implements CommandHandler<WebhookCommand> */
final class WebhookCommandHandler implements CommandHandler
{
    /** active endpoints per organization: every event is sent once per endpoint */
    public const MAX_ENDPOINTS = 10;

    public function __construct(private readonly WebhookDispatcher $webhooks, private readonly EgressGuard $egress) {}

    public function handle(Command $command, CommandContext $context): mixed
    {
        if (! $command instanceof WebhookCommand) {
            throw new \LogicException('WebhookCommandHandler handles WebhookCommand only');
        }

        return match ($command->op()) {
            'create' => $this->create($command, $context),
            'disable' => $this->disable($this->endpoint($command)),
            'enable' => $this->enable($this->endpoint($command)),
            'rotate_secret' => $this->rotate($this->endpoint($command)),
            'redeliver' => $this->redeliver($command),
            'ping' => $this->ping($this->endpoint($command)),
            default => throw new DomainError('webhook_op_unknown', 'Unknown webhook operation.', 422),
        };
    }

    /** @return array<string, mixed> the endpoint and its secret, shown this once (the replay store masks it) */
    private function create(WebhookCommand $command, CommandContext $context): array
    {
        $url = trim((string) $command->get('url'));
        if (! str_starts_with($url, 'https://') || strlen($url) > 500) {
            throw new DomainError('webhook_url_invalid', 'A webhook URL is an https:// address of at most 500 characters.', 422);
        }
        $this->egress->check($url); // refused now, with the reason, instead of failing quietly at the first delivery
        $events = WebhookEvents::normalize((array) $command->get('events', []));
        $active = WebhookEndpoint::query()->where('organization_id', $command->organizationId)->where('state', '!=', WebhookEndpoint::DISABLED)->count();
        if ($active >= self::MAX_ENDPOINTS) {
            throw DomainError::conflict('webhook_limit', 'An organization has at most '.self::MAX_ENDPOINTS.' webhook endpoints; remove one first.');
        }
        $secret = self::newSecret();
        $endpoint = WebhookEndpoint::query()->create(['organization_id' => $command->organizationId, 'url' => $url, 'secret' => $secret, 'events' => $events, 'state' => WebhookEndpoint::ACTIVE, 'failures' => 0, 'created_by' => $context->actorId]);

        return WebhookView::endpointResult($endpoint) + ['secret' => $secret];
    }

    /** @return array<string, mixed> */
    private function disable(WebhookEndpoint $endpoint): array
    {
        $endpoint->forceFill(['state' => WebhookEndpoint::DISABLED])->save();
        // what was still waiting will not be sent: it would only be refused at its attempt
        WebhookDelivery::query()->where('endpoint_id', $endpoint->id)->whereIn('state', [WebhookDelivery::PENDING, WebhookDelivery::FAILED])
            ->update(['state' => WebhookDelivery::DEAD, 'last_error' => 'endpoint disabled', 'next_attempt_at' => null]);

        return WebhookView::endpointResult($endpoint);
    }

    /** @return array<string, mixed> a suspended endpoint comes back with a clean failure count; a removed one stays removed */
    private function enable(WebhookEndpoint $endpoint): array
    {
        if ($endpoint->state === WebhookEndpoint::DISABLED) {
            throw DomainError::conflict('webhook_disabled', 'A removed webhook endpoint cannot be turned on again; create a new one.');
        }
        $endpoint->forceFill(['state' => WebhookEndpoint::ACTIVE, 'failures' => 0])->save();

        return WebhookView::endpointResult($endpoint);
    }

    /** @return array<string, mixed> takes effect at once: every attempt from now on, retries included, is signed with the new secret */
    private function rotate(WebhookEndpoint $endpoint): array
    {
        $this->assertNotDisabled($endpoint);
        $secret = self::newSecret();
        $endpoint->forceFill(['secret' => $secret])->save();

        return WebhookView::endpointResult($endpoint) + ['secret' => $secret];
    }

    /** @return array<string, mixed> */
    private function redeliver(WebhookCommand $command): array
    {
        $endpoint = $this->endpoint($command);
        $this->assertActive($endpoint);
        $delivery = WebhookDelivery::query()->where('endpoint_id', $endpoint->id)->find((string) $command->get('delivery_id'));
        if ($delivery === null) {
            throw DomainError::notFound('webhook delivery');
        }

        return WebhookView::delivery($this->webhooks->redeliver($delivery));
    }

    /** @return array<string, mixed> */
    private function ping(WebhookEndpoint $endpoint): array
    {
        $this->assertActive($endpoint);

        return WebhookView::delivery($this->webhooks->ping($endpoint));
    }

    private function endpoint(WebhookCommand $command): WebhookEndpoint
    {
        $endpoint = WebhookEndpoint::query()->where('organization_id', $command->organizationId)->find((string) $command->get('endpoint_id'));
        if ($endpoint === null) {
            throw DomainError::notFound('webhook');
        }

        return $endpoint;
    }

    private function assertActive(WebhookEndpoint $endpoint): void
    {
        if ($endpoint->state !== WebhookEndpoint::ACTIVE) {
            throw DomainError::conflict('webhook_not_active', $endpoint->isSuspended() ? 'The endpoint is suspended after repeated failures; turn it on again first.' : 'The endpoint was removed.');
        }
    }

    private function assertNotDisabled(WebhookEndpoint $endpoint): void
    {
        if ($endpoint->state === WebhookEndpoint::DISABLED) {
            throw DomainError::conflict('webhook_disabled', 'The endpoint was removed.');
        }
    }

    private static function newSecret(): string
    {
        return 'whsec_'.Str::random(40);
    }
}
