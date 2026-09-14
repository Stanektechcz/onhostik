<?php

declare(strict_types=1);

namespace Onhost\Domain\Incidents;

use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Request;
use Onhost\Domain\Incidents\Models\OnCallAlert;
use Onhost\Domain\Notifications\Models\Notification;
use Onhost\Domain\Provisioning\AutomationLedger;
use Onhost\Platform\Audit\AuditRecorder;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Events\GenericEvent;
use Onhost\Platform\Observability\Tracer;
use Onhost\Platform\Outbox\OutboxEventDispatched;
use Onhost\Platform\Outbox\OutboxPublisher;
use Onhost\Platform\Secrets\SecretRef;
use Onhost\Platform\Secrets\SecretStore;
use Onhost\Providers\Contracts\OnCallProvider;
use Onhost\Providers\OnCall\OpsgenieOnCallProvider;
use Onhost\Providers\OnCall\PagerDutyOnCallProvider;
use Onhost\Providers\OnCall\WebhookOnCallProvider;
use Throwable;

/**
 * On-call escalation (audit §5q-1). The operational events the platform already raises (queue stalled, integration
 * down, BMC alert, capacity low, SLA burn rate, incidents) open one alert per subject; the pager behind
 * `ONHOST_ONCALL_PROVIDER` (PagerDuty, Opsgenie or a signed webhook) is triggered at once. Nobody acknowledging within
 * `escalate_after_minutes` re-pages with a higher severity (rule `oncall.escalate`, at most `max_escalations` times);
 * an acknowledgement from the console or the pager's own webhook stops it; the recovery event (or a person) resolves
 * the alert on both sides. Without a provider the alerts still live in the console — the inbox stays the ground truth.
 */
final class OnCallService
{
    public const RULE = 'oncall.escalate';

    private ?OnCallProvider $provider = null;

    private bool $providerResolved = false;

    public function __construct(private readonly HttpFactory $http, private readonly SecretStore $secrets, private readonly OutboxPublisher $outbox, private readonly AuditRecorder $audit, private readonly AutomationLedger $ledger) {}

    /** Outbox listener: paging events open an alert, recovery events resolve theirs; the on-call's own events are ignored. */
    public function handle(OutboxEventDispatched $event): void
    {
        $m = $event->message;
        if (str_starts_with($m->name, 'oncall.')) {
            return;
        }
        $resolves = (array) config('onhost.oncall.resolves', []);
        if (isset($resolves[$m->name])) {
            $this->resolveByDedup(self::dedupKey((string) $resolves[$m->name], (string) $m->aggregate_type, (string) $m->aggregate_id), 'event:'.$m->name);

            return;
        }
        $events = (array) config('onhost.oncall.events', []);
        if (! array_key_exists($m->name, $events)) {
            return;
        }
        $p = (array) $m->payload;
        $note = Notification::query()->where('audience', 'internal')->where('event', $m->name)->where('ref_id', $m->aggregate_id)->where('created_at', '>=', now()->subMinutes(5))->orderByDesc('id')->first();
        $title = $note?->title ?? ($m->name.' · '.$m->aggregate_id);
        $body = $note?->body ?? mb_substr((string) json_encode(array_intersect_key($p, array_flip(['key', 'node', 'role', 'region', 'error', 'component', 'severity'])), JSON_UNESCAPED_UNICODE), 0, 500);
        $this->open($m->name, (string) $m->aggregate_type, (string) $m->aggregate_id, $title, $body, $note?->surface, (string) ($events[$m->name] ?: 'warn'));
    }

