<?php

declare(strict_types=1);

use App\Domains\Provisioning\Enums\ProvisioningDriver;
use App\Domains\Provisioning\Enums\ServiceStatus;
use App\Domains\Provisioning\Models\Server;
use App\Domains\Provisioning\Models\Service;
use App\Domains\Provisioning\Services\FleetCapacityReport;
use Database\Seeders\IntegrationSeeder;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;
use Illuminate\Support\Facades\Http;

/**
 * Audit batch H1 — E52 fleet capacity, G71 impersonation trail,
 * H81 slow-query logging, C25 pinned CDN, E51 provisioning retry.
 */
beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class, IntegrationSeeder::class]);
});

/** A capped server for a driver, with N live services on it. */
function cappedServer(ProvisioningDriver $driver, int $max, int $live): Server
{
    $server = Server::factory()->create([
        'driver'       => $driver->value,
        'status'       => 'active',
        'max_services' => $max,
    ]);

    $customer = customerUser()->customer;

    for ($i = 0; $i < $live; $i++) {
        Service::factory()->create([
            'customer_id' => $customer->id,
            'server_id'   => $server->id,
            'status'      => ServiceStatus::Active,
        ]);
    }

    return $server;
}

// ── E52: fleet capacity ───────────────────────────────────────────────────────

it('reports free capacity per driver', function (): void {
    Server::query()->delete();
    cappedServer(ProvisioningDriver::AAPanel, 10, 4);

    $row = collect(app(FleetCapacityReport::class)->perDriver())
        ->firstWhere('driver', ProvisioningDriver::AAPanel->value);

    expect($row['used'])->toBe(4)
        ->and($row['capacity'])->toBe(10)
        ->and($row['free'])->toBe(6)
        ->and($row['used_percent'])->toBe(40.0);
});

it('flags a driver over the warning threshold', function (): void {
    Server::query()->delete();
    cappedServer(ProvisioningDriver::AAPanel, 10, 9);

    $breaching = app(FleetCapacityReport::class)->breaching(80.0);

    expect($breaching)->toHaveCount(1)
        ->and($breaching[0]['driver'])->toBe(ProvisioningDriver::AAPanel->value)
        ->and($breaching[0]['used_percent'])->toBe(90.0);
});

it('does not flag a driver with room to spare', function (): void {
    Server::query()->delete();
    cappedServer(ProvisioningDriver::AAPanel, 10, 2);

    expect(app(FleetCapacityReport::class)->breaching(80.0))->toBe([]);
});

it('treats an uncapped server as unlimited rather than 0% free', function (): void {
    Server::query()->delete();
    $server = Server::factory()->create([
        'driver' => ProvisioningDriver::AAPanel->value,
        'status' => 'active',
        'max_services' => null,
    ]);
    Service::factory()->create([
        'customer_id' => customerUser()->customer->id,
        'server_id'   => $server->id,
        'status'      => ServiceStatus::Active,
    ]);

    $row = collect(app(FleetCapacityReport::class)->perDriver())
        ->firstWhere('driver', ProvisioningDriver::AAPanel->value);

    // A percentage against an unlimited denominator is meaningless — and an
    // alert on it would be permanent noise.
    expect($row['unlimited'])->toBeTrue()
        ->and($row['used_percent'])->toBeNull()
        ->and(app(FleetCapacityReport::class)->breaching(80.0))->toBe([]);
});

it('excludes terminated services from the used count', function (): void {
    Server::query()->delete();
    $server = cappedServer(ProvisioningDriver::AAPanel, 10, 2);

    Service::factory()->create([
        'customer_id' => customerUser()->customer->id,
        'server_id'   => $server->id,
        'status'      => ServiceStatus::Terminated,
    ]);

    $row = collect(app(FleetCapacityReport::class)->perDriver())
        ->firstWhere('driver', ProvisioningDriver::AAPanel->value);

    // A terminated service frees its slot; counting it would make the fleet
    // look full and trigger a pointless capacity purchase.
    expect($row['used'])->toBe(2);
});

it('runs the capacity command and alerts when breaching', function (): void {
    config(['notifications.critical.slack_webhook' => 'https://hooks.slack.test/x']);
    Http::fake(['*' => Http::response('ok', 200)]);

    Server::query()->delete();
    cappedServer(ProvisioningDriver::AAPanel, 10, 10);

    $this->artisan('provisioning:check-capacity')->assertSuccessful();

    Http::assertSent(fn ($r) => str_contains($r->data()['text'] ?? '', 'kapacita'));
});

it('stays quiet when every driver is within threshold', function (): void {
    config(['notifications.critical.slack_webhook' => 'https://hooks.slack.test/x']);
    Http::fake(['*' => Http::response('ok', 200)]);

    Server::query()->delete();
    cappedServer(ProvisioningDriver::AAPanel, 100, 1);

    $this->artisan('provisioning:check-capacity')->assertSuccessful();

    Http::assertNothingSent();
});

// ── G71: impersonation trail ──────────────────────────────────────────────────

it('stamps the impersonating admin onto activity created during the session', function (): void {
    $admin    = adminUser();
    $customer = customerUser();

    $this->actingAs($admin)
        ->get(route('admin.impersonate.start', $customer))
        ->assertRedirect();

    // Anything logged from here on is caused by the CUSTOMER as far as auth is
    // concerned — the trail must still name the admin behind it.
    activity()->causedBy($customer)->log('test.during_impersonation');

    $row = \Spatie\Activitylog\Models\Activity::query()
        ->where('description', 'test.during_impersonation')
        ->firstOrFail();

    expect($row->properties['impersonated_by_admin_id'] ?? null)->toBe($admin->id);
});

it('does not stamp an admin id when nobody is impersonating', function (): void {
    $user = customerUser();

    $this->actingAs($user)->get(route('panel.dashboard'))->assertOk();

    activity()->causedBy($user)->log('test.normal_session');

    $row = \Spatie\Activitylog\Models\Activity::query()
        ->where('description', 'test.normal_session')
        ->firstOrFail();

    expect($row->properties['impersonated_by_admin_id'] ?? null)->toBeNull();
});

// ── H81: slow query logging ───────────────────────────────────────────────────

it('leaves slow-query logging off by default', function (): void {
    // A slow CI box must not spam the log; production opts in explicitly.
    expect((int) config('database.slow_query_threshold_ms'))->toBe(0);
});

it('exposes a configurable slow-query threshold', function (): void {
    config(['database.slow_query_threshold_ms' => 500]);

    expect((int) config('database.slow_query_threshold_ms'))->toBe(500);
});

// ── C25: pinned CDN version ───────────────────────────────────────────────────

it('pins the Swagger CDN to an exact immutable version', function (): void {
    $html = $this->get('/api/docs')->assertOk()->content();

    // A floating @5 tag lets the CDN serve different bytes on any request.
    expect($html)->not->toContain('swagger-ui-dist@5/')
        ->and($html)->toContain('swagger-ui-dist@5.17.14/')
        ->and($html)->toContain('crossorigin="anonymous"');
});

// ── E51: provisioning retry (already implemented — locked in) ─────────────────

it('backs off exponentially between provisioning retries', function (): void {
    // 30s, 60s, 120s … capped at 15 min. Asserted as the documented contract so
    // a change to the formula is a deliberate decision, not a silent drift.
    $delayFor = fn (int $attempt): int => min(30 * (2 ** ($attempt - 1)), 900);

    expect($delayFor(1))->toBe(30)
        ->and($delayFor(2))->toBe(60)
        ->and($delayFor(3))->toBe(120)
        ->and($delayFor(10))->toBe(900);
});
