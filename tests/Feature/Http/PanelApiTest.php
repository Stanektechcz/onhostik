<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Database\Seeders\DnsTemplateSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Identity\StepUp\StepUpService;
use Onhost\Domain\Notifications\Models\MailOutbox;
use Onhost\Domain\Notifications\Models\Notification;
use Onhost\Domain\Notifications\Models\NotificationTemplate;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Organizations\Models\OrganizationInvitation;
use Onhost\Domain\Organizations\OrganizationService;
use Onhost\Domain\Provisioning\Models\Node;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Provisioning\Models\ProviderBinding;
use Onhost\Domain\Provisioning\OperationSecrets;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Domain\Services\ServiceFeatures;
use Onhost\Domain\WalletLedger\WalletService;
use Onhost\Platform\Audit\AuditEvent;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Money\Money;
use Onhost\Platform\Outbox\OutboxPublisher;

function panelVps(Organization $org, string $state = ServiceStateMachine::ACTIVE): Service
{
    $instance = pveLab();
    $node = Node::query()->where('name', 'prg1-n2')->firstOrFail();
    $service = Service::query()->create([
        'organization_id' => $org->id, 'product_key' => 'vps', 'family' => 'cloud', 'name' => 'Compute 4', 'hostname' => 'vm-panel.cust.onhost.cz', 'state' => $state, 'region_code' => 'cz1', 'provider_instance_id' => $instance->id, 'node_id' => $node->id,
        'desired_spec' => ['executor' => 'proxmox', 'family' => 'cloud'], 'entitlements' => ['vcpu' => 4, 'ram_mb' => 8192, 'nvme_gb' => 160], 'sla_class' => 'standard', 'activated_at' => now(), 'tags' => ['access' => ['ipv4' => '192.0.2.2']],
    ]);
    ProviderBinding::query()->create(['service_id' => $service->id, 'provider_instance_id' => $instance->id, 'remote_type' => 'qemu', 'remote_id' => '1042', 'remote_node' => 'prg1-n2', 'meta' => [], 'ownership' => ['managed_by' => 'onhost'], 'idempotency_key' => "provision:{$service->id}:qemu", 'adapter_version' => '1.0.0']);

    return $service;
}

beforeEach(function () {
    $this->seed([CatalogSeeder::class, TaxRuleSeeder::class, LegalEntitySeeder::class, DnsTemplateSeeder::class]);
    Http::preventStrayRequests();
});

it('lists services, runs a power action through the command bus and issues a console token', function () {
    Http::fake([
        PVE.'/nodes/prg1-n2/qemu/1042/status/current' => Http::response(['data' => ['status' => 'running', 'uptime' => 100]]),
        PVE.'/nodes/prg1-n2/qemu/1042/config' => Http::response(pveVmConfig()),
        PVE.'/nodes/prg1-n2/qemu/1042/status/reboot' => Http::response(['data' => 'UPID:prg1-n2:000A1B2F:0004E1F8:66F0AA14:qmreboot:1042:onhost@pve!cp:']),
        PVE.'/nodes/prg1-n2/tasks/*/status' => Http::response(['data' => ['status' => 'stopped', 'exitstatus' => 'OK']]),
        PVE.'/nodes/prg1-n2/qemu/1042/vncproxy' => Http::response(['data' => ['port' => 5900, 'ticket' => 'PVEVNC:ticket', 'user' => 'onhost@pve!cp']]),
    ]);
    [$user, $org] = $this->customerWithOrganization();
    $service = panelVps($org);
    $this->actingAs($user, 'sanctum');

    $list = $this->getJson('/v1/services?limit=10')->assertOk()->assertHeader('X-Total-Count', '1');
    expect($list->json('data.0.id'))->toBe($service->id)->and($list->json('data.0.ui'))->toBe('aktivni')->and($list->json('data.0.access.ipv4'))->toBe('192.0.2.2');
    $this->getJson('/v1/services/'.$service->id)->assertOk()->assertJsonPath('data.bindings.0.type', 'qemu');

    $action = $this->postJson("/v1/services/{$service->id}/power", ['power_action' => 'reboot'])->assertStatus(202);
    expect($action->json('kind'))->toBe('service.action');
    driveOperation(Operation::query()->findOrFail($action->json('operation_id')));
    expect(Operation::query()->findOrFail($action->json('operation_id'))->state)->toBe(Operation::SUCCEEDED);
    expect(AuditEvent::query()->where('action', 'service.power')->where('result', 'succeeded')->exists())->toBeTrue();
    $this->postJson("/v1/services/{$service->id}/power", ['power_action' => 'explode'])->assertUnprocessable()->assertJsonPath('error', 'power_action_invalid');

    $console = $this->postJson("/v1/services/{$service->id}/console-token")->assertOk();
    expect($console->json('kind'))->toBe('novnc')->and($console->json('token'))->toStartWith('con_')->and($console->json('url'))->toContain('/console/ws/');
    expect(json_encode($console->json()))->not->toContain('PVEVNC');

    [$other] = $this->customerWithOrganization();
    $this->actingAs($other, 'sanctum');
    $this->getJson('/v1/services/'.$service->id)->assertForbidden();
    $this->getJson('/v1/services')->assertOk()->assertHeader('X-Total-Count', '0');
});

