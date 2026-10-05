<?php

declare(strict_types=1);

use App\Http\Support\CreditClaimsSeam;
use Onhost\Domain\Provisioning\Models\ProviderBinding;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/*
 * Phase G, package G8 (TASK-0118): UI follow-ups. The browser side (the one input dialog that replaced every window.prompt, service
 * accounts in the portal, the resend of the verification mail, the staff sign-on button, the Czech plurals) is exercised by
 * tests/js/g8-ui.harness.mjs, which this test runs. The server side: the destructive preview speaks English when asked, a customer is
 * never told the hypervisor's word (`qemu`), and no served page promises credit back in cash — the prototypes stay byte-identical
 * and are corrected on the way out (CreditClaimsSeam).
 */

it('runs the browser-side harness of the G8 screens', function () {
    $node = (new ExecutableFinder)->find('node');
    if ($node === null) {
        $this->markTestSkipped('node is not installed; the harness is tests/js/g8-ui.harness.mjs');
    }
    $process = new Process([$node, base_path('tests/js/g8-ui.harness.mjs')], base_path(), null, null, 120);
    $process->run();

    expect($process->getExitCode())->toBe(0, $process->getOutput().$process->getErrorOutput());
    expect($process->getOutput())->toMatch('~(\d+)/\1 passed~');
});

it('serves the shared dialog, the Czech count helper and the screens that use them', function () {
    $served = fn (string $file) => (string) file_get_contents((string) $this->get('/surfaces/api/'.$file)->assertOk()->baseResponse->getFile());

    expect($served('onhost-session-bridge.js'))->toContain('window.OnhostDialog')->toContain('cn: function (n, cs, en, lang)');
    expect($served('onhost-panel-account.api.js'))->toContain("A().get('/service-accounts')")->toContain("A().post('/me/email/verification'");
    expect($served('onhost-admin-customer.api.js'))->toContain('/panel-login');
});

it('does not leave a Czech count with a hard-coded plural noun in any API module', function () {
    // "1 dní", "2 domén": the noun is picked by the count (OnhostI18n.cn); these spellings are what the modules used to concatenate
    $left = [];
    foreach (glob(base_path('apps/surfaces/api/*.js')) ?: [] as $file) {
        if (preg_match("~\\+ _\\('\\s(dní|domén|záloh|schránek|souborů|řádků|zón|generací|požadavků|hrozeb blokováno|dokončených|odměněných|proměnných|oprávnění|typů serveru)[ ·']~u", (string) file_get_contents($file), $m)) {
            $left[] = basename($file).': '.$m[1];
        }
    }

    expect($left)->toBe([]);
});

it('says what a destructive action would do in English when the request says locale=en, in Czech otherwise', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $vps = g8Vps($org);
    $this->actingAs($owner, 'sanctum');

    $cs = $this->getJson("/v1/services/{$vps->id}/actions/terminate/preview", ['X-Organization' => $org->id])->assertOk()->json('data');
    $en = $this->getJson("/v1/services/{$vps->id}/actions/terminate/preview?locale=en", ['X-Organization' => $org->id])->assertOk()->json('data');
    $browserEn = $this->getJson("/v1/services/{$vps->id}/actions/terminate/preview", ['X-Organization' => $org->id, 'Accept-Language' => 'en-GB,en;q=0.9'])->assertOk()->json('data');
    $forced = $this->getJson("/v1/services/{$vps->id}/actions/terminate/preview?locale=cs", ['X-Organization' => $org->id, 'Accept-Language' => 'en'])->assertOk()->json('data');

    expect(implode(' ', $cs['what']))->toContain('ochranné lhůtě')
        ->and($cs['recovery']['note'])->toContain('Před odstraněním')
        ->and(implode(' ', $en['what']))->toContain('grace period')->not->toContain('ochranné')
        ->and($en['recovery']['note'])->toContain('Before the removal')->not->toContain('Před')
        ->and(implode(' ', $browserEn['what']))->toContain('ochranné lhůtě') // an English browser alone does not turn a Czech page English
        ->and(implode(' ', $forced['what']))->toContain('ochranné lhůtě');
    // only the words differ: the fingerprint of the target is the same in both languages, so a confirmation made on the English
    // preview is accepted for the Czech one and the other way round
    expect($en['fingerprint'])->toBe($cs['fingerprint'])->and($browserEn['fingerprint'])->toBe($cs['fingerprint']);

    // the refusals speak it too
    $this->getJson("/v1/services/{$vps->id}/actions/power/preview?locale=en", ['X-Organization' => $org->id])->assertStatus(422)->assertJsonPath('error', 'action_not_destructive')
        ->assertJsonPath('message', 'This action deletes and overwrites nothing; no preview is needed.');
});

