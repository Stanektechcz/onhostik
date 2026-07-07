<?php

declare(strict_types=1);

use Database\Seeders\IntegrationSeeder;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;
use Spatie\Activitylog\Models\Activity;

beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class, IntegrationSeeder::class]);
});

// ── Index page ─────────────────────────────────────────────────────────────────

it('audit log index loads for admin', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
        ->get(route('admin.logs.audit'))
        ->assertOk()
        ->assertSee('audit');
});

it('audit log index filters by log name', function (): void {
    $admin = adminUser();

    activity('invoice')->log('invoice created');
    activity('order')->log('order placed');

    $this->actingAs($admin)
        ->get(route('admin.logs.audit', ['log' => 'invoice']))
        ->assertOk();
});

it('audit log index filters by date from', function (): void {
    $admin = adminUser();

    activity('order')->log('old event');

    $this->actingAs($admin)
        ->get(route('admin.logs.audit', ['from' => now()->format('Y-m-d')]))
        ->assertOk();
});

it('audit log index filters by date to', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
        ->get(route('admin.logs.audit', ['to' => now()->format('Y-m-d')]))
        ->assertOk();
});

it('audit log index filters by search query', function (): void {
    $admin = adminUser();

    activity('customer')->log('special-description-xyz-test');

    $this->actingAs($admin)
        ->get(route('admin.logs.audit', ['q' => 'special-description-xyz-test']))
        ->assertOk()
        ->assertSee('special-description-xyz-test');
});

it('audit log index filters by subject type', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
        ->get(route('admin.logs.audit', ['subject_type' => 'Order']))
        ->assertOk();
});

it('audit log index shows both CSV and JSON export buttons', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
        ->get(route('admin.logs.audit'))
        ->assertOk()
        ->assertSee('CSV')
        ->assertSee('JSON');
});

it('non-admin cannot access audit log', function (): void {
    $user = customerUser();

    $this->actingAs($user)
        ->get(route('admin.logs.audit'))
        ->assertStatus(403);
});

// ── CSV export ─────────────────────────────────────────────────────────────────

it('admin can export audit log as CSV', function (): void {
    $admin = adminUser();

    activity('order')->log('exported-event');

    $response = $this->actingAs($admin)
        ->get(route('admin.logs.audit.export', ['format' => 'csv']))
        ->assertOk();

    expect($response->headers->get('Content-Type'))->toContain('text/csv');
    expect($response->headers->get('Content-Disposition'))->toContain('.csv');
    expect($response->getContent())->toContain('ID,Log,Event');
});

it('CSV export contains activity data', function (): void {
    $admin = adminUser();

    activity('invoice')->log('csv-test-entry-99');

    $response = $this->actingAs($admin)
        ->get(route('admin.logs.audit.export', ['format' => 'csv', 'log' => 'invoice']))
        ->assertOk();

    expect($response->getContent())->toContain('csv-test-entry-99');
});

it('CSV export filters by date range', function (): void {
    $admin = adminUser();

    activity('payment')->log('date-range-test');

    $response = $this->actingAs($admin)
        ->get(route('admin.logs.audit.export', [
            'format' => 'csv',
            'from'   => now()->subDay()->format('Y-m-d'),
            'to'     => now()->addDay()->format('Y-m-d'),
        ]))
        ->assertOk();

    expect($response->getContent())->toContain('date-range-test');
});

it('CSV export returns empty set for future date range', function (): void {
    $admin = adminUser();

    activity('order')->log('should-not-appear');

    $response = $this->actingAs($admin)
        ->get(route('admin.logs.audit.export', [
            'format' => 'csv',
            'from'   => now()->addYear()->format('Y-m-d'),
            'to'     => now()->addYear()->addMonth()->format('Y-m-d'),
        ]))
        ->assertOk();

    $lines = explode("\n", trim($response->getContent()));
    expect(count($lines))->toBe(1); // only header row
});

