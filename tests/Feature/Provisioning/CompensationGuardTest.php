<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Catalog\Models\PlanVersion;
use Onhost\Domain\Catalog\Models\Product;
use Onhost\Domain\Notifications\Models\Notification;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Provisioning\Models\ProviderBinding;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\ServiceService;
use Onhost\Platform\Audit\AuditEvent;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Outbox\OutboxPublisher;

/*
 * A failed provisioning takes back what it created — and nothing else. The clone step binds the service to the vmid it
 * RESERVED the moment the clone is accepted; when the clone then fails because somebody else took that number in the
 * meantime (the panel's own UI, another automation), the binding points at a stranger's machine, and the compensation
 * stopped and destroyed it. Deleting is the one operation with no undo: a compensation deletes only what the panel
 * confirms to be the thing this operation made, exactly like a cancellation does.
 */

beforeEach(function () {
    $this->seed(CatalogSeeder::class);
    pveLab();
    Http::preventStrayRequests();
});

/** A panel where the clone of our VM fails and vmid 1042 is a machine called `$nameAtPanel`. @param list<string> $calls */
function compensationPanel(array &$calls, ?string $nameAtPanel): void
{
    Http::fake(function (Request $r) use (&$calls, $nameAtPanel) {
        $path = (string) parse_url($r->url(), PHP_URL_PATH);
        $calls[] = $r->method().' '.$path;

        return match (true) {
            str_ends_with($path, '/cluster/resources') => Http::response(['data' => []]),
            str_ends_with($path, '/cluster/nextid') => Http::response(['data' => '1042']),
            str_ends_with($path, '/qemu/9001/clone') => Http::response(['data' => 'UPID:prg1-n2:000A1B2C:0004E1F5:66F0AA11:qmclone:9001:onhost@pve!cp:']),
            str_contains($path, '/tasks/') => Http::response(['data' => ['status' => 'stopped', 'exitstatus' => 'unable to create VM 1042 - VM 1042 already exists on node \'prg1-n2\'']]),
            str_ends_with($path, '/qemu/1042/status/current') => $nameAtPanel === null ? Http::response(['errors' => 'Configuration file does not exist'], 500) : Http::response(['data' => ['status' => 'running', 'uptime' => 86400]]),
            str_ends_with($path, '/qemu/1042/config') => $nameAtPanel === null ? Http::response(['errors' => 'Configuration file does not exist'], 500) : Http::response(pveVmConfig(['name' => $nameAtPanel, 'tags' => 'somebody-else'])),
            str_ends_with($path, '/qemu/1042/status/stop') => Http::response(['data' => 'UPID:prg1-n2:1:1:1:qmstop:1042:onhost@pve!cp:']),
            default => Http::response(['data' => null]),
        };
    });
}

/** @return array{0:Service,1:Operation} */
function compensationVps(Organization $org, CommandContext $ctx): array
{
    $product = Product::query()->where('key', 'vps')->firstOrFail();
    $version = PlanVersion::query()->whereHas('plan', fn ($q) => $q->where('key', 'compute-4'))->firstOrFail();
    $service = app(ServiceService::class)->create($org, $product, $version, ['ssh_keys' => ['ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIExample test@onhost'], 'options' => ['ipv4' => true]], $ctx);

    return [$service, Operation::query()->where('service_id', $service->id)->firstOrFail()];
}

it('does not destroy a stranger\'s machine whose number a failed clone had reserved', function () {
    $calls = [];
    compensationPanel($calls, 'cizi-vm-uctarna'); // vmid 1042 exists — and it is not ours
    [$user, $org] = $this->customerWithOrganization();
    [$service, $operation] = compensationVps($org, $this->contextFor($user, $org));

    $operation = driveOperation($operation);

    expect($operation->state)->toBeIn([Operation::FAILED, 'COMPENSATED']);
    expect(ProviderBinding::query()->where('service_id', $service->id)->where('remote_id', '1042')->exists())->toBeTrue(); // the binding the clone step wrote
    // it used to be: POST …/1042/status/stop and DELETE …/qemu/1042 — somebody else's running server
    expect(collect($calls)->filter(fn (string $c) => str_starts_with($c, 'DELETE') || str_ends_with($c, '/status/stop') || str_ends_with($c, '/status/shutdown'))->values()->all())->toBe([]);
    // kept, on record, and somebody hears about it
    expect(AuditEvent::query()->where('action', 'provisioning.compensation.kept')->where('resource_id', $service->id)->exists())->toBeTrue();
    app(OutboxPublisher::class)->relayPending();
    expect(Notification::query()->where('audience', 'internal')->where('title', 'like', 'Po nezdařeném zřízení zůstal zdroj na panelu%')->exists())->toBeTrue();
    expect(Service::query()->withTrashed()->findOrFail($service->id)->state)->toBe('FAILED');
});

it('has nothing to take back when the panel has nothing under that number', function () {
    $calls = [];
    compensationPanel($calls, null);
    [$user, $org] = $this->customerWithOrganization();
    [, $operation] = compensationVps($org, $this->contextFor($user, $org));

    $operation = driveOperation($operation);

    expect($operation->state)->toBeIn([Operation::FAILED, 'COMPENSATED'])
        ->and(collect($calls)->filter(fn (string $c) => str_starts_with($c, 'DELETE'))->count())->toBe(0);
});

it('takes back the machine it cloned itself once the panel confirms it is that machine', function () {
    $calls = [];
    $cloned = null;
    Http::fake(function (Request $r) use (&$calls, &$cloned) {
        $path = (string) parse_url($r->url(), PHP_URL_PATH);
        $calls[] = $r->method().' '.$path;

        return match (true) {
            str_ends_with($path, '/cluster/resources') => Http::response(['data' => []]),
            str_ends_with($path, '/cluster/nextid') => Http::response(['data' => '1042']),
            str_ends_with($path, '/qemu/9001/clone') => (function () use ($r, &$cloned) {
                $cloned = (string) $r['name']; // the name OUR clone gave the machine

                return Http::response(['data' => 'UPID:prg1-n2:000A1B2C:0004E1F5:66F0AA11:qmclone:9001:onhost@pve!cp:']);
            })(),
            str_contains($path, '/tasks/') => Http::response(['data' => ['status' => 'stopped', 'exitstatus' => 'OK']]),
            str_ends_with($path, '/qemu/1042/status/current') => Http::response(['data' => ['status' => 'stopped']]),
            str_ends_with($path, '/qemu/1042/config') => $r->method() === 'GET' ? Http::response(pveVmConfig(['name' => $cloned])) : Http::response(['data' => null]),
            str_ends_with($path, '/qemu/1042/resize') => Http::response(['errors' => ['size' => 'value does not match the regex pattern']], 400), // the sizing step fails for good
            default => Http::response(['data' => null]),
        };
    });
    [$user, $org] = $this->customerWithOrganization();
    [$service, $operation] = compensationVps($org, $this->contextFor($user, $org));

    $operation = driveOperation($operation);

    expect($operation->state)->toBeIn([Operation::FAILED, 'COMPENSATED'])->and($cloned)->not->toBeNull();
    expect(collect($calls)->contains(fn (string $c) => $c === 'DELETE /api2/json/nodes/prg1-n2/qemu/1042'))->toBeTrue() // ours, confirmed by its name: taken back
        ->and(AuditEvent::query()->where('action', 'provisioning.compensation.kept')->where('resource_id', $service->id)->exists())->toBeFalse();
});
