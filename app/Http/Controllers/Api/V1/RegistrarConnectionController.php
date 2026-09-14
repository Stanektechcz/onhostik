<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Presenters\Presenters;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Onhost\Domain\Domains\Commands\RegistrarConnectionCommand;
use Onhost\Domain\Domains\Models\Domain;
use Onhost\Domain\Domains\Models\RegistrarConnection;
use Onhost\Domain\Domains\RegistrarConnectionService;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Platform\Audit\AuditEvent;
use Onhost\Platform\Commands\CommandScope;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Support\Hostname;

/** Connected registrar accounts (bring your own WEDOS API), their mirrored domains, sync history and hosting pairing. */
final class RegistrarConnectionController extends ApiController
{
    public function index(Request $request, RegistrarConnectionService $connections): JsonResponse
    {
        $organization = $this->api->organization($request);
        $this->api->authorize($request, 'domain.read', CommandScope::organization($organization->id));
        $rows = RegistrarConnection::query()->where('organization_id', $organization->id)->orderByDesc('created_at')->get()
            ->map(fn (RegistrarConnection $c) => Presenters::registrarConnection($c, $c->state === 'disabled' ? 0 : $connections->mirrored($c)->count()))->values()->all();

        return response()->json(['data' => $rows, 'providers' => [['key' => 'wedos', 'label' => 'WEDOS', 'fields' => ['login', 'password'], 'help' => 'WAPI login is the account e-mail; the API password is set in the WEDOS administration (Zákazník → WAPI) where the platform address must be allowed.']]]);
    }

    public function store(Request $request): JsonResponse
    {
        $organization = $this->api->organization($request);
        $this->api->authorize($request, 'domain.manage', CommandScope::organization($organization->id));
        $data = $request->validate([
            'provider' => ['nullable', 'in:wedos'], 'login' => ['required', 'string', 'max:190'], 'password' => ['required', 'string', 'min:4', 'max:200'],
            'label' => ['nullable', 'string', 'max:120'], 'customer_number' => ['nullable', 'string', 'max:40'],
        ]);

        return $this->dispatch(new RegistrarConnectionCommand($organization->id, $this->idempotencyKey($request, 'registrar.connection.connect'), ['op' => 'connect'] + $data), $this->api->context($request, $organization), 201);
    }

    public function show(Request $request, RegistrarConnectionService $connections, string $connection): JsonResponse
    {
        $organization = $this->api->organization($request);
        $model = $this->resolve($request, $organization, $connection, 'domain.read');
        $domains = $model->state === 'disabled' ? collect() : $connections->mirrored($model)->orderBy('expires_at')->get();
        $services = Service::query()->where('organization_id', $organization->id)->whereIn('family', ['web', 'managed'])->whereIn('state', [ServiceStateMachine::ACTIVE, ServiceStateMachine::DEGRADED])->orderBy('created_at')->get()
            ->map(fn (Service $s) => ['id' => $s->id, 'hostname' => $s->hostname, 'label' => $s->label ?: $s->hostname, 'project_id' => $s->project_id])->values()->all();

        return response()->json(['data' => array_merge(Presenters::registrarConnection($model, $domains->count()), [
            'domain_count' => $domains->count(),
            'domains' => $domains->map(fn (Domain $d) => Presenters::domain($d))->values()->all(), // the detail carries the list where the list endpoint carries the count
            'services' => $services,
            'runs' => array_values((array) $model->runs),
        ])]);
    }

    public function probe(Request $request, string $connection): JsonResponse
    {
        $organization = $this->api->organization($request);
        $model = $this->resolve($request, $organization, $connection, 'domain.manage');

        return $this->dispatch(new RegistrarConnectionCommand($organization->id, 'registrar.connection.probe:'.$model->id.':'.uniqid('', true), ['op' => 'probe', 'connection_id' => $model->id]), $this->api->context($request, $organization));
    }

    public function sync(Request $request, string $connection): JsonResponse
    {
        $organization = $this->api->organization($request);
        $model = $this->resolve($request, $organization, $connection, 'domain.manage');

        return $this->dispatch(new RegistrarConnectionCommand($organization->id, 'registrar.connection.sync:'.$model->id.':'.uniqid('', true), ['op' => 'sync', 'connection_id' => $model->id]), $this->api->context($request, $organization));
    }