    /** One active alert per subject; a repeat of the event while it is open does not page again. */
    public function open(string $event, string $aggregateType, string $aggregateId, string $title, ?string $body, ?string $surface, string $severity = 'warn'): OnCallAlert
    {
        $dedup = self::dedupKey($event, $aggregateType, $aggregateId);
        $existing = OnCallAlert::query()->where('dedup_key', $dedup)->whereIn('state', [OnCallAlert::OPEN, OnCallAlert::ACKED, OnCallAlert::ESCALATED])->orderByDesc('id')->first();
        if ($existing !== null) {
            $existing->forceFill(['meta' => array_merge((array) $existing->meta, ['repeats' => (int) data_get($existing->meta, 'repeats', 0) + 1, 'last_seen_at' => now()->toIso8601String()])])->save();

            return $existing;
        }
        $alert = OnCallAlert::query()->create([
            'event' => $event, 'dedup_key' => $dedup, 'severity' => in_array($severity, ['info', 'warn', 'hot'], true) ? $severity : 'warn', 'state' => OnCallAlert::OPEN,
            'title' => mb_substr($title, 0, 250), 'body' => $body, 'surface' => $surface, 'provider' => $this->provider()?->name(), 'escalate_after' => now()->addMinutes($this->escalateAfterMinutes()),
            'meta' => array_filter(['aggregate' => ['type' => $aggregateType, 'id' => $aggregateId], 'assignee' => app(OnCallRota::class)->assignee(), 'correlation_id' => CommandContext::currentCorrelationId()]), // §5r-1: the person on the rota right now
        ]);
        $this->page($alert, 0);
        $this->outbox->publish(GenericEvent::of('oncall.alert.opened', 'oncall_alert', $alert->id, self::present($alert)));

        return $alert;
    }

    public function acknowledge(OnCallAlert $alert, string $by, ?CommandContext $context = null): OnCallAlert
    {
        if ($alert->state === OnCallAlert::RESOLVED) {
            throw new DomainError('oncall_alert_resolved', 'This alert is resolved already.', 409, ['state' => $alert->state]);
        }
        if ($alert->state === OnCallAlert::ACKED) {
            return $alert;
        }
        $alert->forceFill(['state' => OnCallAlert::ACKED, 'acked_by' => mb_substr($by, 0, 80), 'acked_at' => now(), 'escalate_after' => null])->save();
        $this->provider()?->acknowledge($alert->dedup_key, $alert->provider_ref, $by);
        $this->audit->record($context ?? CommandContext::system('oncall'), 'oncall.alert.ack', 'succeeded', ['alert' => $alert->id, 'by' => $by], 'oncall_alert', $alert->id);
        $this->outbox->publish(GenericEvent::of('oncall.alert.acknowledged', 'oncall_alert', $alert->id, self::present($alert)));

        return $alert;
    }

    public function resolve(OnCallAlert $alert, string $by, ?CommandContext $context = null): OnCallAlert
    {
        if ($alert->state === OnCallAlert::RESOLVED) {
            return $alert;
        }
        $alert->forceFill(['state' => OnCallAlert::RESOLVED, 'resolved_by' => mb_substr($by, 0, 80), 'resolved_at' => now(), 'escalate_after' => null])->save();
        $this->provider()?->resolve($alert->dedup_key, $alert->provider_ref, $by);
        $this->audit->record($context ?? CommandContext::system('oncall'), 'oncall.alert.resolve', 'succeeded', ['alert' => $alert->id, 'by' => $by], 'oncall_alert', $alert->id);
        $this->outbox->publish(GenericEvent::of('oncall.alert.resolved', 'oncall_alert', $alert->id, self::present($alert)));

        return $alert;
    }

    /** The recovery event closes the alert of its subject (nothing to do when there is none). */
    public function resolveByDedup(string $dedup, string $by): ?OnCallAlert
    {
        $alert = OnCallAlert::query()->where('dedup_key', $dedup)->whereIn('state', [OnCallAlert::OPEN, OnCallAlert::ACKED, OnCallAlert::ESCALATED])->orderByDesc('id')->first();

        return $alert !== null ? $this->resolve($alert, $by) : null;
    }

