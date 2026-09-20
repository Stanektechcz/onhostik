<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Onhost\Domain\Identity\Authorization\Models\PolicyBinding;
use Onhost\Domain\Organizations\Models\OrganizationMembership;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Provisioning\OperationSecrets;
use Onhost\Providers\AaPanel\AaPanelWebProvider;
use Onhost\Providers\Shell\ScriptedShell;

/*
 * What an operation must forget (OperationSecrets). An operation carried the passwords it was given — of a database, an
 * FTP account, a mailbox, the customer's mailbox at another provider — and the WordPress administrator password it
 * generated, for good and in plain JSON; the generated one was in the answer to anybody who may list the operations
 * of the service, a read-only viewer included.
 */

afterEach(fn () => AaPanelWebProvider::$shellFactory = null);

/** An aaPanel that creates databases and lets WP-CLI install; records what it was sent. */
function secretsPanel(array &$sent): void
{
    AaPanelWebProvider::$shellFactory = fn () => new ScriptedShell([
        '/test -e .*wp-config\.php/' => [1, ''],
        '/core download/' => [0, 'Success: WordPress downloaded.'],
        '/config create/' => [0, "Success: Generated 'wp-config.php' file."],
        '/core install/' => [0, 'Success: WordPress installed successfully.'],
    ]);
    $databases = [];
    Http::fake(function ($request) use (&$databases, &$sent) {
        if (! str_starts_with($request->url(), AAP)) {
            return null;
        }
        $q = (string) parse_url($request->url(), PHP_URL_QUERY);
        $body = $request->data();
        if (str_contains($q, 'AddDatabase')) {
            $sent[] = ['AddDatabase', (string) ($body['password'] ?? '')];
            if (str_contains((string) $body['name'], 'broken')) {
                return Http::response(['status' => false, 'msg' => 'The database server refused the request']);
            }
            $databases[] = ['id' => 90 + count($databases), 'name' => (string) $body['name'], 'username' => (string) $body['db_user'], 'codeing' => 'utf8mb4'];

            return Http::response(['status' => true, 'msg' => 'ok']);
        }

        return match (true) {
            str_contains($q, 'table=databases') => Http::response(['data' => array_values(array_filter($databases, fn ($d) => ($body['search'] ?? '') === '' || str_contains($d['name'], (string) $body['search']))), 'page' => '']),
            str_contains($q, 'table=sites') => Http::response(['data' => [['id' => 41, 'name' => 'shop.cz', 'path' => '/www/wwwroot/shop.cz', 'status' => '1']], 'page' => '']),
            str_contains($q, 'GetSiteRunPath') => Http::response(['runPath' => '/']),
            str_contains($q, 'GetSitePHPVersion') => Http::response(['phpversion' => '83']),
            str_contains($q, 'GetFileBody') => Http::response(['status' => false, 'msg' => 'file does not exist']),
            default => Http::response(['status' => true, 'msg' => 'ok']),
        };
    });
}

it('forgets the password an action was given the moment the action is done, keeps it for a retry, and lets that run out', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $service = featureWebService($org, 'aapanel');
    $sent = [];
    secretsPanel($sent);
    $this->actingAs($owner, 'sanctum');
    $act = fn (array $params, string $key) => $this->postJson("/v1/services/{$service->id}/actions", ['action' => 'database.create', 'params' => $params], ['Idempotency-Key' => $key])->assertAccepted()->json('operation_id');

    $done = driveOperation(Operation::query()->findOrFail($act(['name' => 'shop', 'password' => 'Velmi-Tajne-Heslo-2026'], 'os-1')));
    expect($done->state)->toBe(Operation::SUCCEEDED, json_encode($done->error));
    expect(collect($sent)->last()[1])->toBe('Velmi-Tajne-Heslo-2026'); // the panel got the real one
    expect($done->desired['password'])->toBe(OperationSecrets::GONE)->and($done->desired['name'])->not->toBe(OperationSecrets::GONE)->and($done->secrets_scrubbed_at)->not->toBeNull();
    expect(json_encode([$done->desired, $done->context, $done->result]))->not->toContain('Velmi-Tajne-Heslo-2026');

    // a failed run is retried with what it was given — for a week, not for ever
    $failed = driveOperation(Operation::query()->findOrFail($act(['name' => 'broken', 'password' => 'Velmi-Silne-Heslo-2026'], 'os-2')));
    expect($failed->state)->toBe(Operation::FAILED)->and($failed->desired['password'])->toBe('Velmi-Silne-Heslo-2026');
    expect(OperationSecrets::sweep()['scrubbed'])->toBe(0);
    $this->travel(8)->days();
    expect(OperationSecrets::sweep()['scrubbed'])->toBe(1)->and($failed->fresh()->desired['password'])->toBe(OperationSecrets::GONE)->and($failed->fresh()->secrets_scrubbed_at)->not->toBeNull();
});

