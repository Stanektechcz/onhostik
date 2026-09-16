<?php

declare(strict_types=1);

use App\Http\Controllers\Web\SurfaceDataController;
use Database\Seeders\CatalogSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Onhost\Domain\Catalog\Models\Product;
use Onhost\Domain\Identity\Authorization\Models\PolicyBinding;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Identity\StepUp\Totp;
use Onhost\Domain\Notifications\Mail\TemplatedMail;
use Onhost\Domain\Provisioning\Models\Node;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Domain\Provisioning\Models\Region;
use Onhost\Domain\Provisioning\PlacementService;
use Onhost\Domain\Provisioning\Scheduling\NodeScheduler;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Resilience\CircuitBreaker;

/* The two commands a fresh installation needs before the console is usable: the first staff account and the provider instances. */

beforeEach(fn () => Http::preventStrayRequests());

it('creates the first staff account with a global role and registers a provider instance from the terminal', function () {
    $this->artisan('onhost:staff:create not-an-email')->assertExitCode(1);
    $this->artisan('onhost:staff:create ops@onhost.test --role=nope')->assertExitCode(1);
    $this->artisan('onhost:staff:create ops@onhost.test --name=Ops --role=platform_owner')->expectsQuestion('Password (at least 12 characters)', 'short')->assertExitCode(1);
    $this->artisan('onhost:staff:create ops@onhost.test --name=Ops --role=platform_owner')->expectsQuestion('Password (at least 12 characters)', 'Velmi-dlouhe-heslo-2026')->expectsOutputToContain('created with the role platform_owner')->assertExitCode(0);
    $user = User::query()->where('email', 'ops@onhost.test')->firstOrFail();
    expect($user->is_staff)->toBeTrue()->and(PolicyBinding::query()->where('principal_id', $user->id)->value('role_key'))->toBe('platform_owner');
    $this->artisan('onhost:staff:create ops@onhost.test')->assertExitCode(1);

    Region::query()->firstOrCreate(['code' => 'cz1'], ['name' => 'Praha', 'country' => 'CZ', 'state' => 'active']);
    $this->artisan('onhost:integrations:register pterodactyl-gamepanel pterodactyl https://gamepanel.onhost.test --name="Game panel" --region=cz1 --option=foo=bar')->expectsOutputToContain('missing: application_key')->assertExitCode(0);
    $instance = ProviderInstance::query()->where('key', 'pterodactyl-gamepanel')->firstOrFail();
    expect($instance->provider)->toBe('pterodactyl')->and($instance->base_url)->toBe('https://gamepanel.onhost.test')->and($instance->region_code)->toBe('cz1')->and($instance->option('foo'))->toBe('bar')->and($instance->state)->toBe('active');
});

it('enrols and confirms the authenticator of a staff account, and places a product on an instance', function () {
    $this->artisan('onhost:staff:create ops@onhost.test --role=platform_owner')->expectsQuestion('Password (at least 12 characters)', 'Velmi-dlouhe-heslo-2026')->assertExitCode(0);
    $this->artisan('onhost:staff:totp nobody@onhost.test')->assertExitCode(1);
    $this->artisan('onhost:staff:totp ops@onhost.test --code=123456')->expectsOutputToContain('Start the enrolment first')->assertExitCode(1);
    $this->artisan('onhost:staff:totp ops@onhost.test')->expectsOutputToContain('otpauth://totp/')->assertExitCode(0);
    $user = User::query()->where('email', 'ops@onhost.test')->firstOrFail();
    expect($user->totp_secret)->not->toBeNull()->and($user->hasTotp())->toBeFalse();
    $this->artisan('onhost:staff:totp ops@onhost.test --code=000000')->expectsOutputToContain('does not match')->assertExitCode(1);
    $this->artisan('onhost:staff:totp ops@onhost.test --code='.Totp::code((string) $user->totp_secret))->expectsOutputToContain('Recovery codes')->assertExitCode(0);
    expect($user->refresh()->hasTotp())->toBeTrue();
    $this->artisan('onhost:staff:totp ops@onhost.test')->expectsOutputToContain('already confirmed')->assertExitCode(0);
    $this->postJson('/v1/auth/login', ['email' => 'ops@onhost.test', 'password' => 'Velmi-dlouhe-heslo-2026'])->assertStatus(403)->assertJsonPath('error', 'mfa_required'); // MFA exists, the code is asked for

    $this->seed(CatalogSeeder::class);
    Region::query()->firstOrCreate(['code' => 'cz1'], ['name' => 'Praha', 'country' => 'CZ', 'state' => 'active']);
    $this->artisan('onhost:integrations:register aapanel-managed01 aapanel https://panel.onhost.test:28133 --region=cz1')->assertExitCode(0);
    $this->artisan('onhost:placements:set web-hosting aapanel-managed01')->expectsOutputToContain('web-hosting → aapanel-managed01')->assertExitCode(0);
    $this->artisan('onhost:placements:set vps aapanel-managed01')->assertExitCode(1); // proxmox products cannot run on aaPanel
    expect(app(PlacementService::class)->resolve('web-hosting', null, 'cz1')?->providerInstance?->key)->toBe('aapanel-managed01');
});

