<?php

declare(strict_types=1);

use App\Http\Controllers\Web\ServiceConsoleController;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Provisioning\Models\Node;
use Onhost\Domain\Provisioning\Models\ProviderBinding;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;

/*
 * Phase C, C9 — the graphical console of a customer's own server. The workbench used to flash the console token at the
 * customer and stop there: there was no page that could use it. Now /panel/konzole/{service} is a noVNC page that asks
 * for the token itself (the bus command, `service.console`, audit) and opens the RELAY with it. The page carries no token
 * and no ticket, sends a CSP of its own (scripts only with its nonce or from the pinned CDN, no eval, websockets only to
 * the relay) and is a 404 for anybody who may not open this server's console.
 */

beforeEach(fn () => Http::preventStrayRequests());

function consolePageVps(object $org, string $family = 'cloud'): Service
{
    $instance = pveLab();
    $node = Node::query()->where('name', 'prg1-n2')->firstOrFail();
    $service = Service::query()->create([
        'organization_id' => $org->id, 'product_key' => 'vps', 'family' => $family, 'name' => 'Compute 4', 'label' => 'app-prod', 'hostname' => 'vm-console.cust.onhost.cz', 'state' => ServiceStateMachine::ACTIVE,
        'region_code' => 'cz1', 'provider_instance_id' => $instance->id, 'node_id' => $node->id, 'desired_spec' => ['executor' => 'proxmox', 'family' => $family],
        'entitlements' => ['vcpu' => 4, 'ram_mb' => 8192, 'nvme_gb' => 160], 'sla_class' => 'standard', 'activated_at' => now(), 'tags' => [],
    ]);
    ProviderBinding::query()->create(['service_id' => $service->id, 'provider_instance_id' => $instance->id, 'remote_type' => 'qemu', 'remote_id' => '1042', 'remote_node' => 'prg1-n2', 'meta' => [], 'ownership' => ['managed_by' => 'onhost'], 'idempotency_key' => "console-page:{$service->id}", 'adapter_version' => '1.0.0']);

    return $service;
}

it('serves the noVNC page to the owner with a CSP of its own and nothing secret in it', function () {
    config()->set('onhost.console.relay_url', 'wss://relay.onhost.test');
    [$owner, $org] = $this->customerWithOrganization();
    $service = consolePageVps($org);

    $response = $this->actingAs($owner)->get("/panel/konzole/{$service->id}")->assertOk()->assertHeader('Cache-Control', 'no-store, private');
    $html = (string) $response->getContent();
    $csp = (string) $response->headers->get('Content-Security-Policy');
    preg_match("/'nonce-([^']+)'/", $csp, $m);
    expect($m[1] ?? '')->not->toBe('');
    expect($csp)->toContain("script-src 'self' 'nonce-{$m[1]}' https://cdn.jsdelivr.net")
        ->not->toContain('unsafe-eval')->not->toContain("script-src 'self' 'unsafe-inline'")
        ->toContain('connect-src \'self\' wss://relay.onhost.test https://relay.onhost.test')
        ->toContain("object-src 'none'");
    // the module script carries the nonce and loads the pinned noVNC release; the page asks for the token itself
    expect($html)->toContain('<script type="module" nonce="'.$m[1].'">')
        ->toContain(str_replace('/', '\/', ServiceConsoleController::NOVNC))
        ->toContain("'/console-token'")->toContain('new RFB(')->toContain('d.socket')->toContain('credentials: { password: d.password')
        ->toContain('Konzole · app-prod')
        ->not->toContain('con_')->not->toContain('PVEVNC');
    // every inline script that runs carries the nonce (the JSON config is data, never executed)
    preg_match_all('/<script(?![^>]*type="application\/json")[^>]*>/', $html, $scripts);
    foreach ($scripts[0] as $tag) {
        expect($tag)->toContain('nonce="'.$m[1].'"');
    }
    // the panel opens this page instead of flashing a token at the customer
    $workbench = (string) file_get_contents(base_path('apps/surfaces/api/onhost-panel-workbench.api.js'));
    expect($workbench)->toContain("window.open('/panel/konzole/' + encodeURIComponent(sel.id)")
        ->not->toContain("' · token ' + (d.token || '')");
});

it('is a 404 for another organization, for a service without a graphical console and for a service that does not exist', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $service = consolePageVps($org);
    [$stranger] = $this->customerWithOrganization();

    $this->actingAs($stranger)->get("/panel/konzole/{$service->id}")->assertNotFound();
    $this->actingAs($owner)->get('/panel/konzole/svc_nope')->assertNotFound();
    $game = featureGameService($org);
    $this->actingAs($owner)->get("/panel/konzole/{$game->id}")->assertNotFound();
});

it('needs a signed-in person', function () {
    [, $org] = $this->customerWithOrganization();
    $service = consolePageVps($org);

    $status = $this->get("/panel/konzole/{$service->id}")->getStatusCode();
    expect($status)->not->toBe(200);
});

it('says the console is off when no relay is configured, and its policy then allows no foreign websocket', function () {
    config()->set('onhost.console.relay_url', '');
    [$owner, $org] = $this->customerWithOrganization();
    $service = consolePageVps($org);

    $response = $this->actingAs($owner)->get("/panel/konzole/{$service->id}")->assertOk();
    expect((string) $response->headers->get('Content-Security-Policy'))->toContain("connect-src 'self';")
        ->and((string) $response->getContent())->toContain('"relay":false');
});
