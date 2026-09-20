<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;

it('serves the public surface verbatim with rewritten asset paths, the boot object and deep-link hashes', function () {
    $home = $this->get('/')->assertOk()->assertHeader('Content-Type', 'text/html; charset=utf-8');
    $html = $home->getContent();
    expect($html)->toContain('window.ONHOST = {')->toContain('"surface":"public"')->toContain('"user":null')
        ->toContain('src="/surfaces/onhost-shell.js?v=')->toContain('src="/surfaces/api/onhost-session-bridge.js?v=')->toContain('href="/surfaces/_ds/')->toContain('src="/surfaces/support.js?v=')
        ->not->toContain('src="./support.js"')->not->toContain('src="onhost-data.js"');
    // the boot object is injected right before the shell, so the shell sees the session
    expect(strpos($html, 'window.ONHOST = {'))->toBeLessThan(strpos($html, 'src="/surfaces/onhost-shell.js?v='));
    expect($this->get('/stav')->assertOk()->getContent())->toContain('"hash":"#/stav"');
    expect($this->get('/blog/pue-118')->assertOk()->getContent())->toContain('"hash":"#/blog/pue-118"');
    $this->get('/neexistujici-stranka')->assertNotFound();

    $shell = $this->get('/surfaces/onhost-shell.js')->assertOk()->assertHeader('Content-Type', 'text/javascript; charset=utf-8');
    // the prototype file, rewritten at serve time in one place only: the surface switcher is not mounted for customers (seam #29)
    expect($shell->getContent())->toContain('OnhostSession')->toContain('function boot() { if (EMBED || (window.ONHOST && !window.ONHOST.demo)) return;');
    $this->get('/surfaces/_ds/modernist-31154b91-2bfe-4cc9-a4d9-6ffc68c8a498/styles.css')->assertOk()->assertHeader('Content-Type', 'text/css; charset=utf-8');
    $this->get('/surfaces/../.env')->assertNotFound();
    $this->get('/surfaces/..%2F.env')->assertNotFound();

    $data = $this->get('/surfaces/onhost-data.js')->assertOk()->assertHeader('Content-Type', 'text/javascript; charset=utf-8')->getContent();
    expect($data)->toContain('window.ONHOST_DATA = ')->toContain('catalog: function (cs)')->toContain('status: function (cs)')->toContain('"locations"');
});

