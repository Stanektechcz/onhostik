<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Presenters\Presenters;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Onhost\Domain\Dns\Commands\DnsCommand;
use Onhost\Domain\Dns\DnsService;
use Onhost\Domain\Dns\Models\DnsZone;
use Onhost\Domain\Dns\Models\DnsZoneVersion;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Platform\Commands\CommandScope;
use Onhost\Platform\Errors\DomainError;

/** Two-phase DNS editing (handoff: batch of changes → explicit publish), versions and rollback. */
final class DnsController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $organization = $this->api->organization($request);
        $this->api->authorize($request, 'dns.zone.read', CommandScope::organization($organization->id));

        return $this->api->paginate($request, DnsZone::query()->where('organization_id', $organization->id), fn (DnsZone $z) => Presenters::zone($z, false), 'name');
    }

    public function show(Request $request, string $zone): JsonResponse
    {
        return response()->json(['data' => Presenters::zone($this->resolve($request, $zone))]);
    }

    public function store(Request $request): JsonResponse
    {
        $organization = $this->api->organization($request);
        $data = $request->validate(['name' => ['required', 'string', 'max:253'], 'template' => ['nullable', 'string', 'max:60'], 'vars' => ['nullable', 'array']]);

        return $this->dispatch(new DnsCommand($organization->id, $this->idempotencyKey($request, 'dns.zone'), ['op' => 'create_zone'] + $data), $this->api->context($request, $organization), 201);
    }

    public function stage(Request $request, string $zone): JsonResponse
    {
        $model = $this->resolve($request, $zone);
        $data = $request->validate(['change' => ['required', 'in:add,update,delete'], 'record_id' => ['nullable', 'string'], 'record' => ['nullable', 'array'], 'record.name' => ['nullable', 'string', 'max:253'], 'record.type' => ['nullable', 'string', 'max:10'], 'record.content' => ['nullable', 'string', 'max:4096'], 'record.ttl' => ['nullable', 'integer'], 'record.prio' => ['nullable', 'integer'], 'confirm_protected' => ['nullable', 'boolean'], 'reason' => ['nullable', 'string', 'max:250']]);

        return $this->command($request, $model, 'stage', $data, 201);
    }

    public function preview(Request $request, DnsService $dns, string $zone): JsonResponse
    {
        return response()->json(['data' => $dns->preview($this->resolve($request, $zone))]);
    }

    public function commit(Request $request, string $zone): JsonResponse
    {
        $model = $this->resolve($request, $zone);
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:250']]);

        return $this->command($request, $model, 'commit', $data);
    }

    public function discard(Request $request, string $zone): JsonResponse
    {
        return $this->command($request, $this->resolve($request, $zone), 'discard', []);
    }

    public function versions(Request $request, string $zone): JsonResponse
    {
        $model = $this->resolve($request, $zone);

        return $this->api->paginate($request, DnsZoneVersion::query()->where('zone_id', $model->id), fn (DnsZoneVersion $v) => ['version' => $v->version, 'serial' => $v->serial, 'records' => count((array) $v->records), 'reason' => $v->reason, 'committed_by' => $v->committed_by, 'committed_at' => $v->committed_at?->toIso8601String()], 'version');
    }

    public function rollback(Request $request, string $zone): JsonResponse
    {
        $model = $this->resolve($request, $zone);
        $data = $request->validate(['version' => ['required', 'integer', 'min:1']]);

        return $this->command($request, $model, 'rollback', $data);
    }

    public function dnssec(Request $request, string $zone): JsonResponse
    {
        $model = $this->resolve($request, $zone);
        $data = $request->validate(['enabled' => ['required', 'boolean']]);

        return $this->command($request, $model, 'dnssec', $data);
    }

    public function export(Request $request, DnsService $dns, string $zone): Response
    {
        $model = $this->resolve($request, $zone);

        return response($dns->export($model), 200, ['Content-Type' => 'text/plain; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="'.$model->name.'.zone"']);
    }

    public function destroy(Request $request, string $zone): JsonResponse
    {
        $model = $this->resolve($request, $zone);
        $data = $request->validate(['reason' => ['required', 'string', 'max:250']]);

        return $this->command($request, $model, 'delete_zone', $data);
    }

    private function command(Request $request, DnsZone $zone, string $op, array $payload, int $status = 200): JsonResponse
    {
        return $this->dispatch(new DnsCommand($zone->organization_id, $this->idempotencyKey($request, "dns.{$op}"), ['op' => $op, 'zone_id' => $zone->id] + $payload), $this->api->context($request, Organization::query()->find($zone->organization_id)), $status);
    }

    private function resolve(Request $request, string $idOrName): DnsZone
    {
        $zone = DnsZone::query()->find($idOrName) ?? DnsZone::query()->where('name', strtolower(rtrim($idOrName, '.')))->first();
        if ($zone === null) {
            throw DomainError::notFound('dns zone');
        }
        $this->api->authorize($request, 'dns.zone.read', CommandScope::organization($zone->organization_id));

        return $zone;
    }
}