it('tells a customer a virtual server, not the hypervisor\'s own word', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $vps = g8Vps($org);
    $this->actingAs($owner, 'sanctum');

    $this->getJson('/v1/services/'.$vps->id, ['X-Organization' => $org->id])->assertOk()
        ->assertJsonPath('data.bindings.0.type', 'vm')
        ->assertJsonMissing(['type' => 'qemu']);
    // the platform's own record keeps its word: the adapters and the identity check compare it
    expect(ProviderBinding::query()->where('service_id', $vps->id)->value('remote_type'))->toBe('qemu');
});

it('keeps every sentence that promised credit back in cash out of what is served, and the prototypes untouched', function () {
    $surfaces = base_path('apps/surfaces/');
    $files = ['content' => 'onhost-content.js', 'admin' => 'Onhost-admin.dc.html', 'panel' => 'Onhost-app.dc.html', 'svc-web' => 'onhost-svc-web.js'];

    foreach (CreditClaimsSeam::CLAIMS as $group => $pairs) {
        $prototype = (string) file_get_contents($surfaces.$files[$group]);
        foreach ($pairs as $needle => $true) {
            // the seam can only correct what is still there: a rewritten prototype sentence must fail here, not escape quietly
            expect(str_contains($prototype, $needle))->toBeTrue("{$files[$group]} no longer contains: {$needle}");
            expect($true === '' || ! str_contains($prototype, $true))->toBeTrue("{$files[$group]} was edited: the seam is the only place that may correct it");
        }
    }

    $js = (string) $this->get('/surfaces/onhost-content.js')->assertOk()->getContent();
    expect($js)->toContain('v hotovosti se nevrací')->toContain('never paid back in cash')
        ->not->toContain('vracíme ho na požádání')->not->toContain('refundable on request')
        ->not->toContain('přidáváme deset procent navíc')->not->toContain('get ten percent extra'); // a top-up bonus nobody gives

    $web = (string) $this->get('/surfaces/onhost-svc-web.js')->assertOk()->getContent();
    expect($web)->toContain('kredit připíšeme automaticky')->not->toContain('kredit vracíme automaticky');

    $staff = $this->staff();
    $admin = (string) $this->actingAs($staff)->get('/sprava')->assertOk()->getContent();
    expect($admin)->toContain('v hotovosti se nevrací')->toContain('never paid back in cash')
        ->not->toContain('refundable on request')->not->toContain('Refund unused credit')->not->toContain('vrací se na požádání')
        ->not->toContain('unused credit refunded')->not->toContain('we refund it on request');

    [$customer] = $this->customerWithOrganization();
    $panel = (string) $this->actingAs($customer)->get('/panel')->assertOk()->getContent();
    expect($panel)->toContain('nevyužité dny vracíme jako kredit')->not->toContain('refund unused days within ten days');
});

function g8Vps($org): Service
{
    $instance = pveLab();
    $vps = Service::query()->create([
        'organization_id' => $org->id, 'product_key' => 'vps', 'family' => 'cloud', 'name' => 'Compute 4', 'label' => 'g8-server', 'hostname' => 'vm-g8.cust.onhost.cz',
        'state' => ServiceStateMachine::ACTIVE, 'region_code' => 'cz1', 'provider_instance_id' => $instance->id,
        'desired_spec' => ['executor' => 'proxmox', 'family' => 'cloud'], 'entitlements' => ['vcpu' => 4, 'ram_mb' => 8192, 'nvme_gb' => 160], 'sla_class' => 'standard', 'activated_at' => now(), 'tags' => [],
    ]);
    ProviderBinding::query()->create(['service_id' => $vps->id, 'provider_instance_id' => $instance->id, 'remote_type' => 'qemu', 'remote_id' => '2042', 'remote_node' => 'prg1-n2', 'meta' => ['name' => 'vm-g8'], 'ownership' => ['managed_by' => 'onhost'], 'idempotency_key' => "g8:{$vps->id}", 'adapter_version' => '1.0.0']);

    return $vps;
}
