<?php

declare(strict_types=1);

use App\Http\Controllers\Web\SurfaceDataController;
use Database\Seeders\CatalogSeeder;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Identity\Authorization\Models\PolicyBinding;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Identity\StepUp\Totp;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Domain\Provisioning\Models\Region;
use Onhost\Domain\Provisioning\PlacementService;

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
