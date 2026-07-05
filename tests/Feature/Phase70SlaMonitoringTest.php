<?php

declare(strict_types=1);

use App\Domains\Monitoring\Models\SlaIncident;
use App\Domains\Monitoring\Models\SlaIncidentUpdate;
use App\Domains\Monitoring\Models\SlaUptimeCheck;
use App\Domains\Monitoring\Models\SlaTier;
use App\Domains\Monitoring\Services\SlaService;
use App\Domains\Provisioning\Models\Service;
use Database\Seeders\IntegrationSeeder;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;

beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class, IntegrationSeeder::class]);
});

// ── Helpers ───────────────────────────────────────────────────────────────────

function makeTier(array $attrs = []): SlaTier
{
    return SlaTier::create(array_merge([
        'name'                    => 'Basic',
        'slug'                    => 'basic-' . uniqid(),
        'uptime_percent_x100'     => 9990,   // 99.9%
        'response_time_minutes'   => 60,
        'resolution_time_hours'   => 8,
        'credit_percent_per_hour' => 5,
        'max_credit_percent'      => 25,
        'is_active'               => true,
    ], $attrs));
}

function makeService(): Service
{
    return Service::first() ?? Service::factory()->create();
}

// ── SlaTier model ─────────────────────────────────────────────────────────────

it('SlaTier uptimeLabel formats correctly', function (): void {
    $t9990 = new SlaTier(['uptime_percent_x100' => 9990]);
    $t9999 = new SlaTier(['uptime_percent_x100' => 9999]);
    $t9900 = new SlaTier(['uptime_percent_x100' => 9900]);

    expect($t9990->uptimeLabel())->toBe('99.90 %')
        ->and($t9999->uptimeLabel())->toBe('99.99 %')
        ->and($t9900->uptimeLabel())->toBe('99 %');
});

it('SlaTier allowedDowntimeMinutes is calculated correctly for 99.9%', function (): void {
    $tier = new SlaTier(['uptime_percent_x100' => 9990]);
    // 30 * 24 * 60 * 0.001 = 43.2 → 43
    expect($tier->allowedDowntimeMinutes())->toBeLessThanOrEqual(44);
    expect($tier->allowedDowntimeMinutes())->toBeGreaterThanOrEqual(43);
});

// ── SlaUptimeCheck model ──────────────────────────────────────────────────────

it('SlaUptimeCheck statusLabel returns Czech strings', function (): void {
    expect((new SlaUptimeCheck(['status' => 'up']))->statusLabel())->toBe('Dostupný')
        ->and((new SlaUptimeCheck(['status' => 'down']))->statusLabel())->toBe('Nedostupný')
        ->and((new SlaUptimeCheck(['status' => 'degraded']))->statusLabel())->toBe('Degradovaný')
        ->and((new SlaUptimeCheck(['status' => 'unknown']))->statusLabel())->toBe('Neznámý');
});

// ── SlaIncident model ─────────────────────────────────────────────────────────

it('SlaIncident severity and status labels are correct', function (): void {
    $i = new SlaIncident(['severity' => 'critical', 'status' => 'open']);
    expect($i->severityLabel())->toBe('Kritická')
        ->and($i->statusLabel())->toBe('Otevřená');
});

it('SlaIncident durationLabel formats correctly', function (): void {
    $under  = new SlaIncident(['downtime_minutes' => 45]);
    $hourly = new SlaIncident(['downtime_minutes' => 90]);
    $exact  = new SlaIncident(['downtime_minutes' => 120]);

    expect($under->durationLabel())->toBe('45 min')
        ->and($hourly->durationLabel())->toBe('1h 30min')
        ->and($exact->durationLabel())->toBe('2h');
});

// ── SlaService::recordCheck ───────────────────────────────────────────────────

it('recordCheck persists an uptime check', function (): void {
    $service = makeService();
    $check   = app(SlaService::class)->recordCheck($service, 'up', 120);

    expect($check->id)->toBeInt()
        ->and($check->status)->toBe('up')
        ->and($check->response_ms)->toBe(120)
        ->and($check->service_id)->toBe($service->id);
});

it('recordCheck stores error message for down status', function (): void {
    $service = makeService();
    $check   = app(SlaService::class)->recordCheck($service, 'down', null, 'Connection refused');

    expect($check->status)->toBe('down')
        ->and($check->error_message)->toBe('Connection refused');
});

// ── SlaService::uptimeStats ───────────────────────────────────────────────────

it('uptimeStats returns 100% when no checks recorded', function (): void {
    $service = makeService();
    $stats   = app(SlaService::class)->uptimeStats($service, now()->subDay(), now());

    expect($stats['uptime_percent'])->toBe(100.0)
        ->and($stats['total_checks'])->toBe(0);
});

it('uptimeStats calculates uptime correctly', function (): void {
    $service = makeService();
    $svc     = app(SlaService::class);

    // 8 up, 2 down → 80% uptime
    foreach (range(1, 8) as $_) {
        $svc->recordCheck($service, 'up', 100);
    }
    foreach (range(1, 2) as $_) {
        $svc->recordCheck($service, 'down');
    }

    $stats = $svc->uptimeStats($service, now()->subHour(), now()->addMinute());

    expect($stats['total_checks'])->toBe(10)
        ->and($stats['down_checks'])->toBe(2)
        ->and($stats['uptime_percent'])->toBe(80.0);
});

// ── SlaService::openIncident ──────────────────────────────────────────────────