    /**
     * The minute pass (rule `oncall.escalate`): every unacknowledged alert past its deadline is paged again with a higher
     * severity, up to `max_escalations`; after the last one it stays hot in the console.
     *
     * @return array{escalated:int, exhausted:int}
     */
    public function escalateDue(): array
    {
        $stats = ['escalated' => 0, 'exhausted' => 0];
        if (! $this->ledger->enabled(self::RULE)) {
            return $stats;
        }
        $max = max(0, (int) config('onhost.oncall.max_escalations', 2));
        $due = OnCallAlert::query()->whereIn('state', OnCallAlert::ACTIVE)->whereNotNull('escalate_after')->where('escalate_after', '<=', now())->orderBy('escalate_after')->limit(100)->get();
        foreach ($due as $alert) {
            $n = $alert->escalations + 1;
            $last = $n >= $max;
            $alert->forceFill(['state' => OnCallAlert::ESCALATED, 'escalations' => $n, 'escalated_at' => now(), 'escalate_after' => $last ? null : now()->addMinutes($this->escalateAfterMinutes())])->save();
            $this->page($alert, $n);
            $this->outbox->publish(GenericEvent::of('oncall.alert.escalated', 'oncall_alert', $alert->id, self::present($alert) + ['final' => $last]));
            $stats[$last ? 'exhausted' : 'escalated']++;
        }

        return $stats;
    }

    /** A test page from the console: opens and pages a synthetic alert that the operator resolves by hand. */
    public function test(CommandContext $context): OnCallAlert
    {
        $alert = $this->open('oncall.test', 'user', (string) ($context->actorId ?? 'staff'), 'Testovací stránka on-call', 'Ověření pageru z konzole ONhost; vyřešte ji ručně.', '/sprava#/incidents', 'info');
        $this->audit->record($context, 'oncall.test', 'succeeded', ['alert' => $alert->id, 'provider' => $alert->provider, 'paged' => $alert->provider_ref !== null], 'oncall_alert', $alert->id);

        return $alert;
    }

    /**
     * The pager tells us somebody acknowledged or resolved on its side (audit §5q-1). PagerDuty webhooks v3 carry
     * `X-PagerDuty-Signature: v1=<hmac>` over the raw body, Opsgenie and the generic relay send the inbound secret in
     * `X-ONhost-Oncall-Token`; a wrong or missing signature is a 401 without any hint which.
     *
     * @return array{handled:bool, alert:?string, action:?string}
     */
    public function inbound(string $provider, Request $request): array
    {
        $secret = (string) config('onhost.oncall.inbound_secret', '');
        $body = (string) $request->getContent();
        $valid = false;
        if ($secret !== '') {
            if ($provider === 'pagerduty') {
                foreach (explode(',', (string) $request->header('X-PagerDuty-Signature', '')) as $part) {
                    $part = trim($part);
                    if (str_starts_with($part, 'v1=') && hash_equals(hash_hmac('sha256', $body, $secret), substr($part, 3))) {
                        $valid = true;
                    }
                }
            } else {
                $valid = hash_equals($secret, (string) $request->header('X-ONhost-Oncall-Token', ''));
            }
        }
        if (! $valid) {
            throw new DomainError('oncall_inbound_unauthorized', 'The pager call-back is not signed for this platform.', 401);
        }
        $payload = (array) json_decode($body, true);
        [$action, $dedup, $by] = match ($provider) {
            'pagerduty' => [match ((string) data_get($payload, 'event.event_type', '')) {
                'incident.acknowledged' => 'ack', 'incident.resolved' => 'resolve', default => null,
            }, (string) data_get($payload, 'event.data.incident_key', ''), 'provider:pagerduty:'.(string) data_get($payload, 'event.agent.summary', data_get($payload, 'event.agent.id', ''))],
            'opsgenie' => [match (strtolower((string) ($payload['action'] ?? ''))) {
                'acknowledge' => 'ack', 'close' => 'resolve', default => null,
            }, (string) data_get($payload, 'alert.alias', ''), 'provider:opsgenie:'.(string) data_get($payload, 'alert.username', '')],
            default => [match (strtolower((string) ($payload['action'] ?? ''))) {
                'acknowledge', 'ack' => 'ack', 'resolve', 'close' => 'resolve', default => null,
            }, (string) ($payload['dedup_key'] ?? ''), 'provider:'.$provider.':'.(string) ($payload['by'] ?? '')],
        };
        if ($action === null || $dedup === '') {
            return ['handled' => false, 'alert' => null, 'action' => null];
        }
        $alert = OnCallAlert::query()->where('dedup_key', $dedup)->whereIn('state', [OnCallAlert::OPEN, OnCallAlert::ACKED, OnCallAlert::ESCALATED])->orderByDesc('id')->first();
        if ($alert === null) {
            return ['handled' => false, 'alert' => null, 'action' => $action];
        }
        $alert = $action === 'ack' ? $this->acknowledge($alert, $by) : $this->resolve($alert, $by);

        return ['handled' => true, 'alert' => $alert->id, 'action' => $action];
    }

