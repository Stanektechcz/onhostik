<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Provisioning\Models\Node;
use Onhost\Domain\Provisioning\Models\ProviderBinding;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;

/*
 * Phase C, C9/C10 — the live consoles through the websocket relay, with the test playing the relay (infra/console-relay
 * does exactly these calls). The browser gets a single-use `con_…` token and the relay socket to open (`socket`), never the
 * game panel's JWT or the hypervisor's ticket; the relay resolves the token ONCE with its shared key and gets what it
 * needs to open the upstream (Wings: socket + JWT for the `auth` frame; Proxmox: vncwebsocket + port + ticket).
 */

const RELAY_FAKE_KEY = 'relay-shared-secret-for-tests';

beforeEach(function () {
    Http::preventStrayRequests();
    config()->set('onhost.console.relay_url', 'https://relay.onhost.test/');
    config()->set('onhost.console.relay_key', RELAY_FAKE_KEY);
});

function relayFakeVps(object $org): Service
{
    $instance = pveLab();
    $node = Node::query()->where('name', 'prg1-n2')->firstOrFail();
    $service = Service::query()->create([
        'organization_id' => $org->id, 'product_key' => 'vps', 'family' => 'cloud', 'name' => 'Compute 4', 'hostname' => 'vm-relay.cust.onhost.cz', 'state' => ServiceStateMachine::ACTIVE,
        'region_code' => 'cz1', 'provider_instance_id' => $instance->id, 'node_id' => $node->id, 'desired_spec' => ['executor' => 'proxmox', 'family' => 'cloud'],
        'entitlements' => ['vcpu' => 4, 'ram_mb' => 8192, 'nvme_gb' => 160], 'sla_class' => 'standard', 'activated_at' => now(), 'tags' => [],
    ]);
    ProviderBinding::query()->create(['service_id' => $service->id, 'provider_instance_id' => $instance->id, 'remote_type' => 'qemu', 'remote_id' => '1042', 'remote_node' => 'prg1-n2', 'meta' => [], 'ownership' => ['managed_by' => 'onhost'], 'idempotency_key' => "relay:{$service->id}", 'adapter_version' => '1.0.0']);

    return $service;
}

it('hands the browser a relay socket for the game console and the relay — once — what it needs to open Wings', function () {
    Http::fake(function (Request $request) {
        if (str_ends_with((string) parse_url($request->url(), PHP_URL_PATH), '/api/client/servers/e4c1abcd/websocket')) {
            return Http::response(['data' => ['socket' => 'wss://games01.node.test:8080/api/servers/e4c1-uuid/ws', 'token' => 'eyJ.wings-session-jwt.sig']]);
        }

        return Http::response([], 501);
    });
    [$owner, $org] = $this->customerWithOrganization();
    $service = featureGameService($org);

    $this->actingAs($owner, 'sanctum');
    $issued = $this->postJson("/v1/services/{$service->id}/console-token", [], ['X-Organization' => $org->id])->assertOk();
    $token = (string) $issued->json('token');
    expect($token)->toMatch('/^con_[0-9a-z]{26}$/')
        ->and($issued->json('kind'))->toBe('wings')
        ->and($issued->json('socket'))->toBe('wss://relay.onhost.test/ws/'.$token) // the relay, never the node
        ->and(json_encode($issued->json()))->not->toContain('wings-session-jwt');
    // the browser pre-flight passes for the owner
    $this->getJson("/console/check/{$token}")->assertOk()->assertJsonPath('data.valid', true);

    // the relay without its key gets nothing, and the attempt does not use the token up
    auth()->forgetGuards();
    $this->getJson("/console/ws/{$token}", ['X-Relay-Key' => 'wrong'])->assertStatus(401);
    $resolved = $this->getJson("/console/ws/{$token}", ['X-Relay-Key' => RELAY_FAKE_KEY])->assertOk();
    expect($resolved->json('data'))->toMatchArray(['kind' => 'wings_ws', 'socket' => 'wss://games01.node.test:8080/api/servers/e4c1-uuid/ws', 'token' => 'eyJ.wings-session-jwt.sig', 'service_id' => $service->id, 'single_use' => true]);
    // single use: a replayed token opens nothing
    $this->getJson("/console/ws/{$token}", ['X-Relay-Key' => RELAY_FAKE_KEY])->assertStatus(410)->assertJsonPath('error', 'console_token_expired');
});