    public function update(Request $request, string $connection): JsonResponse
    {
        $organization = $this->api->organization($request);
        $model = $this->resolve($request, $organization, $connection, 'domain.manage');
        $data = $request->validate([
            'label' => ['sometimes', 'string', 'max:120'], 'auto_sync' => ['sometimes', 'boolean'], 'sync_dns' => ['sometimes', 'boolean'], 'notices' => ['sometimes', 'boolean'],
            'credit_threshold_minor' => ['sometimes', 'integer', 'min:0', 'max:100000000'], 'pair_service_id' => ['sometimes', 'nullable', 'string', 'max:40'],
        ]);

        return $this->dispatch(new RegistrarConnectionCommand($organization->id, $this->idempotencyKey($request, 'registrar.connection.settings:'.$model->id), ['op' => 'settings', 'connection_id' => $model->id] + $data), $this->api->context($request, $organization));
    }

    public function destroy(Request $request, string $connection): JsonResponse
    {
        $organization = $this->api->organization($request);
        $model = $this->resolve($request, $organization, $connection, 'domain.manage');

        return $this->dispatch(new RegistrarConnectionCommand($organization->id, $this->idempotencyKey($request, 'registrar.connection.disconnect:'.$model->id), ['op' => 'disconnect', 'connection_id' => $model->id]), $this->api->context($request, $organization));
    }

    /** Everything that happened to the connection: sync runs kept on the row plus the audit trail (connect, sync, settings, pairing). */
    public function history(Request $request, string $connection): JsonResponse
    {
        $organization = $this->api->organization($request);
        $model = $this->resolve($request, $organization, $connection, 'domain.read');
        $audit = AuditEvent::query()->where('organization_id', $organization->id)
            ->where(fn ($q) => $q->where(fn ($r) => $r->where('resource_type', 'registrar_connection')->where('resource_id', $model->id))->orWhere('detail->connection_id', $model->id))
            ->orderByDesc('created_at')->limit(100)->get()
            ->map(fn (AuditEvent $e) => ['id' => $e->id, 'at' => $e->created_at?->toIso8601String(), 'actor' => ['type' => $e->actor_type, 'id' => $e->actor_id], 'action' => $e->action, 'result' => $e->result, 'resource' => [$e->resource_type, $e->resource_id], 'detail' => $e->detail])->all();

        return response()->json(['data' => ['runs' => array_values((array) $model->runs), 'audit' => $audit]]);
    }

    public function pair(Request $request, string $domain): JsonResponse
    {
        $organization = $this->api->organization($request);
        $model = $this->domain($request, $organization, $domain, 'domain.manage');
        $data = $request->validate(['service_id' => ['required', 'string', 'max:40']]);
        $service = Service::query()->where('organization_id', $organization->id)->find($data['service_id']);
        if ($service === null) {
            throw DomainError::notFound('service');
        }
        $this->api->authorize($request, 'service.manage', CommandScope::resource($service->id, $service->organization_id, $service->project_id));

        return $this->dispatch(new RegistrarConnectionCommand($organization->id, $this->idempotencyKey($request, 'domain.pair:'.$model->id), ['op' => 'pair', 'domain_id' => $model->id, 'service_id' => $service->id]), $this->api->context($request, $organization), 202);
    }

    public function unpair(Request $request, string $domain): JsonResponse
    {
        $organization = $this->api->organization($request);
        $model = $this->domain($request, $organization, $domain, 'domain.manage');

        return $this->dispatch(new RegistrarConnectionCommand($organization->id, $this->idempotencyKey($request, 'domain.unpair:'.$model->id), ['op' => 'unpair', 'domain_id' => $model->id]), $this->api->context($request, $organization));
    }

    private function resolve(Request $request, Organization $organization, string $id, string $permission): RegistrarConnection
    {
        $model = RegistrarConnection::query()->where('organization_id', $organization->id)->find($id);
        if ($model === null) {
            throw DomainError::notFound('registrar_connection');
        }
        $this->api->authorize($request, $permission, CommandScope::organization($organization->id));

        return $model;
    }

    private function domain(Request $request, Organization $organization, string $idOrName, string $permission): Domain
    {
        $model = Domain::query()->where('organization_id', $organization->id)->find($idOrName);
        if ($model === null) {
            try {
                $model = Domain::query()->where('organization_id', $organization->id)->where('fqdn_ascii', Hostname::canonical($idOrName))->first();
            } catch (\InvalidArgumentException) {
                $model = null;
            }
        }
        if ($model === null) {
            throw DomainError::notFound('domain');
        }
        $this->api->authorize($request, $permission, CommandScope::organization($organization->id));

        return $model;
    }
}