    /**
     * Alertmanager's webhook (infra/monitoring/alertmanager.yml → `POST /v1/webhooks/alertmanager`, bearer =
     * `ONHOST_ONCALL_INBOUND_SECRET`): every firing Prometheus rule opens one on-call alert (dedup = the rule and its
     * labels), a resolved one closes it — so paging, escalation and the rota work the same for platform metrics as for
     * the platform's own events.
     *
     * @return array{opened:int, resolved:int, ignored:int}
     */
    public function fromAlertmanager(Request $request): array
    {
        $secret = (string) config('onhost.oncall.inbound_secret', '');
        if ($secret === '' || ! hash_equals($secret, (string) $request->bearerToken())) {
            throw new DomainError('oncall_inbound_unauthorized', 'The alert call-back is not signed for this platform.', 401);
        }
        $stats = ['opened' => 0, 'resolved' => 0, 'ignored' => 0];
        foreach (array_slice((array) $request->input('alerts', []), 0, 200) as $alert) {
            $labels = (array) ($alert['labels'] ?? []);
            $name = (string) ($labels['alertname'] ?? '');
            if ($name === '') {
                $stats['ignored']++;

                continue;
            }
            $subject = collect($labels)->except(['alertname', 'severity', 'job', 'instance', 'prometheus'])->map(fn ($v, $k) => "{$k}={$v}")->sort()->implode(',') ?: 'platform';
            if (($alert['status'] ?? 'firing') === 'resolved') {
                $this->resolveByDedup(self::dedupKey('prometheus.'.$name, 'metric', $subject), 'alertmanager') !== null ? $stats['resolved']++ : $stats['ignored']++;

                continue;
            }
            $annotations = (array) ($alert['annotations'] ?? []);
            $severity = match ((string) ($labels['severity'] ?? '')) {
                'page' => 'hot', 'ticket' => 'warn', default => 'info'
            };
            $this->open('prometheus.'.$name, 'metric', $subject, mb_substr((string) ($annotations['summary'] ?? $name), 0, 250), mb_substr(trim(((string) ($annotations['description'] ?? '')).' '.$subject.(isset($annotations['runbook']) ? ' · '.$annotations['runbook'] : '')), 0, 1000), '/sprava#/incidents', $severity);
            $stats['opened']++;
        }

        return $stats;
    }