it('hands the noVNC page a relay socket and a one-time VNC password, and the relay the ticket', function () {
    Http::fake(['pve.mgmt.test:8006/api2/json/nodes/prg1-n2/qemu/1042/vncproxy' => Http::response(['data' => ['port' => '5901', 'ticket' => 'PVEVNC:relay-only', 'password' => 'vnc-once-123', 'user' => 'onhost@pve!cp']])]);
    [$owner, $org] = $this->customerWithOrganization();
    $service = relayFakeVps($org);

    $this->actingAs($owner, 'sanctum');
    $issued = $this->postJson("/v1/services/{$service->id}/console-token", [], ['X-Organization' => $org->id])->assertOk();
    $token = (string) $issued->json('token');
    expect($issued->json('kind'))->toBe('novnc')->and($issued->json('socket'))->toBe('wss://relay.onhost.test/ws/'.$token)
        ->and($issued->json('password'))->toBe('vnc-once-123')
        ->and(json_encode($issued->json()))->not->toContain('relay-only');

    auth()->forgetGuards();
    $resolved = $this->getJson("/console/ws/{$token}", ['X-Relay-Key' => RELAY_FAKE_KEY])->assertOk();
    expect($resolved->json('data.kind'))->toBe('pve_vnc')->and($resolved->json('data.vncticket'))->toBe('PVEVNC:relay-only')
        ->and((int) $resolved->json('data.port'))->toBe(5901)->and($resolved->json('data.upstream'))->toEndWith('/nodes/prg1-n2/qemu/1042/vncwebsocket');
});

it('says there is no live console when no relay is configured', function () {
    config()->set('onhost.console.relay_url', '');
    Http::fake(['pve.mgmt.test:8006/api2/json/nodes/prg1-n2/qemu/1042/vncproxy' => Http::response(['data' => ['port' => '5901', 'ticket' => 'PVEVNC:x', 'password' => 'p']])]);
    [$owner, $org] = $this->customerWithOrganization();
    $service = relayFakeVps($org);

    $this->actingAs($owner, 'sanctum');
    $this->postJson("/v1/services/{$service->id}/console-token", [], ['X-Organization' => $org->id])->assertOk()->assertJsonPath('socket', null);
});

it('wires the workbench to the relay: the live game console, commands into it, binary uploads and the VPS reinstall', function () {
    $js = (string) file_get_contents(base_path('apps/surfaces/api/onhost-panel-workbench.api.js'));
    expect($js)
        // the live console: token → relay socket → Wings frames, the panel never sees the JWT
        ->toContain("API.post('/services/' + sel.id + '/console-token'")->toContain('new WebSocket(d.socket)')
        ->toContain("frame.event === 'auth success'")->toContain("event: 'send logs'")->toContain("frame.event === 'console output'")
        ->toContain("frame.event === 'token expiring'")->toContain("event: 'send command'")
        // binary upload: multipart to the scanned upload endpoint, into the folder open now
        ->toContain('new FormData()')->toContain("form.append('directory', root || '/')")->toContain("'/game-files/upload', form")
        // reinstall: allowed images only, the server's own preview and its fingerprint
        ->toContain("'/actions/reinstall/preview?params%5Bimage%5D='")->toContain('confirm: p.fingerprint')->toContain('images.indexOf(pick) < 0');
    // the prototype surfaces stay byte-identical: everything above lives in the api module
    expect((string) file_get_contents(base_path('apps/surfaces/Onhost-app.dc.html')))->not->toContain('liveStart');
});
