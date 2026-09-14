<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Onhost\Domain\Services\Models\Backup;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\UptimeMonitor;
use Onhost\Platform\Commands\CommandScope;

/** Organization-wide views the panel pages read: every uptime monitor and every backup across the customer's services. */
final class InsightsController extends ApiController
{
    public function monitors(Request $request): JsonResponse
    {
        $organization = $this->api->organization($request);
        $this->api->authorize($request, 'service.read', CommandScope::organization($organization->id));
        $services = $this->services($organization->id);
        $query = UptimeMonitor::query()->where('organization_id', $organization->id);
        if ($request->filled('state')) {
            $query->where('state', (string) $request->query('state'));
        }

        return $this->api->paginate($request, $query, fn (UptimeMonitor $m) => [
            'id' => $m->id, 'service_id' => $m->service_id, 'service' => $services[$m->service_id] ?? null, 'url' => $m->url, 'state' => $m->state, 'enabled' => (bool) $m->enabled, 'notify' => (bool) $m->notify,
            'interval_seconds' => (int) $m->interval_seconds, 'expected_status' => (int) $m->expected_status, 'keyword' => $m->keyword, 'consecutive_failures' => (int) $m->consecutive_failures,
            'last_checked_at' => $this->iso($m->last_checked_at), 'next_check_at' => $this->iso($m->next_check_at), 'last_status' => $m->last_status, 'last_ms' => $m->last_ms, 'last_error' => $m->last_error,
        ]);
    }

    public function backups(Request $request): JsonResponse
    {
        $organization = $this->api->organization($request);
        $this->api->authorize($request, 'backup.read', CommandScope::organization($organization->id));
        $services = $this->services($organization->id);
        $query = Backup::query()->where('organization_id', $organization->id);
        foreach (['state', 'kind', 'service_id'] as $filter) {
            if ($request->filled($filter)) {
                $query->where($filter, (string) $request->query($filter));
            }
        }

        return $this->api->paginate($request, $query, fn (Backup $b) => [
            'id' => $b->id, 'service_id' => $b->service_id, 'service' => $services[$b->service_id] ?? null, 'kind' => $b->kind, 'state' => $b->state, 'size_bytes' => $b->size_bytes,
            'started_at' => $this->iso($b->started_at), 'finished_at' => $this->iso($b->finished_at), 'verified_at' => $this->iso($b->verified_at), 'verify_status' => $b->verify_status, 'protected' => (bool) $b->protected,
        ]);
    }

    /** @return array<string, array{id:string, label:string, hostname:?string, family:string, state:string}> */
    private function services(string $organizationId): array
    {
        return Service::query()->where('organization_id', $organizationId)->get(['id', 'label', 'hostname', 'name', 'family', 'state'])
            ->mapWithKeys(fn (Service $s) => [$s->id => ['id' => $s->id, 'label' => (string) ($s->label ?: $s->hostname ?: $s->name), 'hostname' => $s->hostname, 'family' => $s->family, 'state' => $s->state]])->all();
    }

    private function iso(mixed $value): ?string
    {
        if ($value instanceof \DateTimeInterface) {
            return CarbonImmutable::instance($value)->toIso8601String();
        }

        return $value === null ? null : (string) $value;
    }
}