    /** The pager behind the configuration, built once per process; null without one (console-only). */
    public function provider(): ?OnCallProvider
    {
        if ($this->providerResolved) {
            return $this->provider;
        }
        $this->providerResolved = true;
        $driver = (string) config('onhost.oncall.provider', '');
        if ($driver === '') {
            return null;
        }
        try {
            $credentials = $this->secrets->read(SecretRef::parse((string) config('onhost.oncall.secret_ref', 'env://ONHOST_ONCALL')));
        } catch (Throwable) {
            return null;
        }
        $this->provider = match ($driver) {
            'pagerduty' => ! empty($credentials['routing_key']) ? new PagerDutyOnCallProvider($this->http, (string) $credentials['routing_key'], (string) config('app.name', 'onhost')) : null,
            'opsgenie' => ! empty($credentials['api_key']) ? new OpsgenieOnCallProvider($this->http, (string) $credentials['api_key'], (string) ($credentials['base_url'] ?? 'https://api.opsgenie.com')) : null,
            'webhook' => ! empty($credentials['url']) ? new WebhookOnCallProvider($this->http, (string) $credentials['url'], (string) ($credentials['secret'] ?? '')) : null,
            default => null,
        };

        return $this->provider;
    }

    /** @return array{provider:?string, configured:bool, events:list<string>, escalate_after_minutes:int, max_escalations:int, active:int} */
    public function status(): array
    {
        return [
            'provider' => (string) config('onhost.oncall.provider', '') ?: null, 'configured' => $this->provider() !== null, 'events' => array_keys((array) config('onhost.oncall.events', [])),
            'escalate_after_minutes' => $this->escalateAfterMinutes(), 'max_escalations' => (int) config('onhost.oncall.max_escalations', 2), 'active' => OnCallAlert::query()->whereIn('state', OnCallAlert::ACTIVE)->count(),
            'on_call' => app(OnCallRota::class)->assignee(), 'next' => ($next = app(OnCallRota::class)->next()) !== null ? OnCallRota::present($next) : null, // §5r-1
        ];
    }

    /** @return array<string,mixed> */
    public static function present(OnCallAlert $a): array
    {
        return [
            'id' => $a->id, 'event' => $a->event, 'severity' => $a->severity, 'state' => $a->state, 'title' => $a->title, 'body' => $a->body, 'surface' => $a->surface, 'provider' => $a->provider, 'paged' => $a->provider_ref !== null,
            'escalations' => (int) $a->escalations, 'escalate_after' => $a->escalate_after?->toIso8601String(), 'escalated_at' => $a->escalated_at?->toIso8601String(),
            'acked_by' => $a->acked_by, 'acked_at' => $a->acked_at?->toIso8601String(), 'resolved_by' => $a->resolved_by, 'resolved_at' => $a->resolved_at?->toIso8601String(),
            'trace_url' => Tracer::urlFor(data_get($a->meta, 'correlation_id')), 'repeats' => (int) data_get($a->meta, 'repeats', 0), 'aggregate' => data_get($a->meta, 'aggregate'), 'assignee' => data_get($a->meta, 'assignee'), 'at' => $a->created_at?->toIso8601String(),
        ];
    }

    public static function dedupKey(string $event, string $aggregateType, string $aggregateId): string
    {
        return mb_substr("{$event}:{$aggregateType}:{$aggregateId}", 0, 190);
    }

    private function page(OnCallAlert $alert, int $escalation): void
    {
        $provider = $this->provider();
        if ($provider === null) {
            return;
        }
        $ref = $provider->trigger(['dedup_key' => $alert->dedup_key, 'title' => $alert->title, 'body' => $alert->body, 'severity' => $alert->severity, 'event' => $alert->event, 'surface' => $alert->surface, 'escalation' => $escalation, 'assignee' => data_get($alert->meta, 'assignee.name')]);
        $alert->forceFill(['provider' => $provider->name(), 'provider_ref' => $ref, 'meta' => array_merge((array) $alert->meta, ['pages' => (int) data_get($alert->meta, 'pages', 0) + ($ref !== null ? 1 : 0), 'page_failed_at' => $ref === null ? now()->toIso8601String() : null])])->save();
    }

    private function escalateAfterMinutes(): int
    {
        return max(1, (int) config('onhost.oncall.escalate_after_minutes', 15));
    }
}