it('overlays both game pages with the catalogue plans and the templates the panel offers', function () {
    $this->seed(CatalogSeeder::class);
    [, $org] = $this->customerWithOrganization();
    featureGameService($org);
    ProviderInstance::query()->where('key', 'pterodactyl-games01')->firstOrFail()->forceFill(['options' => ['eggs' => ['minecraft-paper' => ['nest' => 1, 'egg' => 1], 'minecraft-bedrock' => ['nest' => 1, 'egg' => 30], 'cs2' => ['nest' => 5, 'egg' => 17, 'required' => [['env' => 'STEAM_GSLT', 'rules' => 'required|size:32', 'editable' => true]]], 'dayz' => ['nest' => 5, 'egg' => 18, 'required' => [['env' => 'STEAM_USER', 'rules' => 'required', 'editable' => false]]]]]])->save();
    $pages = (fn () => $this->pageRows('cs'))->call(app(SurfaceDataController::class));
    expect($pages['minecraft']['plans'][0]['sku']['product_key'])->toBe('game')->and($pages['minecraft']['chips'])->toBe(['Paper', 'Bedrock'])->and($pages['minecraft']['kpi_games'][0])->toBe('2');
    expect($pages['gamehosting']['chips'])->toBe(['Counter-Strike 2'])->and($pages['gamehosting']['kicker'])->toBe('Counter-Strike 2'); // DayZ waits for the operator's Steam account
    expect((string) file_get_contents(base_path('apps/surfaces/api/onhost-svc-pages.api.js')))->toContain('page.chips = o.chips');
});

it('takes products off sale and back, and shows and resets the breakers of an instance (audit §5z)', function () {
    $this->seed(CatalogSeeder::class);
    $this->artisan('onhost:catalog:state draft vps vds')->expectsOutputToContain('vps → draft')->assertExitCode(0);
    expect(Product::query()->where('key', 'vps')->value('state'))->toBe('draft');
    $this->artisan('onhost:catalog:state retired vps')->assertExitCode(1);
    $this->artisan('onhost:catalog:state active vps')->assertExitCode(0);
    expect(Product::query()->where('key', 'vps')->value('state'))->toBe('active');

    ProviderInstance::query()->firstOrCreate(['key' => 'wedos-main'], ['provider' => 'wedos', 'name' => 'WEDOS', 'base_url' => 'https://api.wedos.com/wapi/json', 'state' => 'active', 'secret_ref' => 'db://registrars/wedos', 'options' => []]);
    $wapi = new CircuitBreaker(app('cache.store'), 'wapi:invalid', 10, 900, 3600);
    $wapi->trip();
    $this->artisan('onhost:integrations:breaker wedos-main')->expectsOutputToContain('open')->assertExitCode(0);
    $this->artisan('onhost:integrations:breaker wedos-main --reset')->expectsOutputToContain('Breakers closed')->assertExitCode(0);
    expect($wapi->state())->toBe('closed');
});

it('sends a test e-mail through the mailer and validates the smoke test products (audit §5z)', function () {
    Mail::fake();
    $this->artisan('onhost:mail:test nobody')->assertExitCode(1);
    $this->artisan('onhost:mail:test ops@onhost.test')->expectsOutputToContain('Sent to ops@onhost.test')->expectsOutputToContain('mail queue')->assertExitCode(0);
    Mail::assertSent(TemplatedMail::class, fn ($m) => $m->hasTo('ops@onhost.test'));

    $this->seed(CatalogSeeder::class);
    [, $org] = $this->customerWithOrganization();
    $this->artisan("onhost:smoke:order {$org->id} --product=neexistuje")->expectsOutputToContain('Neznámý produkt neexistuje')->assertExitCode(1);
    $this->artisan("onhost:smoke:order {$org->id} --product=wordpress@nope")->expectsOutputToContain('Neznámá instance: nope')->assertExitCode(1);
});