it('gates the panel and admin surfaces, injects the real session and swaps the data seams for API-backed ones', function () {
    $this->get('/panel')->assertRedirect('/prihlaseni?next=%2Fpanel');
    $this->get('/surfaces/onhost-panel.js')->assertUnauthorized();

    [$customer, $org] = $this->customerWithOrganization();
    $service = Service::query()->create(['organization_id' => $org->id, 'product_key' => 'vps', 'family' => 'cloud', 'name' => 'VPS', 'label' => 'app-prod', 'hostname' => 'app-prod.onhost.cloud', 'state' => ServiceStateMachine::ACTIVE, 'region_code' => 'cz1', 'entitlements' => [], 'desired_spec' => ['vcpu' => 4, 'ram_mb' => 8192, 'nvme_gb' => 80, 'image' => 'debian-12'], 'sla_class' => 'standard', 'activated_at' => now()->subDays(3), 'tags' => ['access' => ['ipv4' => '192.0.2.10']], 'health' => ['cpu_pct' => 41]]);
    $this->actingAs($customer);

    $panel = $this->get('/panel/sluzby')->assertOk()->getContent();
    expect($panel)->toContain('"role":"klient"')->toContain('"hash":"#/sluzby"')->toContain('"email":"'.$customer->email.'"')->toContain('"organization":{"id":"'.$org->id.'"')
        ->toContain('src="/surfaces/api/onhost-store.api.js?v=')->not->toContain('src="/surfaces/onhost-store.js"')
        ->toContain('src="/surfaces/onhost-panel.js?t=')
        ->toContain('>'.htmlspecialchars($customer->name, ENT_QUOTES | ENT_HTML5).'</span>')->toContain('>'.$customer->email.'</div>')->not->toContain('>Hana Nováková<')
        ->toContain('services: (window.ONHOST_PANEL && window.ONHOST_PANEL.services) || {')
        ->toContain('servers: (window.ONHOST_PANEL && window.ONHOST_PANEL.servers) || [')
        ->toContain('&quot;liveSimulation&quot;:{&quot;editor&quot;:&quot;boolean&quot;,&quot;default&quot;:false');

    // seams #35/#36: the projects and connected-registrars pages ride on the generic view, with their own deep links and a password field
    expect($panel)->toContain('src="/surfaces/api/onhost-panel-projects.api.js?v=')->toContain('src="/surfaces/api/onhost-panel-registrars.api.js?v=')
        ->toContain("if (T('projects')) sets.projects = (window.OnhostPanelProjects ? window.OnhostPanelProjects.view(this, _, { stat, pill, bar, dot, rowStyle, match }) : null) || sets.overview;")
        ->toContain("if (T('registrars')) sets.registrars = (window.OnhostPanelRegistrars ? window.OnhostPanelRegistrars.view(this, _, { stat, pill, bar, dot, rowStyle, match }) : null) || sets.overview;")
        ->toContain("projects: 'projekty', registrars: 'registratori'");
    expect($this->get('/panel/projekty')->assertOk()->getContent())->toContain('"hash":"#/projekty"');
    expect($this->get('/panel/registratori')->assertOk()->getContent())->toContain('"hash":"#/registratori"');
    foreach (['onhost-panel-projects.api.js' => 'window.OnhostPanelProjects = {', 'onhost-panel-registrars.api.js' => 'window.OnhostPanelRegistrars = {', 'onhost-panel-nav.api.js' => "open.push('registrars')"] as $module => $needle) {
        $this->get('/surfaces/api/'.$module)->assertOk();
        expect((string) file_get_contents(base_path('apps/surfaces/api/'.$module)))->toContain($needle);
    }

    $seam = $this->get('/surfaces/onhost-panel.js')->assertOk()->getContent();
    expect($seam)->toContain('window.ONHOST_PANEL = ')->toContain('"id":"'.$service->id.'"')->toContain('"type":"vps"')->toContain('"spec":"4 vCPU / 8 GB / 80 GB NVMe"')->toContain('"state":"running"')->toContain('"cpu":41')->toContain('"ip":"192.0.2.10"');

    $this->get('/sprava')->assertRedirect('/panel'); // customers never see the staff console
    $this->get('/partner')->assertOk(); // the surface itself loads; the portal API answers partner_missing until enrolled

    $this->actingAs($this->staff('sre'));
    expect($this->get('/sprava/incidenty')->assertOk()->getContent())->toContain('"role":"noc"')->toContain('"staff":true')->toContain('"hash":"#/incidenty"')->toContain('src="/surfaces/api/onhost-integrations.api.js?v=');
    $this->actingAs($this->staff('billing_finance_admin'));
    expect($this->get('/sprava')->assertOk()->getContent())->toContain('"role":"fakturace"');
    $this->actingAs($this->staff('support_manager'));
    $console = $this->get('/sprava')->assertOk()->getContent();
    expect($console)->toContain('"role":"admin"')
        ->toContain('src="/surfaces/api/onhost-admin.api.js') // seam #33: the console outside demo mode
        ->toContain('.filter(v => !window.OnhostAdmin || window.OnhostAdmin.allows(v))')
        ->toContain('get TICKETS() { return (window.OnhostAdmin && window.OnhostAdmin.tickets(this)) || this.TICKETS_PROTO; }')
        ->toContain('window.OnhostAdmin.ticketActions(this, sel, _)')->toContain('window.OnhostAdmin.quick(this, sel, _)')->toContain('window.OnhostAdmin.context(this, sel, _)')
        ->toContain('window.OnhostAdmin.customers(this, _)')->toContain('window.OnhostAdmin.cards(this, _)')->toContain('window.OnhostAdmin.dashPanels(this, _)')
        ->toContain("onCallTitle: window.OnhostAdmin ? '' : _('Na směně', 'On call')")->toContain('log: window.OnhostAdmin ? [] : [');
    $this->get('/surfaces/api/onhost-admin.api.js')->assertOk();
    expect((string) file_get_contents(base_path('apps/surfaces/api/onhost-admin.api.js')))->toContain('window.OnhostAdmin = {')->toContain("var ALLOWED = ['dash', 'queue', 'ticket', 'customers', 'incidents', 'maintenance', 'gnodes', 'geggs', 'galloc', 'gprov', 'fleet', 'jobsadm', 'automation', 'renewals', 'money', 'coupons', 'nodecost']")->toContain('table: table,');
    // table views (audit §5f-2): the game panels, the fleet, the jobs, the automation rules and the renewals read their rows from the module and their counts into the sidebar
    expect($console)->toContain('const t = (window.OnhostAdmin && window.OnhostAdmin.table(this, _, s.view)) || T[s.view];')->toContain('window.OnhostAdmin.counts(this).gnodes')->toContain('window.OnhostAdmin.counts(this).renewals')->toContain('window.OnhostAdmin.counts(this).fleetDot');
});

