<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Provisioning\IntegrationHealthProbe;
use Onhost\Domain\Provisioning\Models\ProviderInstance;

/*
 * "Test connection" only reads (Brain card H329). The probe runs on a schedule and from a button staff press on a
 * production panel: it must never create, change or delete anything there, whatever the panel is. Proxmox and the game
 * panel speak REST, so reading is GET; aaPanel and ISPConfig put the function in the URL and POST everything, so there
 * the function itself has to be a read.
 */

beforeEach(fn () => Http::preventStrayRequests());

/** @return list<array{method:string, target:string}> */
function probeCalls(): array
{
    return collect(Http::recorded())->map(fn (array $pair) => ['method' => $pair[0]->method(), 'target' => (string) parse_url($pair[0]->url(), PHP_URL_PATH).'?'.(string) parse_url($pair[0]->url(), PHP_URL_QUERY)])->values()->all();
}

it('probes Proxmox and the game panel with GET requests only', function () {
    Http::fake(function (Request $request) {
        $path = (string) parse_url($request->url(), PHP_URL_PATH);

        return match (true) {
            str_ends_with($path, '/api2/json/version') => Http::response(['data' => ['version' => '8.2.4', 'release' => '8.2']]),
            str_ends_with($path, '/api2/json/cluster/status') => Http::response(['data' => [['type' => 'cluster', 'quorate' => 1]]]),
            $path === '/api/application/nodes' => Http::response(['object' => 'list', 'data' => [['object' => 'node', 'attributes' => ['id' => 2, 'name' => 'games01', 'memory' => 65536, 'disk' => 500000, 'maintenance_mode' => false, 'allocated_resources' => ['memory' => 0, 'disk' => 0]]]], 'meta' => ['pagination' => ['total_pages' => 1, 'current_page' => 1]]]),
            default => Http::response(['data' => []]),
        };
    });
    [, $org] = $this->customerWithOrganization();
    featureGameService($org);
    $probe = app(IntegrationHealthProbe::class);

    expect($probe->probeInstance(pveLab())['up'])->toBeTrue()
        ->and($probe->probeInstance(ProviderInstance::query()->where('key', 'pterodactyl-games01')->firstOrFail())['up'])->toBeTrue();
    $calls = probeCalls();
    expect($calls)->not->toBe([])->and(collect($calls)->pluck('method')->unique()->values()->all())->toBe(['GET']);
});

it('probes aaPanel and ISPConfig with read functions only', function () {
    Http::fake(function (Request $request) {
        $function = (string) parse_url($request->url(), PHP_URL_QUERY);
        if (str_contains($request->url(), 'action=GetSystemTotal')) {
            return Http::response(['version' => '7.0.11', 'load' => 0.4, 'memRealUsed' => 2048, 'memTotal' => 8192]);
        }

        return Http::response(['code' => 'ok', 'message' => '', 'response' => match ($function) {
            'login' => 'sess-1', 'monitor_jobqueue_count' => 0, 'server_get' => ['hostname' => 's2.onhost.test'], 'server_get_serverid_by_ip' => [['server_id' => 1]], default => [],
        }]);
    });
    [, $org] = $this->customerWithOrganization();
    $probe = app(IntegrationHealthProbe::class);
    featureWebService($org, 'aapanel');
    featureWebService($org, 'ispconfig');

    foreach (['aapanel-managed01', 'ispconfig-shared01'] as $key) {
        $probe->probeInstance(ProviderInstance::query()->where('key', $key)->firstOrFail());
    }
    $targets = collect(probeCalls())->pluck('target');
    expect($targets->all())->not->toBe([]);
    // every function the probe asked for is a read: a session, a getter or a monitor counter — nothing that adds, updates or deletes
    foreach ($targets as $target) {
        expect($target)->toMatch('/(action=Get[A-Za-z]+|\?login$|\?logout$|_get([a-z_]*)?$|\?monitor_[a-z_]+$)/')
            ->and($target)->not->toMatch('/(_add|_update|_delete|_set|action=(Add|Set|Del|Remove|Create|Modify))/');
    }
});