it('terminating needs a fresh step-up and is refused by the bus otherwise', function () {
    Http::fake();
    [$user, $org] = $this->customerWithOrganization();
    $service = panelVps($org);
    $this->actingAs($user, 'sanctum');
    $this->postJson("/v1/services/{$service->id}/terminate", ['reason' => 'no longer needed'])->assertForbidden()->assertJsonPath('error', 'step_up_required')->assertJsonPath('requirement', 'step_up');
    expect(AuditEvent::query()->where('action', 'service.terminate')->where('result', 'denied')->exists())->toBeTrue();
    expect(Operation::query()->where('service_id', $service->id)->exists())->toBeFalse();
    Http::assertNothingSent();
});

it('manages a DNS zone in two phases: stage, preview, commit, versions, rollback', function () {
    pdnsLab();
    pdnsZoneFake('panel.cz');
    [$user, $org] = $this->customerWithOrganization();
    $this->actingAs($user, 'sanctum');

    $zone = $this->postJson('/v1/dns/zones', ['name' => 'panel.cz', 'template' => 'web_basic', 'vars' => ['ipv4' => '192.0.2.10']])->assertCreated();
    $zoneId = $zone->json('zone_id');
    $this->getJson("/v1/dns/zones/{$zoneId}")->assertOk()->assertJsonPath('data.version', 1)->assertJsonPath('data.pending_changes', 0);
    $this->postJson("/v1/dns/zones/{$zoneId}/changes", ['change' => 'add', 'record' => ['name' => 'api', 'type' => 'A', 'content' => '300.1.1.1']])->assertUnprocessable()->assertJsonPath('error', 'dns_invalid_ipv4');
    $this->postJson("/v1/dns/zones/{$zoneId}/changes", ['change' => 'add', 'record' => ['name' => 'api', 'type' => 'A', 'content' => '192.0.2.11', 'ttl' => 300]])->assertCreated();
    $this->getJson("/v1/dns/zones/{$zoneId}/preview")->assertOk()->assertJsonCount(1, 'data.changes');
    $this->postJson("/v1/dns/zones/{$zoneId}/commit", ['reason' => 'api host'])->assertOk()->assertJsonPath('version', 2);
    $this->getJson("/v1/dns/zones/{$zoneId}/versions")->assertOk()->assertHeader('X-Total-Count', '2');
    $this->postJson("/v1/dns/zones/{$zoneId}/rollback", ['version' => 1])->assertOk()->assertJsonPath('version', 3);
    $this->getJson('/v1/domains/panel.cz/zone')->assertOk()->assertJsonPath('data.version', 3);
    $export = $this->get("/v1/dns/zones/{$zoneId}/export")->assertOk();
    expect($export->headers->get('Content-Type'))->toContain('text/plain')->and($export->getContent())->toContain('$ORIGIN panel.cz.');
    expect(collect(Http::recorded())->filter(fn (array $p) => $p[0]->method() === 'PATCH'))->toHaveCount(3);
});

