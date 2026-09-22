<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Onhost\Domain\Services\FinalArchive;
use Onhost\Domain\Services\Models\Backup;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;

/*
 * The final archive of a VPS is a PROTECTED backup on the backup server; the set on our disk holds only its metadata
 * (Brain card H488). When the retention ran out, `prune()` deleted the set and marked the archive expired — and the
 * whole disk image of the cancelled customer's server stayed on the backup server for good: protected, so no prune job
 * would ever take it, and nothing in the platform that still knew it was there.
 */

beforeEach(function () {
    Http::preventStrayRequests();
    Storage::fake('local');
});

/** A VPS archive whose retention has run out: the metadata set on our disk and the protected backup volume at the provider. */
function expiredVpsArchive(object $org): Backup
{
    $instance = pveLab();
    $service = Service::query()->create(['organization_id' => $org->id, 'product_key' => 'vps', 'family' => 'cloud', 'name' => 'Compute 4', 'state' => ServiceStateMachine::TERMINATED,
        'region_code' => 'cz1', 'provider_instance_id' => $instance->id, 'desired_spec' => ['executor' => 'proxmox', 'family' => 'cloud'], 'entitlements' => [], 'sla_class' => 'standard', 'tags' => []]);
    $set = FinalArchive::PREFIX.'/'.$org->id.'/'.$service->id.'-20260701-020000';
    Storage::disk('local')->put($set.'/service.json', '{}');
    Storage::disk('local')->put($set.'/manifest.json', '{}');

    return Backup::query()->create(['service_id' => $service->id, 'organization_id' => $org->id, 'provider_instance_id' => $instance->id, 'kind' => 'final', 'state' => 'completed',
        'started_at' => now()->subDays(90), 'finished_at' => now()->subDays(90), 'protected' => true, 'retention_until' => now()->subDay(), 'immutable_until' => now()->subDay(),
        'remote_id' => 'pbs-main:backup/vm/1042/2026-07-01T02:00:00Z', 'meta' => ['set' => $set, 'family' => 'cloud', 'snapshot' => ['remote_id' => 'pbs-main:backup/vm/1042/2026-07-01T02:00:00Z', 'size_bytes' => 21474836480]]]);
}

/**
 * The backup server as Proxmox shows it: one protected volume, which it refuses to delete while protected.
 *
 * @param  array{volumes:array<string,bool>, calls:list<string>, down?:bool}  $pve
 */
function backupServerFake(array &$pve): void
{
    Http::fake(function (Request $r) use (&$pve) {
        $path = rawurldecode((string) parse_url($r->url(), PHP_URL_PATH));
        $pve['calls'][] = $r->method().' '.$path;
        if (str_ends_with($path, '/nodes')) {
            return Http::response(['data' => [['node' => 'prg1-n2', 'status' => ($pve['down'] ?? false) ? 'offline' : 'online']]]);
        }
        if (preg_match('~/storage/pbs-main/content/(.+)$~', $path, $m) === 1) {
            $volid = $m[1];
            if (! array_key_exists($volid, $pve['volumes'])) {
                return Http::response(['errors' => ['volume' => 'does not exist']], 404);
            }
            if ($r->method() === 'PUT') {
                $pve['volumes'][$volid] = (bool) ($r->data()['protected'] ?? true);

                return Http::response(['data' => null]);
            }
            if ($r->method() === 'DELETE') {
                if ($pve['volumes'][$volid]) {
                    return Http::response(['errors' => ['volume' => 'backup is protected']], 400);
                }
                unset($pve['volumes'][$volid]);

                return Http::response(['data' => 'UPID:prg1-n2:delete']);
            }
        }

        return Http::response(['data' => null]);
    });
}

it('removes the protected disk image from the backup server when the archive expires', function () {
    [, $org] = $this->customerWithOrganization();
    $archive = expiredVpsArchive($org);
    $pve = ['volumes' => ['pbs-main:backup/vm/1042/2026-07-01T02:00:00Z' => true], 'calls' => []];
    backupServerFake($pve);

    expect(app(FinalArchive::class)->prune())->toBe(1);

    // it used to be: the metadata deleted, the row marked expired, and 20 GB of the customer's server kept for ever
    expect($pve['volumes'])->toBe([])
        ->and($archive->refresh()->state)->toBe('expired')
        ->and(Storage::disk('local')->exists((string) data_get($archive->meta, 'set').'/service.json'))->toBeFalse();
    // unprotected first — a protected backup cannot be deleted — then deleted
    $order = array_values(array_filter($pve['calls'], fn (string $c) => str_contains($c, '/content/')));
    expect($order[0])->toStartWith('PUT')->and($order[1])->toStartWith('DELETE');
});

it('does not call an archive expired while its data is still at the provider', function () {
    [, $org] = $this->customerWithOrganization();
    $archive = expiredVpsArchive($org);
    $pve = ['volumes' => ['pbs-main:backup/vm/1042/2026-07-01T02:00:00Z' => true], 'calls' => [], 'down' => true]; // no node can reach the storage today
    backupServerFake($pve);

    expect(app(FinalArchive::class)->prune())->toBe(0);

    expect($archive->refresh()->state)->toBe('completed') // not "expired": the disk image is still there
        ->and(data_get($archive->meta, 'expiry_blocked.why'))->toContain('No node')
        ->and(Storage::disk('local')->exists((string) data_get($archive->meta, 'set').'/service.json'))->toBeTrue(); // nor its metadata
    expect($pve['volumes'])->toHaveKey('pbs-main:backup/vm/1042/2026-07-01T02:00:00Z');
});

it('takes a volume that is already gone as gone', function () {
    [, $org] = $this->customerWithOrganization();
    $archive = expiredVpsArchive($org);
    $pve = ['volumes' => [], 'calls' => []]; // removed by hand, or by an earlier run that died before it wrote the row
    backupServerFake($pve);

    expect(app(FinalArchive::class)->prune())->toBe(1)->and($archive->refresh()->state)->toBe('expired');
});
