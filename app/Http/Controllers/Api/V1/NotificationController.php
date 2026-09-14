<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Onhost\Domain\Notifications\Models\MailOutbox;
use Onhost\Domain\Notifications\Models\Notification;
use Onhost\Domain\Notifications\Models\NotificationPreference;
use Onhost\Domain\Notifications\Models\NotificationTemplate;
use Onhost\Domain\Notifications\Models\WebhookDelivery;
use Onhost\Domain\Notifications\Models\WebhookEndpoint;
use Onhost\Domain\Notifications\NotificationService;
use Onhost\Domain\Notifications\WebhookDispatcher;
use Onhost\Platform\Audit\AuditRecorder;
use Onhost\Platform\Commands\CommandScope;
use Onhost\Platform\Errors\DomainError;

/** In-app feed, preferences, customer webhooks; staff mail outbox and template test render. */
final class NotificationController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $user = $this->api->user($request);
        // staff without an organization context read the internal inbox; customers (and staff impersonating) the customer feed
        $audience = (string) $request->query('audience', $user->is_staff && ! $request->hasHeader('X-Organization') && ! $request->filled('organization') ? 'internal' : 'customer');
        if ($audience === 'internal') {
            $this->api->authorize($request, 'staff.customer.read', CommandScope::global());
            $query = Notification::query()->where('audience', 'internal');
        } else {
            $organization = $this->api->organization($request);
            $query = Notification::query()->where('audience', 'customer')->where(fn ($q) => $q->where('organization_id', $organization->id)->orWhere('user_id', $user->id));
        }
        if ($request->boolean('unread')) {
            $query->whereNull('read_at');
        }

        return $this->api->paginate($request, $query, fn (Notification $n) => ['id' => $n->id, 'aud' => $n->audience, 'kind' => $n->kind, 'event' => $n->event, 'ref' => $n->ref_id, 'ref_type' => $n->ref_type, 'title' => $n->title, 'body' => $n->body, 'surface' => $n->surface, 'severity' => $n->severity, 'read' => $n->read_at !== null, 'at' => $n->created_at?->toIso8601String()]);
    }

    public function read(Request $request, NotificationService $notifications): JsonResponse
    {
        $data = $request->validate(['audience' => ['nullable', 'in:customer,internal'], 'ids' => ['required', 'array', 'min:1', 'max:200'], 'ids.*' => ['string']]);
        $audience = $data['audience'] ?? 'customer';
        $user = $this->api->user($request);
        $organization = $audience === 'customer' ? $this->api->organization($request) : null;
        if ($audience === 'internal') {
            $this->api->authorize($request, 'staff.customer.read', CommandScope::global());
        }

        return response()->json(['data' => ['read' => $notifications->markRead($audience, $data['ids'], $organization?->id, $user->id)]]);
    }

    public function preferences(Request $request): JsonResponse
    {
        $user = $this->api->user($request);

        return response()->json(['data' => NotificationPreference::query()->where('user_id', $user->id)->get()->map(fn ($p) => ['kind' => $p->kind, 'channel' => $p->channel, 'enabled' => (bool) $p->enabled])->all(), 'mandatory' => config('onhost.notifications.mandatory_kinds')]);
    }

    public function updatePreference(Request $request, NotificationService $notifications): JsonResponse
    {
        $data = $request->validate(['kind' => ['required', 'string', 'max:40'], 'channel' => ['required', 'in:mail,inapp'], 'enabled' => ['required', 'boolean']]);
        $pref = $notifications->setPreference($this->api->user($request)->id, $data['kind'], $data['channel'], (bool) $data['enabled']);

        return response()->json(['data' => ['kind' => $pref->kind, 'channel' => $pref->channel, 'enabled' => (bool) $pref->enabled]]);
    }

    // ── customer webhooks ────────────────────────────────────────────────────

    public function webhooks(Request $request): JsonResponse
    {
        $organization = $this->api->organization($request);
        $this->api->authorize($request, 'organization.manage', CommandScope::organization($organization->id));

        return response()->json(['data' => WebhookEndpoint::query()->where('organization_id', $organization->id)->get()->map(fn (WebhookEndpoint $e) => $this->endpoint($e))->all(), 'events' => WebhookDispatcher::CUSTOMER_EVENTS]);
    }

    public function createWebhook(Request $request, WebhookDispatcher $dispatcher, AuditRecorder $audit): JsonResponse
    {
        $organization = $this->api->organization($request);
        $this->api->authorize($request, 'organization.manage', CommandScope::organization($organization->id));
        $data = $request->validate(['url' => ['required', 'url', 'starts_with:https://', 'max:500'], 'events' => ['nullable', 'array', 'max:50'], 'events.*' => ['string', 'max:80']]);
        $created = $dispatcher->createEndpoint($organization->id, $data['url'], (array) ($data['events'] ?? ['*']), $this->api->user($request)->id);
        $audit->record($this->api->context($request, $organization), 'webhook.create', 'succeeded', ['url' => $data['url'], 'events' => $data['events'] ?? ['*']], 'webhook_endpoint', $created['endpoint']->id);

        return response()->json(['data' => $this->endpoint($created['endpoint']) + ['secret' => $created['secret']]], 201);
    }

    public function deleteWebhook(Request $request, AuditRecorder $audit, string $endpoint): JsonResponse
    {
        $organization = $this->api->organization($request);
        $this->api->authorize($request, 'organization.manage', CommandScope::organization($organization->id));
        $model = WebhookEndpoint::query()->where('organization_id', $organization->id)->find($endpoint);
        if ($model === null) {
            throw DomainError::notFound('webhook');
        }
        $model->forceFill(['state' => 'disabled'])->save();
        $audit->record($this->api->context($request, $organization), 'webhook.disable', 'succeeded', ['url' => $model->url], 'webhook_endpoint', $model->id);

        return response()->json(['data' => $this->endpoint($model)]);
    }

    public function webhookDeliveries(Request $request, string $endpoint): JsonResponse
    {
        $organization = $this->api->organization($request);
        $this->api->authorize($request, 'organization.manage', CommandScope::organization($organization->id));
        $model = WebhookEndpoint::query()->where('organization_id', $organization->id)->find($endpoint);
        if ($model === null) {
            throw DomainError::notFound('webhook');
        }

        return $this->api->paginate($request, WebhookDelivery::query()->where('endpoint_id', $model->id), fn (WebhookDelivery $d) => ['id' => $d->id, 'event' => $d->event, 'state' => $d->state, 'attempts' => $d->attempts, 'response_status' => $d->response_status, 'last_error' => $d->last_error, 'next_attempt_at' => $d->next_attempt_at?->toIso8601String(), 'delivered_at' => $d->delivered_at?->toIso8601String(), 'at' => $d->created_at?->toIso8601String()]);
    }

    // ── staff: mail outbox & templates ───────────────────────────────────────

    public function outbox(Request $request): JsonResponse
    {
        $this->api->authorize($request, 'notification.template.manage', CommandScope::global());
        $query = MailOutbox::query();
        if ($request->filled('state')) {
            $query->where('state', (string) $request->query('state'));
        }

        return $this->api->paginate($request, $query, fn (MailOutbox $m) => ['id' => $m->id, 'tpl' => $m->template_key, 'to' => $m->to, 'subject' => $m->subject, 'state' => $m->state, 'at' => $m->created_at?->toIso8601String(), 'sent_at' => $m->sent_at?->toIso8601String(), 'attempts' => $m->attempts, 'last_error' => $m->last_error, 'ref' => $m->ref_id, 'vars' => $m->vars, 'organization_id' => $m->organization_id]);
    }

    public function sendMail(Request $request, NotificationService $notifications, string $mail): JsonResponse
    {
        $this->api->authorize($request, 'notification.template.manage', CommandScope::global());
        $model = MailOutbox::query()->find($mail);
        if ($model === null) {
            throw DomainError::notFound('mail');
        }
        $sent = $notifications->sendNow($model);

        return response()->json(['data' => ['id' => $sent->id, 'state' => $sent->state, 'sent_at' => $sent->sent_at?->toIso8601String()]]);
    }

    public function templates(Request $request): JsonResponse
    {
        $this->api->authorize($request, 'notification.template.manage', CommandScope::global());

        return response()->json(['data' => NotificationTemplate::query()->where('state', 'active')->orderBy('key')->orderBy('locale')->get()->map(fn (NotificationTemplate $t) => ['id' => $t->id, 'key' => $t->key, 'channel' => $t->channel, 'locale' => $t->locale, 'version' => $t->version, 'subject' => $t->subject, 'body' => $t->body, 'variables' => $t->variables, 'mandatory' => (bool) $t->mandatory])->all()]);
    }

    public function testRender(Request $request, NotificationService $notifications): JsonResponse
    {
        $this->api->authorize($request, 'notification.template.manage', CommandScope::global());
        $data = $request->validate(['key' => ['required', 'string'], 'channel' => ['nullable', 'in:mail,inapp'], 'locale' => ['nullable', 'in:cs,en'], 'vars' => ['nullable', 'array']]);

        return response()->json(['data' => $notifications->testRender($data['key'], $data['channel'] ?? 'mail', $data['locale'] ?? 'cs', (array) ($data['vars'] ?? []))]);
    }

    private function endpoint(WebhookEndpoint $e): array
    {
        return ['id' => $e->id, 'url' => $e->url, 'events' => $e->events, 'state' => $e->state, 'failures' => $e->failures, 'last_delivered_at' => $e->last_delivered_at?->toIso8601String(), 'created_at' => $e->created_at?->toIso8601String()];
    }
}