it('walks the shop flow: cart → quote → wallet order → invoice list and PDF, all under the organization scope', function () {
    [$user, $org] = $this->customerWithOrganization();
    app(WalletService::class)->topup($org, Money::decimal('5000', 'CZK'), 'card', 'seed', $this->contextFor($user, $org), bankProvider: 'comgate');
    $this->actingAs($user, 'sanctum');

    $this->putJson('/v1/cart', ['items' => [['product_key' => 'web-hosting', 'plan_key' => 'start', 'config' => ['fqdn' => 'shop.cz']]], 'commit_months' => 1, 'currency' => 'CZK'])->assertOk()->assertJsonCount(1, 'data.items');
    $this->postJson('/v1/cart/promo', ['code' => 'NOPE'])->assertUnprocessable()->assertJsonPath('errors.promo.0', 'Slevový kód neplatí.');
    $quote = $this->postJson('/v1/cart/quote')->assertOk();
    expect($quote->json('data.total'))->toBeGreaterThan(0)->and($quote->json('data.required_documents'))->toContain('terms');

    $consents = ['terms' => ['version' => '2026-09'], 'privacy' => [], 'dpa' => [], 'withdrawal_waiver' => [], 'sla' => []];
    $order = $this->postJson('/v1/orders', ['quote_id' => $quote->json('data.quote_id'), 'consents' => $consents, 'payment' => ['mode' => 'wallet']])->assertCreated();
    expect($order->json('state'))->toBe('PAID')->and($order->json('number'))->toStartWith('OH-');
    $this->getJson('/v1/orders')->assertOk()->assertHeader('X-Total-Count', '1')->assertJsonPath('data.0.number', $order->json('number'));
    $this->getJson('/v1/orders/'.$order->json('order_id'))->assertOk()->assertJsonCount(1, 'data.items');

    $invoices = $this->getJson('/v1/invoices')->assertOk()->assertHeader('X-Total-Count', '1'); // the order statement (receipts are issued by verified gateway payments)
    $statement = collect($invoices->json('data'))->firstWhere('type', 'statement');
    expect($statement['state'])->toBe('PAID')->and($statement['number'])->toStartWith('VY-');
    $pdf = $this->get('/v1/invoices/'.$statement['id'].'/pdf')->assertOk();
    expect($pdf->headers->get('Content-Type'))->toBe('application/pdf')->and(substr($pdf->getContent(), 0, 4))->toBe('%PDF');
    $this->get('/v1/invoices/'.$statement['id'].'/ubl')->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8');

    $wallet = $this->getJson('/v1/wallet')->assertOk();
    expect($wallet->json('data.spendable.minor'))->toBe(500000 - $statement['total']['minor']);
});

it('lists pending invitations on the organization and lets the owner cancel one after step-up', function () {
    [$user, $org] = $this->customerWithOrganization();
    $this->actingAs($user, 'sanctum');
    $this->postJson("/v1/organizations/{$org->id}/invitations", ['email' => 'ucetni@firma.cz', 'role' => 'billing_admin'])->assertForbidden()->assertJsonPath('error', 'step_up_required');
    app(StepUpService::class)->grant($user, 'password', null, '127.0.0.1');
    $this->postJson("/v1/organizations/{$org->id}/invitations", ['email' => 'ucetni@firma.cz', 'role' => 'billing_admin'])->assertCreated();
    $shown = $this->getJson("/v1/organizations/{$org->id}")->assertOk();
    expect($shown->json('data.invitations'))->toHaveCount(1)->and($shown->json('data.invitations.0.email'))->toBe('ucetni@firma.cz')->and($shown->json('data.invitations.0.role'))->toBe('billing_admin')->and($shown->json('data'))->not->toHaveKey('token');
    $id = $shown->json('data.invitations.0.id');

    $this->deleteJson("/v1/organizations/{$org->id}/invitations/{$id}")->assertOk()->assertJsonPath('cancelled', true);
    $this->getJson("/v1/organizations/{$org->id}")->assertOk()->assertJsonCount(0, 'data.invitations');
    $this->deleteJson("/v1/organizations/{$org->id}/invitations/{$id}")->assertOk(); // repeating the cancel replays the command result
    $this->deleteJson("/v1/organizations/{$org->id}/invitations/inv_missing")->assertNotFound();
    $invitation = OrganizationInvitation::query()->findOrFail($id);
    expect($invitation->isUsable())->toBeFalse();
    expect(fn () => app(OrganizationService::class)->cancelInvitation($org, $id, $this->contextFor($user, $org)))->toThrow(DomainError::class, 'already accepted or has expired');
});