it('openIncident creates incident with investigating update', function (): void {
    $service  = makeService();
    $incident = app(SlaService::class)->openIncident($service, 'DB outage', 'critical', 'Database is down');

    expect($incident->title)->toBe('DB outage')
        ->and($incident->severity)->toBe('critical')
        ->and($incident->status)->toBe('open')
        ->and($incident->updates()->count())->toBe(1);
});

// ── SlaService::addUpdate ─────────────────────────────────────────────────────

it('addUpdate adds a status update to an incident', function (): void {
    $service  = makeService();
    $incident = app(SlaService::class)->openIncident($service, 'Test', 'low');

    app(SlaService::class)->addUpdate($incident, 'Root cause found.', 'identified');

    expect($incident->updates()->count())->toBe(2);
    $last = $incident->updates()->orderBy('id', 'desc')->first();
    expect($last->status)->toBe('identified')
        ->and($last->message)->toBe('Root cause found.');
});

// ── SlaService::resolve ───────────────────────────────────────────────────────

it('resolve marks incident as resolved and calculates downtime', function (): void {
    $service  = makeService();
    $incident = app(SlaService::class)->openIncident($service, 'Test', 'high');

    app(SlaService::class)->resolve($incident, 'Issue fixed.');

    $incident->refresh();
    expect($incident->status)->toBe('resolved')
        ->and($incident->resolved_at)->not->toBeNull()
        ->and($incident->downtime_minutes)->toBeGreaterThanOrEqual(0);
});

it('resolve marks sla_breached when downtime exceeds tier allowance', function (): void {
    $tier    = makeTier(['uptime_percent_x100' => 9990]); // ~43 min allowed
    $service = makeService();
    $service->update(['sla_tier_id' => $tier->id]);

    $incident = SlaIncident::create([
        'service_id'  => $service->id,
        'title'       => 'Long outage',
        'severity'    => 'critical',
        'status'      => 'open',
        'started_at'  => now()->subHours(3), // 180 min >> 43 min allowed
    ]);

    app(SlaService::class)->resolve($incident, 'Fixed after long outage.');

    $incident->refresh();
    expect($incident->sla_breached)->toBeTrue()
        ->and($incident->credit_haler)->toBeGreaterThan(0);
});

// ── SlaService::incidentStats ─────────────────────────────────────────────────

it('incidentStats counts correctly', function (): void {
    $service = makeService();
    $svc     = app(SlaService::class);

    $i1 = $svc->openIncident($service, 'Inc1', 'low');
    $i2 = $svc->openIncident($service, 'Inc2', 'high');
    $svc->resolve($i2, 'Fixed.');

    $stats = $svc->incidentStats();

    expect($stats['total'])->toBeGreaterThanOrEqual(2)
        ->and($stats['open'])->toBeGreaterThanOrEqual(1)
        ->and($stats['resolved'])->toBeGreaterThanOrEqual(1);
});

// ── Admin routes ──────────────────────────────────────────────────────────────

it('admin can list SLA tiers', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
        ->get(route('admin.sla-tiers.index'))
        ->assertOk()
        ->assertSee('SLA Tiery');
});

it('admin can create a SLA tier', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
        ->post(route('admin.sla-tiers.store'), [
            'name'                    => 'Premium',
            'slug'                    => 'premium',
            'uptime_percent_x100'     => 9995,
            'response_time_minutes'   => 30,
            'resolution_time_hours'   => 4,
            'credit_percent_per_hour' => 10,
            'max_credit_percent'      => 30,
            'is_active'               => 1,
        ])
        ->assertRedirect(route('admin.sla-tiers.index'));

    expect(SlaTier::where('slug', 'premium')->exists())->toBeTrue();
});

it('admin can list SLA incidents', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
        ->get(route('admin.sla-incidents.index'))
        ->assertOk()
        ->assertSee('SLA Incidenty');
});

it('admin can open a new incident', function (): void {
    $admin   = adminUser();
    $service = makeService();

    $this->actingAs($admin)
        ->post(route('admin.sla-incidents.store'), [
            'service_id' => $service->id,
            'title'      => 'Admin created incident',
            'severity'   => 'medium',
        ])
        ->assertRedirect(route('admin.sla-incidents.index'));

    expect(SlaIncident::where('title', 'Admin created incident')->exists())->toBeTrue();
});

it('admin can view incident detail', function (): void {
    $admin    = adminUser();
    $service  = makeService();
    $incident = app(SlaService::class)->openIncident($service, 'Show test', 'low');

    $this->actingAs($admin)
        ->get(route('admin.sla-incidents.show', $incident))
        ->assertOk()
        ->assertSee('Show test');
});

it('admin can add update to incident', function (): void {
    $admin    = adminUser();
    $service  = makeService();
    $incident = app(SlaService::class)->openIncident($service, 'Update test', 'low');

    $this->actingAs($admin)
        ->post(route('admin.sla-incidents.update', $incident), [
            'message' => 'We identified the root cause.',
            'status'  => 'identified',
        ])
        ->assertRedirect(route('admin.sla-incidents.show', $incident));

    expect($incident->updates()->count())->toBe(2);
});

it('admin can resolve an incident via update', function (): void {
    $admin    = adminUser();
    $service  = makeService();
    $incident = app(SlaService::class)->openIncident($service, 'Resolve test', 'high');

    $this->actingAs($admin)
        ->post(route('admin.sla-incidents.update', $incident), [
            'message' => 'All systems operational.',
            'status'  => 'resolved',
        ])
        ->assertRedirect();

    expect($incident->fresh()->status)->toBe('resolved');
});

it('non-admin cannot manage SLA tiers', function (): void {
    $user = customerUser();

    $this->actingAs($user)
        ->get(route('admin.sla-tiers.index'))
        ->assertStatus(403);
});
