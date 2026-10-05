<?php

declare(strict_types=1);

namespace Onhost\Domain\Notifications\Webhooks;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
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

    /** one test event per endpoint in this many seconds */
    public const PING_COOLDOWN_SECONDS = 30;

    /** redelivery requests per endpoint and hour (counted per request, whatever became of it) */
    public const REDELIVERS_PER_HOUR = 20;

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
            'rotate_secret' => $this->rotate($this->endpoint($command), $command->get('overlap') !== false),
            'redeliver' => $this->redeliver($command),
            'ping' => $this->ping($this->endpoint($command, lock: true)),
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
        $this->egress->check($url); // the address first (an inward one is refused as such), with the reason, not at the first delivery
        $problem = WebhookDispatcher::destinationProblem($url);
        if ($problem !== null) {
            throw new DomainError('webhook_port_not_allowed', ucfirst($problem).'.', 422, ['ports' => WebhookDispatcher::ALLOWED_PORTS]);
        }
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

    /**
     * The new secret signs every attempt from now on, retries included. G7 (TASK-0115): the secret it replaces still signs
     * X-ONhost-Signature-Previous for `onhost.webhooks.secret_overlap_minutes` (0 = not at all), so the receiver can switch without
     * losing a delivery; a rotation inside the window keeps only the secret it replaced. The old secret is never shown again.
     *
     * @return array<string, mixed>
     */
    private function rotate(WebhookEndpoint $endpoint, bool $withOverlap = true): array
    {
        $this->assertNotDisabled($endpoint);
        $secret = self::newSecret();
        $overlap = $withOverlap ? (int) config('onhost.webhooks.secret_overlap_minutes', 60) : 0; // `overlap: false` — a leaked secret ends now
        $until = $overlap > 0 ? now()->addMinutes($overlap) : null;
        $endpoint->forceFill(['secret' => $secret, 'previous_secret' => $until === null ? null : (string) $endpoint->secret, 'previous_secret_expires_at' => $until])->save();

        return WebhookView::endpointResult($endpoint) + ['secret' => $secret, 'previous_secret_valid_until' => $until?->toIso8601String()];
    }

    /** @return array<string, mixed> */
    private function redeliver(WebhookCommand $command): array
    {
        $endpoint = $this->endpoint($command);
        $this->assertActive($endpoint);
        $key = 'webhook-redeliver:'.$endpoint->id;
        if (RateLimiter::tooManyAttempts($key, self::REDELIVERS_PER_HOUR)) {
            throw new DomainError('webhook_redeliver_rate', 'Too many redeliveries for this endpoint; try again later.', 429, ['retry_after' => RateLimiter::availableIn($key)]);
        }
        RateLimiter::hit($key, 3600);
        $delivery = WebhookDelivery::query()->where('endpoint_id', $endpoint->id)->find((string) $command->get('delivery_id'));
        if ($delivery === null) {
            throw DomainError::notFound('webhook delivery');
        }
        if ($delivery->attempts >= WebhookDispatcher::MAX_ATTEMPTS) {
            throw DomainError::conflict('webhook_redeliver_limit', 'This delivery was attempted '.WebhookDispatcher::MAX_ATTEMPTS.' times; it is not sent again.');
        }

        return WebhookView::delivery($this->webhooks->redeliver($delivery));
    }

    /** The cache entry that claims an endpoint's ping window (F12a): it lasts the whole cooldown, not just while the request runs. */
    public static function pingLockKey(string $endpointId): string
    {
        return 'webhook-ping:'.$endpointId;
    }

    /**
     * One test event per endpoint and cooldown. F12a (TASK-0106): reading the last ping and then writing one was a race — parallel
     * pings without an Idempotency-Key (each its own command since F12a) all read "no ping yet" and all sent one. Two guards now:
     * the endpoint row is read FOR UPDATE (endpoint(lock: true)), so on PostgreSQL a second ping waits for the first to commit and
     * then sees its delivery; and the window is claimed with an atomic Cache::add that lasts the cooldown (SET NX on Redis, INSERT … ON
     * CONFLICT DO NOTHING on the database store — no failed statement inside the bus transaction), which holds on any database and
     * while the first delivery is not committed yet. A ping that fails in the handler gives the window back; a rollback after the
     * handler keeps it closed for one cooldown at most (on the database store the claim rolls back with it).
     *
     * @return array<string, mixed>
     */
    private function ping(WebhookEndpoint $endpoint): array
    {
        $this->assertActive($endpoint);
        $last = WebhookDelivery::query()->where('endpoint_id', $endpoint->id)->where('event', WebhookEvents::PING)->max('created_at');
        $wait = $last === null ? 0 : self::PING_COOLDOWN_SECONDS - (int) Carbon::parse((string) $last)->diffInSeconds(now(), true);
        if ($wait > 0) {
            throw self::pingCooldown($wait);
        }
        $claim = self::pingLockKey($endpoint->id);
        if (! Cache::add($claim, now()->getTimestamp(), self::PING_COOLDOWN_SECONDS)) {
            throw self::pingCooldown(self::PING_COOLDOWN_SECONDS); // another ping holds the window (possibly not committed yet)
        }
        try {
            return WebhookView::delivery($this->webhooks->ping($endpoint));
        } catch (\Throwable $e) {
            Cache::forget($claim);
            throw $e;
        }
    }

    private static function pingCooldown(int $wait): DomainError
    {
        return new DomainError('webhook_ping_cooldown', 'One test event per endpoint every '.self::PING_COOLDOWN_SECONDS.' seconds.', 429, ['retry_after' => $wait]);
    }

    private function endpoint(WebhookCommand $command, bool $lock = false): WebhookEndpoint
    {
        $query = WebhookEndpoint::query()->where('organization_id', $command->organizationId);
        $endpoint = ($lock ? $query->lockForUpdate() : $query)->find((string) $command->get('endpoint_id')); // the bus runs the handler in a transaction
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