it('changes the password from the panel: wrong current password refused, other sessions and remembered devices signed out', function () {
    [$user] = $this->customerWithOrganization(['password' => 'Stare-Heslo-2026a', 'remember_token' => 'remember-me-elsewhere']);
    $this->actingAs($user, 'sanctum');
    $this->postJson('/v1/me/password', ['current_password' => 'spatne', 'password' => 'Nove-Heslo-2026b'])->assertUnprocessable()->assertJsonPath('error', 'password_mismatch');
    $this->postJson('/v1/me/password', ['current_password' => 'Stare-Heslo-2026a', 'password' => 'kratke'])->assertUnprocessable();

    config()->set('session.driver', 'database');
    foreach (['sess-phone', 'sess-office'] as $id) {
        DB::table('sessions')->insert(['id' => $id, 'user_id' => $user->id, 'ip_address' => '10.0.0.9', 'user_agent' => 'other device', 'payload' => '', 'last_activity' => time()]);
    }
    app(StepUpService::class)->grant($user, 'password', null, '127.0.0.1');
    $this->postJson('/v1/me/password', ['current_password' => 'Stare-Heslo-2026a', 'password' => 'Nove-Heslo-2026b'])->assertOk()->assertJsonPath('data.changed', true);
    $fresh = $user->fresh();
    expect(Hash::check('Nove-Heslo-2026b', (string) $fresh->password))->toBeTrue()
        ->and($fresh->remember_token)->not->toBe('remember-me-elsewhere')
        ->and(DB::table('sessions')->where('user_id', $user->id)->count())->toBe(0)
        ->and(app(StepUpService::class)->activeGrant($fresh, null))->toBeNull();
    $this->getJson('/v1/me')->assertOk(); // the session that changed the password is still signed in
});

it('tells the truth about API tokens after a password change: they stay valid, and the mail says so', function () {
    [$user] = $this->customerWithOrganization(['password' => 'Stare-Heslo-2026a']);
    $user->createToken('CI deploy', ['services:read']);
    $user->createToken('starý, zrušený', ['services:read'])->accessToken->forceFill(['revoked_at' => now()])->save();
    $this->actingAs($user, 'sanctum');

    $this->postJson('/v1/me/password', ['current_password' => 'Stare-Heslo-2026a', 'password' => 'Nove-Heslo-2026b'])->assertOk();
    app(OutboxPublisher::class)->relayPending();

    // a CHANGE keeps the tokens (a reset revokes them) — and the mail used to say "all other sessions and API tokens were signed out":
    // somebody who changed the password because something leaked believed the tokens were dead
    expect($user->tokens()->whereNull('revoked_at')->count())->toBe(1);
    $mail = MailOutbox::query()->where('to', $user->email)->where('template_key', 'like', 'security-password%')->sole();
    expect($mail->template_key)->toBe('security-password-kept')->and($mail->vars['pocet'])->toBe('1');
    $template = NotificationTemplate::query()->where('key', 'security-password-kept')->where('locale', 'cs')->firstOrFail();
    expect($template->body)->toContain('API tokeny zůstávají platné')->not->toContain('API tokeny byly odhlášeny');
    expect(Notification::query()->where('user_id', $user->id)->where('title', 'Heslo bylo změněno')->value('body'))->toContain('API tokeny zůstávají platné: 1');
});

