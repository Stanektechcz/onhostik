<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\StaffReadAudit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Onhost\Domain\Notifications\Models\MailOutbox;
use Onhost\Domain\Notifications\Models\Notification;
use Onhost\Domain\Notifications\Models\NotificationPreference;
use Onhost\Domain\Notifications\Models\NotificationTemplate;
use Onhost\Domain\Notifications\NotificationService;
use Onhost\Platform\Commands\CommandScope;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Redaction\Redactor;

/** In-app feed and preferences; staff mail outbox and template test render. Customer webhooks: WebhookController. */
final class NotificationController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $user = $this->api->user($request);
        // staff without an organization context read the internal inbox; customers (and staff impersonating) the customer feed
        $audience = (string) $request->query('audience', $user->is_staff && ! $request->hasHeader('X-Organization') && ! $request->filled('organization') ? 'internal' : 'customer');
        if ($audience === 'internal') {
            $query = Notification::query()->where('audience', 'internal');
            if (! $this->wholeInternalInbox($request)) {
                $query->where('user_id', $user->id); // staff.inbox.read: only what is addressed to the reader
            }
        } else {
            $organization = $this->api->organization($request);
            $query = Notification::query()->where('audience', 'customer')->where(fn ($q) => $q->where('organization_id', $organization->id)->orWhere('user_id', $user->id));
        }
        if ($request->boolean('unread')) {
            $query->whereNull('read_at');
        }

        return $this->api->paginate($request, $query, fn (Notification $n) => ['id' => $n->id, 'aud' => $n->audience, 'kind' => $n->kind, 'event' => $n->event, 'ref' => $n->ref_id, 'ref_type' => $n->ref_type, 'title' => $n->title, 'body' => $n->body, 'surface' => $n->surface, 'severity' => $n->severity, 'locale' => $n->locale, 'read' => $n->read_at !== null, 'at' => $n->created_at?->toIso8601String()]);
    }

    public function read(Request $request, NotificationService $notifications): JsonResponse
    {
        $data = $request->validate(['audience' => ['nullable', 'in:customer,internal'], 'ids' => ['required', 'array', 'min:1', 'max:200'], 'ids.*' => ['string']]);
        $audience = $data['audience'] ?? 'customer';
        $user = $this->api->user($request);
        $organization = $audience === 'customer' ? $this->api->organization($request) : null;
        $ids = $data['ids'];
        if ($audience === 'internal' && ! $this->wholeInternalInbox($request)) {
            // staff.inbox.read marks only the reader's own rows, never the shared internal inbox of everybody else
            $ids = Notification::query()->where('audience', 'internal')->where('user_id', $user->id)->whereIn('id', $ids)->pluck('id')->map(fn ($id) => (string) $id)->all();
        }

        return response()->json(['data' => ['read' => $ids === [] ? 0 : $notifications->markRead($audience, $ids, $organization?->id, $user->id)]]);
    }

    /**
     * Who reads the internal (staff) inbox, and how much of it. `staff.customer.read` reads all of it — it names customers and
     * their organizations. TASK-0067: `staff.inbox.read` (the content team, who hold no customer view) reads only the
     * notifications addressed to them. Neither: 403, asking for the customer view as before.
     */
    private function wholeInternalInbox(Request $request): bool
    {
        if ($this->api->can($request, 'staff.customer.read', CommandScope::global())) {
            return true;
        }
        if ($this->api->can($request, 'staff.inbox.read', CommandScope::global())) {
            return false;
        }
        $this->api->authorize($request, 'staff.customer.read', CommandScope::global()); // throws: the answer names the key it always named

        return true;
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

    // ── staff: mail outbox & templates ───────────────────────────────────────

    public function outbox(Request $request): JsonResponse
    {
        $this->api->authorize($request, 'notification.template.manage', CommandScope::global());
        app(StaffReadAudit::class)->record($request, $this->api->context($request), 'mail_outbox', null, 'mail_outbox', null, ['state' => $request->query('state')]);
        $query = MailOutbox::query();
        if ($request->filled('state')) {
            $query->where('state', (string) $request->query('state'));
        }

        return $this->api->paginate($request, $query, fn (MailOutbox $m) => ['id' => $m->id, 'tpl' => $m->template_key, 'to' => $m->to, 'subject' => $m->subject, 'state' => $m->state, 'at' => $m->created_at?->toIso8601String(), 'sent_at' => $m->sent_at?->toIso8601String(), 'attempts' => $m->attempts, 'last_error' => $m->last_error, 'ref' => $m->ref_id, 'vars' => (new Redactor)->redact((array) $m->vars), 'organization_id' => $m->organization_id]); // an invitation link is a way into a customer's organization
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
}
