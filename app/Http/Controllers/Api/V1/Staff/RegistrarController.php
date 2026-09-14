<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Staff;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Presenters\Presenters;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Onhost\Domain\Domains\Commands\RegistrarConnectionCommand;
use Onhost\Domain\Domains\Models\RegistrarConnection;
use Onhost\Domain\Domains\RegistrarPricing;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Provisioning\Commands\ProvisioningCommand;
use Onhost\Platform\Commands\CommandScope;
use Onhost\Platform\Errors\DomainError;

/**
 * Registrar price book (Nastavení systému → Registrátoři domén): ONhost selling prices vs.
 * the wholesale price of every connected registrar per TLD, the registrar that currently
 * wins new registrations, manual cost rows for registrars without a price API and TLD pins.
 */
final class RegistrarController extends ApiController
{
    public function index(Request $request, RegistrarPricing $pricing): JsonResponse
    {
        $this->api->authorize($request, 'provider.instance.read', CommandScope::global());

        return $this->ok($pricing->matrix());
    }

    /** Customer-connected registrar accounts across organizations: state, last error, counts — the operator's view of bring-your-own WEDOS API. */
    public function connections(Request $request): JsonResponse
    {
        $this->api->authorize($request, 'domain.registrar.manage', CommandScope::global());
        $query = RegistrarConnection::query()->orderByDesc('created_at');
        foreach (['state', 'organization_id', 'provider'] as $filter) {
            if ($request->filled($filter)) {
                $query->where($filter, (string) $request->query($filter));
            }
        }
        $organizations = Organization::query()->whereIn('id', (clone $query)->pluck('organization_id')->unique()->all())->get()->keyBy('id');

        return $this->api->paginate($request, $query, fn (RegistrarConnection $c) => Presenters::registrarConnection($c) + ['organization' => ['id' => $c->organization_id, 'name' => $organizations[$c->organization_id]->name ?? null], 'runs' => array_slice((array) $c->runs, 0, 5)]);
    }

    public function syncConnection(Request $request, string $connection): JsonResponse
    {
        [$model, $organization] = $this->connection($request, $connection);

        return $this->dispatch(new RegistrarConnectionCommand($organization->id, 'registrar.connection.staff_sync:'.$model->id.':'.uniqid('', true), ['op' => 'staff_sync', 'connection_id' => $model->id]), $this->api->context($request, $organization));
    }

    public function disableConnection(Request $request, string $connection): JsonResponse
    {
        [$model, $organization] = $this->connection($request, $connection);
        $data = $request->validate(['reason' => ['required', 'string', 'max:250']]);

        return $this->dispatch(new RegistrarConnectionCommand($organization->id, $this->idempotencyKey($request, 'registrar.connection.disable:'.$model->id), ['op' => 'disable', 'connection_id' => $model->id, 'reason' => $data['reason']]), $this->api->context($request, $organization, $data['reason']));
    }

    public function enableConnection(Request $request, string $connection): JsonResponse
    {
        [$model, $organization] = $this->connection($request, $connection);

        return $this->dispatch(new RegistrarConnectionCommand($organization->id, $this->idempotencyKey($request, 'registrar.connection.enable:'.$model->id), ['op' => 'enable', 'connection_id' => $model->id]), $this->api->context($request, $organization));
    }

    /** @return array{0: RegistrarConnection, 1: Organization} */
    private function connection(Request $request, string $id): array
    {
        $this->api->authorize($request, 'domain.registrar.manage', CommandScope::global());
        $model = RegistrarConnection::query()->find($id);
        $organization = $model ? Organization::query()->find($model->organization_id) : null;
        if ($model === null || $organization === null) {
            throw DomainError::notFound('registrar_connection');
        }

        return [$model, $organization];
    }

    public function refresh(Request $request): JsonResponse
    {
        $data = $request->validate(['tlds' => ['nullable', 'array', 'max:200'], 'tlds.*' => ['string', 'max:32']]);

        return $this->dispatch(new ProvisioningCommand($this->idempotencyKey($request, 'registrar.costs.refresh'), ['op' => 'registrar.costs.refresh', 'tlds' => $data['tlds'] ?? null]), $this->api->context($request));
    }

    /** Import the public price lists (registrars without a wholesale API keep a retail-derived cost). */
    public function scrape(Request $request): JsonResponse
    {
        $data = $request->validate(['registrar' => ['nullable', 'string', 'max:24']]);

        return $this->dispatch(new ProvisioningCommand($this->idempotencyKey($request, 'registrar.costs.scrape:'.($data['registrar'] ?? 'all')), ['op' => 'registrar.costs.scrape', 'registrar' => $data['registrar'] ?? null]), $this->api->context($request));
    }

    public function upsertCost(Request $request): JsonResponse
    {
        $data = $request->validate([
            'registrar_provider' => ['required', 'string', 'max:24'], 'tld' => ['required', 'string', 'max:32'], 'currency' => ['nullable', 'in:CZK,EUR,USD'],
            'register' => ['nullable', 'numeric', 'min:0'], 'renew' => ['nullable', 'numeric', 'min:0'], 'transfer' => ['nullable', 'numeric', 'min:0'], 'restore' => ['nullable', 'numeric', 'min:0'], 'note' => ['nullable', 'string', 'max:250'],
        ]);

        return $this->dispatch(new ProvisioningCommand($this->idempotencyKey($request, 'registrar.costs.upsert:'.$data['registrar_provider'].':'.$data['tld']), ['op' => 'registrar.costs.upsert', 'cost' => $data]), $this->api->context($request));
    }

    public function setPolicy(Request $request): JsonResponse
    {
        $data = $request->validate(['tld' => ['required', 'string', 'max:32'], 'registrar_provider' => ['required', 'string', 'max:24']]);

        return $this->dispatch(new ProvisioningCommand($this->idempotencyKey($request, 'registrar.policy.set:'.$data['tld']), ['op' => 'registrar.policy.set'] + $data), $this->api->context($request));
    }
}