it('issues scoped API tokens after step-up and enforces the scopes on bearer requests', function () {
    Http::fake();
    [$user, $org] = $this->customerWithOrganization();
    $service = panelVps($org);
    $this->actingAs($user, 'sanctum');
    // the panel repeats the very same request (same Idempotency-Key) once the customer has stepped up: a denial is never replayed
    $this->postJson('/v1/tokens', ['name' => 'CI', 'scopes' => ['services:read']], ['Idempotency-Key' => 'ci-token'])->assertForbidden()->assertJsonPath('error', 'step_up_required')->assertHeaderMissing('Idempotent-Replayed');
    app(StepUpService::class)->grant($user, 'totp', null, '127.0.0.1');
    $created = $this->postJson('/v1/tokens', ['name' => 'CI', 'scopes' => ['services:read']], ['Idempotency-Key' => 'ci-token'])->assertCreated()->assertHeaderMissing('Idempotent-Replayed');
    $this->postJson('/v1/tokens', ['name' => 'CI', 'scopes' => ['services:read']], ['Idempotency-Key' => 'ci-token'])->assertCreated()->assertHeader('Idempotent-Replayed', 'true');
    $plain = $created->json('token');
    expect($plain)->toContain('|onh_live_')->and($created->json('scopes'))->toBe(['services:read']);
    $this->getJson('/v1/tokens')->assertOk()->assertJsonPath('data.0.name', 'CI');

    $this->app['auth']->forgetGuards();
    $this->withHeader('Authorization', "Bearer {$plain}")->getJson('/v1/services')->assertOk()->assertHeader('X-Total-Count', '1');
    $this->withHeader('Authorization', "Bearer {$plain}")->postJson("/v1/services/{$service->id}/power", ['power_action' => 'start'])->assertForbidden()->assertJsonPath('message', 'The API token lacks the services:power scope.');
    $this->withHeader('Authorization', "Bearer {$plain}")->deleteJson('/v1/tokens/'.$created->json('id'))->assertForbidden(); // tokens cannot manage tokens

    $this->actingAs($user->fresh(), 'sanctum');
    $this->deleteJson('/v1/tokens/'.$created->json('id'))->assertOk()->assertJsonPath('revoked', true);
    $this->app['auth']->forgetGuards();
    $this->withHeader('Authorization', "Bearer {$plain}")->getJson('/v1/services')->assertUnauthorized();
});

/*
 * A token reaches only what its scopes name. The scope check lived inside the permission check, so an endpoint that
 * asks for no permission was open to any token: `invoices:read` could change the profile, enrol TOTP on an account
 * without one, read the recovery codes — and then step up, clearing the gate for whatever its scopes did cover.
 */
it('keeps a bearer token out of the account, the step-up and every route family its scopes do not name', function () {
    Http::fake();
    [$user, $org] = $this->customerWithOrganization(['password' => 'Correct-Horse-Battery-9']);
    $service = panelVps($org);
    $this->actingAs($user, 'sanctum');
    app(StepUpService::class)->grant($user, 'totp', null, '127.0.0.1');
    $plain = $this->postJson('/v1/tokens', ['name' => 'účetní export', 'scopes' => ['invoices:read']], ['Idempotency-Key' => 'tok-invoices'])->assertCreated()->json('token');
    $this->app['auth']->forgetGuards();
    $bearer = ['Authorization' => "Bearer {$plain}"];

    $this->withHeaders($bearer)->getJson('/v1/invoices')->assertOk();   // what it is for
    $this->withHeaders($bearer)->getJson('/v1/me')->assertOk();         // and who it is
    foreach ([
        ['PATCH', '/v1/me', ['name' => 'Převzatý účet']], ['POST', '/v1/me/totp/enroll', []], ['POST', '/v1/me/totp/confirm', ['code' => '123456']], ['POST', '/v1/me/password', ['current_password' => 'x', 'password' => 'y']],
        ['POST', '/v1/auth/step-up', ['method' => 'password', 'code' => 'Correct-Horse-Battery-9']], ['POST', '/v1/notifications/read', ['ids' => []]], ['GET', '/v1/organizations', []],
        ['GET', '/v1/services', []], ['POST', "/v1/services/{$service->id}/power", ['power_action' => 'stop']], ['GET', '/v1/wallet', []], ['POST', '/v1/webhooks', ['url' => 'https://example.com/h']], ['GET', '/v1/orders', []],
    ] as [$method, $uri, $body]) {
        $response = $this->withHeaders($bearer)->json($method, $uri, $body);
        expect($response->status())->toBe(403, "{$method} {$uri} answered {$response->status()}");
    }
    expect($user->fresh()->name)->not->toBe('Převzatý účet')->and($user->fresh()->hasTotp())->toBeFalse();
});

