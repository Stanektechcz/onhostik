<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Presenters\Presenters;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Onhost\Domain\Domains\Commands\DomainCommand;
use Onhost\Domain\Domains\Models\Domain;
use Onhost\Domain\Domains\Models\RegistrarContact;
use Onhost\Domain\Domains\Models\RegistrarOperation;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Platform\Commands\CommandScope;
use Onhost\Platform\Errors\DomainError;
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

        return response()->json(['data' => Presenters::domain($model) + ['registrant' => $registrant ? $this->contact($registrant) : null, 'registrar_operations' => $operations, 'registry_status' => $model->registry_status]]);
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

    public function transferIn(Request $request): JsonResponse
    {
        $organization = $this->api->organization($request);
        $data = $request->validate(['fqdn' => ['required', 'string', 'max:253'], 'auth_info' => ['required', 'string', 'max:64'], 'registrant_contact_id' => ['nullable', 'string'], 'registrant' => ['nullable', 'array'], 'nameservers' => ['nullable', 'array'], 'period' => ['nullable', 'integer', 'min:1', 'max:10'], 'consent' => ['required', 'array'], 'consent.person' => ['required', 'string', 'max:190']]);

        return $this->dispatch(new DomainCommand($organization->id, $this->idempotencyKey($request, 'domain.transfer_in'), ['op' => 'transfer_in'] + $data), $this->api->context($request, $organization), 202);
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

        return $this->dispatch(new DomainCommand($organization->id, $this->idempotencyKey($request, 'domain.contact'), ['op' => 'contact', 'contact' => $data]), $this->api->context($request, $organization), 201);
    }

    private function command(Request $request, Domain $model, string $op, array $payload, int $status = 200): JsonResponse
    {
        $organization = Organization::query()->find($model->organization_id);

        return $this->dispatch(new DomainCommand($model->organization_id, $this->idempotencyKey($request, "domain.{$op}"), ['op' => $op, 'fqdn' => $model->fqdn_ascii] + $payload), $this->api->context($request, $organization), $status);
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