it('shows a generated administrator password once — to somebody who manages the service, for half an hour — never to a viewer, to staff, or afterwards', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $service = featureWebService($org, 'aapanel');
    $sent = [];
    secretsPanel($sent);
    $viewer = $this->customer(['email' => 'jen-ctu@example.cz']);
    PolicyBinding::query()->create(['principal_type' => 'user', 'principal_id' => $viewer->id, 'role_key' => 'viewer', 'scope_type' => 'organization', 'scope_id' => $org->id, 'organization_id' => $org->id]);
    OrganizationMembership::query()->create(['organization_id' => $org->id, 'user_id' => $viewer->id, 'state' => 'active', 'role_key' => 'viewer', 'joined_at' => now()]);

    $this->actingAs($owner, 'sanctum');
    $id = $this->postJson("/v1/services/{$service->id}/actions", ['action' => 'wp.install', 'params' => ['title' => 'Shop', 'admin_email' => 'owner@shop.cz']], ['Idempotency-Key' => 'os-wp'])->assertAccepted()->json('operation_id');
    $operation = driveOperation(Operation::query()->findOrFail($id));
    expect($operation->state)->toBe(Operation::SUCCEEDED, json_encode($operation->error));
    $generated = (string) $operation->result['admin_password'];
    expect(strlen($generated))->toBeGreaterThanOrEqual(16)->and($operation->desired['admin_password'])->toBe(OperationSecrets::GONE); // the request forgot it; the result holds it for the reveal

    $row = fn () => collect($this->getJson("/v1/services/{$service->id}/operations")->assertOk()->json('data'))->firstWhere('id', $id);
    expect($row()['result']['admin_password'])->toBe($generated)->and($row()['result']['reveal_until'])->not->toBeNull();

    // a read-only member lists the operations — and used to read the administrator password of the site there
    $this->actingAs($viewer, 'sanctum');
    expect($row()['result'])->toHaveKey('admin_user')->not->toHaveKey('admin_password');
    // staff work on the run, not on the customer's passwords
    $this->actingAs($this->staff('support_l3'), 'sanctum');
    expect(json_encode($this->getJson("/v1/staff/provisioning/jobs/{$id}")->assertOk()->json()))->not->toContain($generated);

    // half an hour later it is gone from the answer, and the sweep takes it off the row
    $this->travel(31)->minutes();
    $this->actingAs($owner, 'sanctum');
    expect($row()['result'])->not->toHaveKey('admin_password');
    expect(OperationSecrets::sweep()['scrubbed'])->toBe(1);
    expect(json_encode(Operation::query()->findOrFail($id)->only(['desired', 'context', 'result'])))->not->toContain($generated);
});

it('knows a private key from the name of a variable, and a secret variable by its name', function () {
    $pem = "-----BEGIN PRIVATE KEY-----\n".str_repeat('A', 80)."\n-----END PRIVATE KEY-----";
    expect(OperationSecrets::forget(['action' => 'ssl.upload', 'cert' => 'CERT', 'key' => $pem]))->toBe(['action' => 'ssl.upload', 'cert' => 'CERT', 'key' => OperationSecrets::GONE]);
    expect(OperationSecrets::forget(['action' => 'variable.set', 'key' => 'MOTD', 'value' => 'Vítejte']))->toBe(['action' => 'variable.set', 'key' => 'MOTD', 'value' => 'Vítejte']);
    expect(OperationSecrets::forget(['action' => 'variable.set', 'key' => 'RCON_PASSWORD', 'value' => 'tajne']))->toBe(['action' => 'variable.set', 'key' => 'RCON_PASSWORD', 'value' => OperationSecrets::GONE]);
    expect(OperationSecrets::forget(['db' => ['user' => 'u', 'password' => 'p'], 'admin_password' => 'x', 'ssh_key' => 'ssh-ed25519 AAAA public'], ['admin_password']))
        ->toBe(['db' => ['user' => 'u', 'password' => OperationSecrets::GONE], 'admin_password' => 'x', 'ssh_key' => 'ssh-ed25519 AAAA public']);
});