it('lets the owner of a virtual server set new SSH keys and a new password — after a fresh step-up, and nothing else of its cloud-init moves', function () {
    $sent = [];
    Http::fake([
        PVE.'/nodes/prg1-n2/qemu/1042/config' => function ($request) use (&$sent) {
            if ($request->method() === 'PUT') {
                $sent[] = $request->data();
            }

            return Http::response($request->method() === 'PUT' ? ['data' => null] : pveVmConfig());
        },
        PVE.'/nodes/prg1-n2/qemu/1042/cloudinit' => Http::response(['data' => null]),
        PVE.'/nodes/prg1-n2/qemu/1042/status/current' => Http::response(['data' => ['status' => 'running', 'uptime' => 100]]),
    ]);
    [$user, $org] = $this->customerWithOrganization();
    $service = panelVps($org);
    $this->actingAs($user, 'sanctum');
    $key = 'ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIJ0m3Yq0v6l3S5q1v1mXbq1Qd0m1o9yq1x1w2n3b4c5d jana@notebook';

    // a server is delivered with the keys of its order and there was no way to change them: whoever lost the key was locked out
    expect(app(ServiceFeatures::class)->actions($service))->toContain('access.reset');
    $this->postJson("/v1/services/{$service->id}/actions", ['action' => 'access.reset', 'params' => ['ssh_keys' => [$key]]])->assertForbidden()->assertJsonPath('error', 'step_up_required'); // new keys open the server
    app(StepUpService::class)->grant($user, 'password', null, '127.0.0.1');
    $this->postJson("/v1/services/{$service->id}/actions", ['action' => 'access.reset', 'params' => []])->assertUnprocessable()->assertJsonPath('error', 'action_param_invalid');
    $this->postJson("/v1/services/{$service->id}/actions", ['action' => 'access.reset', 'params' => ['ssh_keys' => ['ssh-rsa kratky']]])->assertUnprocessable();
    $this->postJson("/v1/services/{$service->id}/actions", ['action' => 'access.reset', 'params' => ['password' => 'kratke']])->assertUnprocessable();

    $accepted = $this->postJson("/v1/services/{$service->id}/actions", ['action' => 'access.reset', 'params' => ['ssh_keys' => [$key], 'password' => 'Dlouhe-Heslo-2026!']])->assertStatus(202);
    $operation = driveOperation(Operation::query()->findOrFail($accepted->json('operation_id')));

    expect($operation->state)->toBe(Operation::SUCCEEDED)->and($sent)->toHaveCount(1);
    // only what was given: the address, the gateway and the user of the server stay as they are
    expect(array_keys($sent[0]))->toEqualCanonicalizing(['cipassword', 'sshkeys'])->and($sent[0]['cipassword'])->toBe('Dlouhe-Heslo-2026!')->and(rawurldecode((string) $sent[0]['sshkeys']))->toBe($key);
    Http::assertSent(fn ($r) => $r->method() === 'PUT' && str_ends_with($r->url(), '/qemu/1042/cloudinit')); // the panel makes the cloud-init drive again
    expect($service->refresh()->desired_spec['ssh_keys'])->toBe([$key]);
    // the row forgets the password it carried
    expect($operation->refresh()->secrets_scrubbed_at)->not->toBeNull()->and(json_encode($operation->desired))->not->toContain('Dlouhe-Heslo-2026!')->toContain(OperationSecrets::GONE);

    // a managed database has no root for its customer
    $service->forceFill(['family' => 'data'])->save();
    app(ServiceFeatures::class)->forget($service);
    expect(app(ServiceFeatures::class)->actions($service->refresh()))->not->toContain('access.reset');
});
