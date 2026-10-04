<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Presenters\Presenters;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Onhost\Domain\Domains\Commands\DomainCommand;
use Onhost\Domain\Domains\Models\Domain;
use Onhost\Domain\Domains\Models\RegistrarContact;
use Onhost\Domain\Domains\Models\RegistrarOperation;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Commands\CommandScope;
use Onhost\Platform\Commands\IdempotencyStore;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Redaction\Redactor;
use Onhost\Platform\Support\Hostname;

/** Domain platform endpoints (blueprint §46): every mutating call is a DomainCommand through the bus. */
final class DomainController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $organization = $this->api->organization($request);
        $this->api->authorize($request, 'domain.read', CommandScope::organization($organization->id));
        $query = Domain::query()->where('organization_id', $organization->id);
        if ($request->filled('state')) {
            $query->where('state', strtoupper((string) $request->query('state')));
        }
        if ($request->filled('q')) {
            $query->where('fqdn_ascii', 'like', '%'.strtolower((string) $request->query('q')).'%');
        }

        return $this->api->paginate($request, $query, fn (Domain $d) => Presenters::domain($d), 'expires_at');
    }

    public function show(Request $request, string $domain): JsonResponse
    {
        $model = $this->resolve($request, $domain);
        $operations = RegistrarOperation::query()->where('domain_id', $model->id)->orderByDesc('sent_at')->limit(20)->get()->map(fn (RegistrarOperation $o) => ['id' => $o->id, 'command' => $o->command, 'state' => $o->state, 'normalized_error' => $o->normalized_error, 'sent_at' => $o->sent_at?->toIso8601String(), 'completed_at' => $o->completed_at?->toIso8601String(), 'test_mode' => (bool) $o->test_mode])->all();
        $registrant = $model->registrant_contact_id ? RegistrarContact::query()->find($model->registrant_contact_id) : null;

        return response()->json(['data' => Presenters::domain($model) + ['registrant' => $registrant ? $this->contact($registrant) : null, 'registrar_operations' => $operations, 'registry_status' => is_array($model->registry_status) ? (new Redactor)->redact($model->registry_status) : null]]);
    }

    public function renew(Request $request, string $domain): JsonResponse
    {
        $model = $this->resolve($request, $domain);
        $data = $request->validate(['years' => ['nullable', 'integer', 'min:1', 'max:10']]);

        return $this->command($request, $model, 'renew', ['years' => (int) ($data['years'] ?? $model->renewal_period ?: 1)], 202);
    }

    public function nameservers(Request $request, string $domain): JsonResponse
    {
        $model = $this->resolve($request, $domain);
        $data = $request->validate(['nameservers' => ['required', 'array', 'min:2', 'max:8'], 'nameservers.*' => ['string', 'max:253'], 'dns_provider' => ['nullable', 'in:external,powerdns']]);

        return $this->command($request, $model, 'nameservers', $data, 202);
    }

    public function useOnhostDns(Request $request, string $domain): JsonResponse
    {
        $model = $this->resolve($request, $domain);
        $data = $request->validate(['template' => ['nullable', 'string', 'max:60'], 'vars' => ['nullable', 'array']]);

        return $this->command($request, $model, 'use_onhost_dns', $data, 202);
    }

    public function autoRenew(Request $request, string $domain): JsonResponse
    {
        $model = $this->resolve($request, $domain);
        $data = $request->validate(['enabled' => ['required', 'boolean']]);

        return $this->command($request, $model, 'auto_renew', $data);
    }

    public function transferLock(Request $request, string $domain): JsonResponse
    {
        $model = $this->resolve($request, $domain);
        $data = $request->validate(['locked' => ['required', 'boolean']]);

        return $this->command($request, $model, 'transfer_lock', $data);
    }

    public function authInfo(Request $request, string $domain): JsonResponse
    {
        $model = $this->resolve($request, $domain);

        return $this->command($request, $model, 'auth_info', []);
    }

    public function publishDs(Request $request, string $domain): JsonResponse
    {
        $model = $this->resolve($request, $domain);

        return $this->command($request, $model, 'publish_ds', []);
    }

    /**
     * The holder's contact details (e-mail, phone, address) at the registrar. HIGH (step-up) under `domain.registrant.change`;
     * the holder himself (name, company, IČO) cannot be changed here, only his way of being reached (DomainService::updateHolderContact).
     * Route: POST /v1/domains/{domain}/holder — registered in routes/api.php beside the other domain writes.
     */
    public function holder(Request $request, string $domain): JsonResponse
    {
        $model = $this->resolve($request, $domain);
        $data = $request->validate(['email' => ['nullable', 'email', 'max:190'], 'phone' => ['nullable', 'string', 'max:40'], 'street' => ['nullable', 'string', 'max:190'], 'city' => ['nullable', 'string', 'max:120'], 'postal_code' => ['nullable', 'string', 'max:20'], 'country' => ['nullable', 'string', 'size:2'], 'name' => ['nullable', 'string', 'max:190'], 'organization_name' => ['nullable', 'string', 'max:190'], 'ico' => ['nullable', 'string', 'max:20'], 'dic' => ['nullable', 'string', 'max:20']]);

        return $this->command($request, $model, 'holder', ['holder' => array_filter($data, fn ($v) => $v !== null)]);
    }

    /**
     * A customer hands over the transfer code of a PAID transfer line (`order_item_id`, TASK-0058); without one the transfer is
     * refused with 422 `transfer_needs_order` (DomainService::transferIn) — a transfer renews the name and is bought in the cart.
     */
    public function transferIn(Request $request): JsonResponse
    {
        $organization = $this->api->organization($request);
        $data = $request->validate(['fqdn' => ['required', 'string', 'max:253'], 'order_item_id' => ['nullable', 'string', 'max:40'], 'auth_info' => ['required', 'string', 'max:64'], 'registrant_contact_id' => ['nullable', 'string'], 'registrant' => ['nullable', 'array'], 'nameservers' => ['nullable', 'array'], 'period' => ['nullable', 'integer', 'min:1', 'max:10'], 'consent' => ['required', 'array'], 'consent.person' => ['required', 'string', 'max:190']]);

        return $this->send($request, $organization, 'transfer_in', null, $data, 202);
    }

    public function contacts(Request $request): JsonResponse
    {
        $organization = $this->api->organization($request);
        $this->api->authorize($request, 'domain.read', CommandScope::organization($organization->id));

        return response()->json(['data' => RegistrarContact::query()->where('organization_id', $organization->id)->orderByDesc('created_at')->get()->map(fn ($c) => $this->contact($c))->all()]);
    }

    public function createContact(Request $request): JsonResponse
    {
        $organization = $this->api->organization($request);
        $data = $request->validate(['name' => ['required', 'string', 'max:190'], 'organization_name' => ['nullable', 'string', 'max:190'], 'email' => ['required', 'email'], 'phone' => ['nullable', 'string', 'max:40'], 'street' => ['required', 'string', 'max:190'], 'city' => ['required', 'string', 'max:120'], 'postal_code' => ['required', 'string', 'max:20'], 'country' => ['required', 'string', 'size:2'], 'ico' => ['nullable', 'string', 'max:20'], 'dic' => ['nullable', 'string', 'max:20'], 'privacy' => ['nullable', 'in:hidden,public'], 'kind' => ['nullable', 'in:registrant,admin,tech']]);

        return $this->send($request, $organization, 'contact', null, ['contact' => $data], 201);
    }

    private function command(Request $request, Domain $model, string $op, array $payload, int $status = 200): JsonResponse
    {
        $organization = Organization::query()->find($model->organization_id) ?? throw DomainError::notFound('organization');

        return $this->send($request, $organization, $op, $model, ['fqdn' => $model->fqdn_ascii] + $payload, $status);
    }

    /**
     * One door for every domain write (TASK-0041, permission program IF-12 / P0-10 follow-up, audit SE-5 / G12). The bus keeps
     * its answers per organization, and the key used to be `domain.<op>:<header>` — nothing about the domain or the person.
     * Another member sending the same header (a shared script, a copied request) for another domain of the organization got
     * the first member's answer back from the bus before anything ran: nothing happened on their domain, and they were told
     * it had. The HTTP layer's replay is per person and never saw it. The key now names the target and the actor (the person
     * a staff member acts for first, as OperationKey does), and DomainCommand adds the request's fingerprint to the bus key.
     *
     * @param  array<string, mixed>  $payload
     */
    private function send(Request $request, Organization $organization, string $op, ?Domain $target, array $payload, int $status): JsonResponse
    {
        $context = $this->api->context($request, $organization);
        $actor = substr(hash('sha256', $context->actorType.':'.($context->onBehalfOfUserId ?? $context->actorId ?? '')), 0, 16);
        $command = new DomainCommand($organization->id, $this->idempotencyKey($request, "domain.{$op}:".($target === null ? 'org' : $target->id).":{$actor}"), ['op' => $op] + $payload);
        $this->assertKeyUnused($command, $context);

        return $this->dispatch($command, $context, $status);
    }

    /**
     * The same key with another request is refused (409), as for service actions: the bus keeps `<key>#<fingerprint>`, so a
     * changed body no longer gets the first run's answer — but for the instant ops (auto-renew, transfer lock, auth-info,
     * DS, contact) there is no operation whose own 409 would stop it, and it would simply run as a new request under a key
     * the caller already spent. Only answers still kept by the bus count (24 h, IdempotencyStore). A true retry (same
     * fingerprint) passes and is replayed by the bus.
     */
    private function assertKeyUnused(DomainCommand $command, CommandContext $context): void
    {
        $prefix = $command->idempotencyKey.'#';
        // the bus's own scope (organization and person since the P0-16 red team round, IdempotencyStore)
        $used = DB::table('idempotency_keys')->where('scope', IdempotencyStore::scopeOf($context))->whereRaw('substr("key", 1, ?) = ?', [mb_strlen($prefix), $prefix])
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))->pluck('key');
        if ($used->contains(fn ($key) => (string) $key !== $command->idempotencyKey())) {
            throw DomainError::conflict('idempotency_key_reused', 'This idempotency key was already used for another domain request.', [
                'hint' => 'Use a new key for a different request.',
            ]);
        }
    }

    private function resolve(Request $request, string $idOrName): Domain
    {
        $domain = Domain::query()->find($idOrName);
        if ($domain === null) {
            try {
                $domain = Domain::query()->where('fqdn_ascii', Hostname::canonical($idOrName))->first();
            } catch (\InvalidArgumentException) {
                $domain = null; // neither an id nor a valid host name
            }
        }
        if ($domain === null) {
            throw DomainError::notFound('domain');
        }
        $this->api->authorize($request, 'domain.read', CommandScope::organization($domain->organization_id));

        return $domain;
    }

    private function contact(RegistrarContact $c): array
    {
        return ['id' => $c->id, 'kind' => $c->kind, 'name' => $c->name, 'organization_name' => $c->organization_name, 'email' => $c->email, 'phone' => $c->phone, 'street' => $c->street, 'city' => $c->city, 'postal_code' => $c->postal_code, 'country' => $c->country, 'ico' => $c->ico, 'dic' => $c->dic, 'privacy' => $c->privacy, 'state' => $c->state, 'remote_id' => $c->remote_id];
    }
}
