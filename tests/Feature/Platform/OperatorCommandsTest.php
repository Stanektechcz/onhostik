<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Onhost\Domain\Identity\Authorization\Models\PolicyBinding;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Domain\Provisioning\Models\Region;

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