it('lets one panel host serve web and mail: --add-role adds a role the scheduler honours (audit §5z)', function () {
    Region::query()->firstOrCreate(['code' => 'cz1'], ['name' => 'Praha', 'country' => 'CZ', 'state' => 'active']);
    $instance = ProviderInstance::query()->create(['key' => 'ispconfig-roles', 'provider' => 'ispconfig', 'name' => 'ISPConfig', 'base_url' => 'https://s2.onhost.test:8080', 'region_code' => 'cz1', 'state' => 'active', 'options' => [], 'secret_ref' => 'db://test/ispconfig']);
    Node::query()->create(['provider_instance_id' => $instance->id, 'name' => 's2.onhost.test', 'region_code' => 'cz1', 'role' => 'web', 'state' => 'active', 'capacity' => [], 'usage' => [], 'failure_domain' => 's2', 'tags' => ['ispconfig_roles' => ['web']]]);
    $scheduler = app(NodeScheduler::class);
    $mail = ['role' => 'mail', 'region' => 'cz1', 'provider' => 'ispconfig', 'placement' => ['instance_id' => $instance->id, 'instance_key' => 'ispconfig-roles']];
    expect(fn () => $scheduler->pick($mail))->toThrow(DomainError::class);

    $node = Node::query()->where('provider_instance_id', $instance->id)->sole();
    $node->forceFill(['tags' => array_merge((array) $node->tags, ['roles' => ['mail']])])->save(); // what `onhost:nodes:discover ispconfig-roles --add-role=mail` stores
    expect($scheduler->pick($mail)['node']->name)->toBe('s2.onhost.test')
        ->and($scheduler->pick(['role' => 'web', 'region' => 'cz1', 'provider' => 'ispconfig'])['node']->name)->toBe('s2.onhost.test')
        ->and(NodeScheduler::serves($node->refresh(), 'game'))->toBeFalse();
});

it('recreates a deleted ISPConfig site from its data-log record and lists the backups the node still has (§5z incident)', function () {
    $_ENV['ISPCONFIG_S2_REMOTE_USER'] = 'onhost-remote';
    $_ENV['ISPCONFIG_S2_REMOTE_PASSWORD'] = 'remote-secret';
    ProviderInstance::query()->create(['key' => 'ispconfig-s2', 'provider' => 'ispconfig', 'name' => 'ISPConfig s2', 'base_url' => 'https://s2.onhost.test:8080', 'state' => 'active', 'options' => ['server_id' => 1, 'verify_tls' => false], 'secret_ref' => 'env://ISPCONFIG_S2']);
    $record = tempnam(sys_get_temp_dir(), 'rec').'.json';
    file_put_contents($record, json_encode(['old' => ['domain_id' => 27, 'domain' => 's4s.electree.cz', 'server_id' => 1, 'client_id' => 9, 'system_user' => 'web27', 'system_group' => 'client5',
        'document_root' => '/var/www/clients/client5/web27', 'hd_quota' => 10240, 'php' => 'php-fpm', 'fastcgi_php_version' => 'PHP 8.3:/etc/php/8.3/fpm:/run/php/php8.3-fpm.sock', 'active' => 'n', 'backup_copies' => 7]]));

    Http::fake([
        's2.onhost.test:8080/remote/json.php?login' => Http::response(['code' => 'ok', 'message' => '', 'response' => 'sess-restore']),
        's2.onhost.test:8080/remote/json.php?sites_web_domain_get' => Http::sequence()
            ->push(['code' => 'ok', 'message' => '', 'response' => []])                                       // the site is really gone
            ->push(['code' => 'ok', 'message' => '', 'response' => ['domain_id' => 33, 'domain' => 's4s.electree.cz', 'system_user' => 'web33', 'document_root' => '/var/www/clients/client5/web33']])
            ->push(['code' => 'ok', 'message' => '', 'response' => ['domain_id' => 33, 'domain' => 's4s.electree.cz', 'system_user' => 'web33']]),
        's2.onhost.test:8080/remote/json.php?sites_web_domain_add' => Http::response(['code' => 'ok', 'message' => '', 'response' => 33]),
        's2.onhost.test:8080/remote/json.php?monitor_jobqueue_count' => Http::response(['code' => 'ok', 'message' => '', 'response' => 0]),
        's2.onhost.test:8080/remote/json.php?sites_web_domain_backup_list' => Http::response(['code' => 'ok', 'message' => '', 'response' => [['backup_id' => 41, 'backup_type' => 'web', 'tstamp' => time() - 3600, 'filesize' => 52428800, 'filename' => 'web27_2026-09-15.tar.gz']]]),
    ]);

    expect(Artisan::call('onhost:ispconfig:restore-site', ['domain' => 's4s.electree.cz', '--instance' => 'ispconfig-s2', '--record' => $record]))->toBe(0);
    expect(Artisan::output())->toContain('site recreated: domain_id 33')->toContain('web33');
    Http::assertSent(fn ($r) => str_ends_with($r->url(), '?sites_web_domain_add') && $r['client_id'] === 9 && $r['params']['domain'] === 's4s.electree.cz' && $r['params']['system_user'] === 'web27' && $r['params']['active'] === 'y');

    expect(Artisan::call('onhost:ispconfig:restore-site', ['domain' => 's4s.electree.cz', '--instance' => 'ispconfig-s2', '--list-backups' => true]))->toBe(0);
    expect(Artisan::output())->toContain('web27_2026-09-15.tar.gz');
    @unlink($record);
});