it('hands console descriptors to the relay once, only with the relay key, and lets the browser pre-flight validity', function () {
    config(['onhost.console.relay_key' => 'relay-secret']);
    [$customer, $org] = $this->customerWithOrganization();
    $token = 'con_'.strtolower((string) Str::ulid());
    Cache::put("onhost:console:{$token}", ['kind' => 'pve_vnc', 'upstream' => 'https://pve.lab/api2/json/nodes/n1/qemu/100/vncwebsocket', 'port' => 5900, 'vncticket' => 'PVEVNC:secret', 'service_id' => 'srv_x', 'organization_id' => $org->id, 'expires_at' => now()->addMinutes(2)->toIso8601String()], 120);

    $this->getJson("/console/ws/{$token}")->assertUnauthorized();
    $this->withHeader('X-Relay-Key', 'wrong')->getJson("/console/ws/{$token}")->assertUnauthorized();
    $this->actingAs($customer);
    $this->getJson("/console/check/{$token}")->assertOk()->assertJsonPath('data.valid', true)->assertJsonPath('data.kind', 'pve_vnc')->assertJsonMissingPath('data.vncticket');
    $resolved = $this->withHeader('X-Relay-Key', 'relay-secret')->getJson("/console/ws/{$token}")->assertOk();
    expect($resolved->json('data.vncticket'))->toBe('PVEVNC:secret')->and($resolved->json('data.single_use'))->toBeTrue();
    $this->withHeader('X-Relay-Key', 'relay-secret')->getJson("/console/ws/{$token}")->assertStatus(410);
    $this->getJson("/console/check/{$token}")->assertOk()->assertJsonPath('data.valid', false);
    $this->withHeader('X-Relay-Key', 'relay-secret')->getJson('/console/ws/not-a-token')->assertUnprocessable();
});

it('judges a console token by the service it was issued for — the shape the adapters really store', function () {
    [$owner, $org] = $this->customerWithOrganization();
    [$stranger] = $this->customerWithOrganization(['email' => 'cizi@example.cz']);
    $service = Service::query()->create(['organization_id' => $org->id, 'product_key' => 'vps', 'family' => 'cloud', 'name' => 'VPS', 'state' => 'ACTIVE', 'region_code' => 'cz1', 'entitlements' => [], 'desired_spec' => []]);
    $token = 'con_'.strtolower((string) Str::ulid());
    // neither adapter writes an organization into the descriptor: the membership check used to read null and pass everybody
    Cache::put("onhost:console:{$token}", ['kind' => 'pve_vnc', 'upstream' => 'https://pve.lab/x', 'port' => 5900, 'vncticket' => 'PVEVNC:secret', 'instance' => 'pi_1', 'service_id' => $service->id], 120);
    $orphan = 'con_'.strtolower((string) Str::ulid());
    Cache::put("onhost:console:{$orphan}", ['kind' => 'pve_vnc', 'upstream' => 'https://pve.lab/y', 'port' => 5900, 'vncticket' => 'PVEVNC:other'], 120);

    $this->actingAs($stranger)->getJson("/console/check/{$token}")->assertOk()->assertJsonPath('data.valid', false);
    $this->actingAs($stranger)->getJson("/console/check/{$orphan}")->assertOk()->assertJsonPath('data.valid', false); // nobody's token is nobody's console
    $this->actingAs($owner)->getJson("/console/check/{$token}")->assertOk()->assertJsonPath('data.valid', true);
});