it('CSV export filters by search query', function (): void {
    $admin = adminUser();

    activity('support')->log('unique-csv-search-term-abc');
    activity('support')->log('other-entry');

    $response = $this->actingAs($admin)
        ->get(route('admin.logs.audit.export', [
            'format' => 'csv',
            'q'      => 'unique-csv-search-term-abc',
        ]))
        ->assertOk();

    $content = $response->getContent();
    expect($content)->toContain('unique-csv-search-term-abc');
    expect($content)->not->toContain('other-entry');
});

// ── JSON export ────────────────────────────────────────────────────────────────

it('admin can export audit log as JSON', function (): void {
    $admin = adminUser();

    activity('order')->log('json-export-test');

    $response = $this->actingAs($admin)
        ->get(route('admin.logs.audit.export', ['format' => 'json']))
        ->assertOk();

    expect($response->headers->get('Content-Type'))->toContain('application/json');
    expect($response->headers->get('Content-Disposition'))->toContain('.json');
});

it('JSON export has correct structure', function (): void {
    $admin = adminUser();

    activity('customer')->log('json-structure-test');

    $response = $this->actingAs($admin)
        ->get(route('admin.logs.audit.export', ['format' => 'json', 'log' => 'customer']))
        ->assertOk();

    $data = json_decode($response->getContent(), true);

    expect($data)->toHaveKey('exported_at')
        ->and($data)->toHaveKey('count')
        ->and($data)->toHaveKey('records')
        ->and($data['records'])->toBeArray();
});

it('JSON export records contain required fields', function (): void {
    $admin = adminUser();

    activity('service')->log('json-fields-check');

    $response = $this->actingAs($admin)
        ->get(route('admin.logs.audit.export', ['format' => 'json', 'log' => 'service']))
        ->assertOk();

    $data    = json_decode($response->getContent(), true);
    $records = $data['records'];

    expect($records)->not->toBeEmpty();

    $record = $records[0];
    expect($record)->toHaveKey('id')
        ->and($record)->toHaveKey('log_name')
        ->and($record)->toHaveKey('description')
        ->and($record)->toHaveKey('causer_name')
        ->and($record)->toHaveKey('causer_email')
        ->and($record)->toHaveKey('subject_type')
        ->and($record)->toHaveKey('subject_id')
        ->and($record)->toHaveKey('properties')
        ->and($record)->toHaveKey('timestamp');
});

it('JSON export count matches records length', function (): void {
    $admin = adminUser();

    activity('credit')->log('count-test-1');
    activity('credit')->log('count-test-2');
    activity('credit')->log('count-test-3');

    $response = $this->actingAs($admin)
        ->get(route('admin.logs.audit.export', ['format' => 'json', 'log' => 'credit']))
        ->assertOk();

    $data = json_decode($response->getContent(), true);

    expect($data['count'])->toBe(count($data['records']));
});

it('JSON export filters by search query', function (): void {
    $admin = adminUser();

    activity('user')->log('json-filter-unique-xyz');
    activity('user')->log('json-other-entry');

    $response = $this->actingAs($admin)
        ->get(route('admin.logs.audit.export', [
            'format' => 'json',
            'q'      => 'json-filter-unique-xyz',
        ]))
        ->assertOk();

    $data = json_decode($response->getContent(), true);
    expect($data['count'])->toBe(1)
        ->and($data['records'][0]['description'])->toBe('json-filter-unique-xyz');
});

it('default export format is CSV when no format param given', function (): void {
    $admin = adminUser();

    $response = $this->actingAs($admin)
        ->get(route('admin.logs.audit.export'))
        ->assertOk();

    expect($response->headers->get('Content-Type'))->toContain('text/csv');
});

it('non-admin cannot export audit log', function (): void {
    $user = customerUser();

    $this->actingAs($user)
        ->get(route('admin.logs.audit.export'))
        ->assertStatus(403);
});
