<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Services\Web\DatabaseCredentials;
use Onhost\Providers\AaPanel\AaPanelWebProvider;
use Onhost\Providers\Contracts\Naming;
use Onhost\Providers\Shell\ScriptedShell;

/*
 * A clean WordPress through WP-CLI on an aaPanel-backed site: the install creates the site's own database within the
 * plan and remembers its credentials, or uses a database the customer made in the panel — whose password the platform
 * never saw, so the customer supplies it once and the toolkit keeps it from then on. The node is a scripted shell.
 */

beforeEach(fn () => Http::preventStrayRequests());
afterEach(fn () => AaPanelWebProvider::$shellFactory = null);

it('installs WordPress with its own database, or into a panel-made database once the customer supplies its password', function () {
    [$user, $org] = $this->customerWithOrganization();
    $service = featureWebService($org, 'aapanel');
    $shell = new ScriptedShell([
        '/test -e .*wp-config\.php/' => [1, ''], // not installed yet
        '/core download/' => [0, 'Success: WordPress downloaded.'],
        '/config create/' => [0, "PHP Deprecated:  Case statements followed by a semicolon (;) are deprecated in phar:///www/server/onhost/wp-cli.phar\nSuccess: Generated 'wp-config.php' file."],
        '/core install/' => [0, 'Success: WordPress installed successfully.'],
    ]);
    AaPanelWebProvider::$shellFactory = fn () => $shell;
    $databases = [];
    Http::fake(function ($request) use (&$databases) {
        $q = (string) parse_url($request->url(), PHP_URL_QUERY);
        $body = $request->data();
        if (str_contains($q, 'AddDatabase')) {
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
    $this->actingAs($user, 'sanctum');
    $base = "/v1/services/{$service->id}";
    $action = function (string $action, array $params, string $key) use ($base) {
        return $this->postJson("{$base}/actions", ['action' => $action, 'params' => $params], ['Idempotency-Key' => $key]);
    };
    $configCreate = fn () => (string) (collect($shell->calls)->last(fn ($c) => str_contains($c['command'], 'config create'))['command'] ?? '');

    // validation at the API: title, password strength, database password shape
    $action('wp.install', ['admin_email' => 'a@b.cz'], 'wp-0')->assertUnprocessable()->assertJsonPath('error', 'action_param_invalid');
    $action('wp.install', ['title' => 'Shop', 'admin_email' => 'a@b.cz', 'admin_password' => 'short'], 'wp-1')->assertUnprocessable()->assertJsonPath('error', 'action_param_invalid');
    $action('wp.install', ['title' => 'Shop', 'admin_email' => 'a@b.cz', 'database_id' => '7', 'database_password' => 'a b'], 'wp-2')->assertUnprocessable()->assertJsonPath('error', 'action_param_invalid');

    // 1. no database yet: the toolkit creates one within the plan, remembers its credentials and installs
    $response = $action('wp.install', ['title' => 'Shop', 'admin_email' => 'Owner@Shop.cz', 'admin_password' => 'Toolkit-Admin-2026x', 'locale' => 'cs_CZ'], 'wp-3')->assertAccepted();
    $operation = driveOperation(Operation::query()->findOrFail($response->json('operation_id')));
    expect($operation->state)->toBe(Operation::SUCCEEDED, json_encode($operation->error));
    $row = collect($this->getJson("{$base}/operations")->assertOk()->json('data'))->firstWhere('id', $operation->id);
    expect($row['result']['admin_url'])->toBe('https://shop.cz/wp-admin/')->and($row['result']['admin_user'])->toBe('admin')->and($row['result']['admin_password'])->toBe('Toolkit-Admin-2026x')->and($row['result']['installed'])->toBeTrue();
    $dbName = Naming::scoped($service->id, 'wp', 32);
    expect($databases)->toHaveCount(1)->and($databases[0]['name'])->toBe($dbName)
        ->and($configCreate())->toContain("--dbname='{$dbName}'")->toContain('--dbuser=\''.Naming::scoped($service->id, 'wp', 16).'\'')->toContain("--locale='cs_CZ'")
        ->and(app(DatabaseCredentials::class)->read($service, '90')['password'] ?? '')->not->toBeEmpty();
    $install = (string) collect($shell->calls)->last(fn ($c) => str_contains($c['command'], 'core install'))['command'];
    expect($install)->toContain("--url='https://shop.cz'")->toContain("--admin_email='owner@shop.cz'")->toContain('--skip-email')->and($operation->result['log'] ?? '')->toContain('--admin_password=***')->not->toContain('Toolkit-Admin-2026x')->not->toContain('Deprecated'); // secrets masked, WP-CLI's PHP noise dropped

    // 2. a database made in the panel: without its password the install stops, with it the toolkit keeps the password
    $panelDb = rtrim(Naming::prefix($service->id), '_').'_shop'; // panel-made databases carry the site prefix, like every database of the site
    $databases = [['id' => 7, 'name' => $panelDb, 'username' => 'shop_user', 'codeing' => 'utf8mb4']];
    $response = $action('wp.install', ['title' => 'Shop', 'admin_email' => 'owner@shop.cz', 'database_id' => '7'], 'wp-4')->assertAccepted();
    $operation = driveOperation(Operation::query()->findOrFail($response->json('operation_id')));
    expect($operation->state)->toBe(Operation::FAILED)->and($operation->error['message'] ?? '')->toContain($panelDb)->toContain('database_password');
    $response = $action('wp.install', ['title' => 'Shop', 'admin_email' => 'owner@shop.cz', 'database_id' => '7', 'database_password' => 'Db-Secret-2026'], 'wp-5')->assertAccepted();
    $operation = driveOperation(Operation::query()->findOrFail($response->json('operation_id')));
    expect($operation->state)->toBe(Operation::SUCCEEDED, json_encode($operation->error))
        ->and($configCreate())->toContain("--dbname='{$panelDb}'")->toContain("--dbuser='shop_user'")->toContain("--dbpass='Db-Secret-2026'")
        ->and(app(DatabaseCredentials::class)->read($service, '7')['password'] ?? '')->toBe('Db-Secret-2026')
        ->and($databases)->toHaveCount(1); // nothing new was created
    $response = $action('wp.install', ['title' => 'Shop', 'admin_email' => 'owner@shop.cz', 'database_id' => '404'], 'wp-6')->assertAccepted();
    expect(driveOperation(Operation::query()->findOrFail($response->json('operation_id')))->state)->toBe(Operation::FAILED);
    expect(json_encode($this->getJson("{$base}/operations")->json()))->not->toMatch('/aapanel/i');
});
