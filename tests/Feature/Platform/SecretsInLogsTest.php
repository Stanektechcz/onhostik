<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Identity\StepUp\StepUpService;
use Onhost\Domain\Provisioning\IntegrationHealthProbe;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Provisioning\Models\ProviderInstance;

/*
 * No secret in a log (Brain card H12). Every call to a panel is written to `provider_calls` and every change to the
 * audit trail; both are read by staff and kept for months. The values below are planted where a panel really puts
 * them — a session in a login answer, a generated password in a create answer, a key in a header, a customer's password
 * in a request — and then looked for, as plain text, in everything that was written.
 */

beforeEach(fn () => Http::preventStrayRequests());

/** Everything the platform persisted about what just happened, as one string. */
function everythingLogged(): string
{
    return json_encode([
        DB::table('provider_calls')->get(), DB::table('audit_events')->get(), DB::table('operations')->get(['error', 'result', 'context']),
        DB::table('operation_attempts')->get(), DB::table('outbox_messages')->get(),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
}

it('keeps the ISPConfig session, the remote password and a customer\'s new password out of every log', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $service = featureWebService($org, 'ispconfig'); // remote user `onhost`, remote password `secret` (tests/Pest.php)
    $site = ['domain_id' => 7, 'domain' => 'shop.cz', 'sys_groupid' => 3, 'system_user' => 'web7', 'system_group' => 'client3', 'document_root' => '/var/www/clients/client3/web7'];
    Http::fake(function (Request $request) use ($site) {
        $function = (string) parse_url($request->url(), PHP_URL_QUERY);
        $ok = fn ($response) => Http::response(['code' => 'ok', 'message' => '', 'response' => $response]);

        return match ($function) {
            'login' => $ok('SESSION-7f3a9c2e1b5d4f60a8c7e9d1'), // the panel answers with the session under a neutral key
            'sites_web_domain_get' => $ok(is_array($request->data()['primary_id'] ?? null) ? [$site] : $site),
            'monitor_jobqueue_count' => $ok(0),
            'sites_ftp_user_get' => $ok([]),
            'sites_ftp_user_add' => $ok(55),
            'client_get' => $ok(['client_id' => 3, 'username' => 'client3']),
            'client_login_get' => $ok('https://isp.test:8080/login/?otp=ONE-TIME-LOGIN-5f1e2d3c4b'), // a link straight into the customer's panel
            default => $ok([]),
        };
    });
    $this->actingAs($owner, 'sanctum');
    $id = $this->withHeader('Idempotency-Key', 'h12-ftp')->postJson("/v1/services/{$service->id}/actions", ['action' => 'ftp.create', 'params' => ['user' => 'deploy', 'password' => 'Customer-Chosen-Pass-91']])->assertStatus(202)->json('operation_id');
    expect(driveOperation(Operation::query()->findOrFail($id))->state)->toBe(Operation::SUCCEEDED);
    expect(DB::table('provider_calls')->where('provider', 'ispconfig')->count())->toBeGreaterThan(2);

    // staff open the customer's panel through an audited single sign-on: the link is for them, not for the log
    $staff = $this->staff('shared_hosting_admin');
    $this->actingAs($staff, 'sanctum');
    app(StepUpService::class)->grant($staff, 'totp', null, '127.0.0.1'); // the single sign-on asks for a fresh step-up (TASK-0030 WP-B)
    $sso = $this->getJson("/v1/staff/services/{$service->id}/panel-login");
    expect($sso->status())->toBe(200, (string) $sso->getContent());
    Http::assertSent(fn (Request $r) => str_contains($r->url(), 'client_login_get')); // the call whose answer is the link really happened
    expect(DB::table('provider_calls')->where('action', 'client_login_get')->value('response'))->toContain('withheld');

    $logged = everythingLogged();
    expect($logged)->not->toContain('ONE-TIME-LOGIN-5f1e2d3c4b');
    expect($logged)->not->toContain('SESSION-7f3a9c2e1b5d4f60a8c7e9d1')   // the session opens the whole remote API while it lives
        ->not->toContain('Customer-Chosen-Pass-91')                      // what the customer typed
        ->not->toContain('"password":"secret"');                          // the remote user's password
});

it('keeps the panel keys of aaPanel, Proxmox and the game panel out of every log', function () {
    [, $org] = $this->customerWithOrganization();
    featureWebService($org, 'aapanel');   // api key `aa-key-123`
    featureGameService($org);             // `ptla_APPLICATIONKEY1234567890`, `ptlc_CLIENTKEY1234567890`
    pveLab();                             // token secret `deadbeef-0000`
    Http::fake(function (Request $request) {
        $path = (string) parse_url($request->url(), PHP_URL_PATH);

        return match (true) {
            str_ends_with($path, '/api2/json/version') => Http::response(['data' => ['version' => '8.2.4', 'release' => '8.2']]),
            str_ends_with($path, '/api2/json/cluster/status') => Http::response(['data' => [['type' => 'cluster', 'quorate' => 1]]]),
            $path === '/api/application/nodes' => Http::response(['object' => 'list', 'data' => [], 'meta' => ['pagination' => ['total_pages' => 1, 'current_page' => 1]]]),
            default => Http::response(['status' => true, 'version' => '7.0.11', 'load' => 0.4]),
        };
    });
    $probe = app(IntegrationHealthProbe::class);
    foreach (['aapanel-managed01', 'pterodactyl-games01', 'proxmox-cz1'] as $key) {
        $probe->probeInstance(ProviderInstance::query()->where('key', $key)->firstOrFail());
    }
    expect(DB::table('provider_calls')->count())->toBeGreaterThan(2);

    $logged = everythingLogged();
    expect($logged)->not->toContain('aa-key-123')->not->toContain('APPLICATIONKEY1234567890')->not->toContain('CLIENTKEY1234567890')->not->toContain('deadbeef-0000');
    // aaPanel signs every request with md5(time + md5(key)): the token itself is a credential for that second and must not be kept either
    expect($logged)->not->toMatch('/"request_token":"[0-9a-f]{32}"/');
});
