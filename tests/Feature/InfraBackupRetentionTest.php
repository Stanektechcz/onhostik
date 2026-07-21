<?php

declare(strict_types=1);

use App\Domains\Shared\Services\DatabaseBackupService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Audit INFRA #7 (offsite DB backup) and #180 (data retention).
 */

// ── #7: offsite DB backup ────────────────────────────────────────────────────────

it('skips gracefully for an in-memory database rather than crashing', function (): void {
    // The test suite runs on :memory:, which has nothing on disk to copy.
    $result = app(DatabaseBackupService::class)->run('backup-test', 14);

    expect($result['stored'])->toBeNull()
        ->and($result['skipped'])->toContain('memory');
});

it('the db:backup command reports the skip without failing', function (): void {
    $this->artisan('db:backup', ['--disk' => 'backup-test'])
        ->expectsOutputToContain('Backup skipped')
        ->assertSuccessful();
});

it('stores a gzipped backup for a file-based sqlite database', function (): void {
    Storage::fake('backup-test');

    $path = tempnam(sys_get_temp_dir(), 'bk') . '.sqlite';
    file_put_contents($path, "SQLite format 3\0" . str_repeat('x', 64));

    $original = config('database.default');
    config([
        'database.connections.bk_test' => ['driver' => 'sqlite', 'database' => $path],
        'database.default'             => 'bk_test',
    ]);

    try {
        $result = app(DatabaseBackupService::class)->run('backup-test', 14);
    } finally {
        config(['database.default' => $original]);
        @unlink($path);
    }

    expect($result['stored'])->not->toBeNull()
        ->and($result['stored'])->toEndWith('.sqlite.gz');
    Storage::disk('backup-test')->assertExists($result['stored']);
});

it('prunes backups older than the retention window', function (): void {
    Storage::fake('backup-test');
    $fs = Storage::disk('backup-test');

    $fs->put('db/old.sql.gz', 'old');
    $fs->put('db/fresh.sql.gz', 'fresh');
    // Age the old file past the window.
    touch($fs->path('db/old.sql.gz'), now()->subDays(40)->getTimestamp());

    $deleted = app(DatabaseBackupService::class)->prune('backup-test', 14);

    expect($deleted)->toBe(1);
    $fs->assertMissing('db/old.sql.gz');
    $fs->assertExists('db/fresh.sql.gz');
});

// ── #180: data retention ──────────────────────────────────────────────────────────

it('prunes operational rows older than the configured window', function (): void {
    config(['retention.policies' => [
        'login_history' => ['table' => 'user_login_history', 'column' => 'created_at', 'days' => 180],
    ]]);

    $user = customerUser();
    DB::table('user_login_history')->insert([
        ['user_id' => $user->id, 'ip_address' => '1.1.1.1', 'created_at' => now()->subDays(365)],
        ['user_id' => $user->id, 'ip_address' => '2.2.2.2', 'created_at' => now()->subDays(10)],
    ]);

    $this->artisan('retention:apply')->assertSuccessful();

    // The year-old row is gone; the recent one stays.
    expect(DB::table('user_login_history')->count())->toBe(1)
        ->and(DB::table('user_login_history')->where('ip_address', '2.2.2.2')->exists())->toBeTrue();
});

it('dry-run deletes nothing', function (): void {
    config(['retention.policies' => [
        'login_history' => ['table' => 'user_login_history', 'column' => 'created_at', 'days' => 30],
    ]]);

    $user = customerUser();
    DB::table('user_login_history')->insert(['user_id' => $user->id, 'ip_address' => '9.9.9.9', 'created_at' => now()->subDays(365)]);

    $this->artisan('retention:apply', ['--dry-run' => true])
        ->expectsOutputToContain('dry-run')
        ->assertSuccessful();

    expect(DB::table('user_login_history')->count())->toBe(1);
});

it('honours a 0-day window as keep-forever', function (): void {
    config(['retention.policies' => [
        'login_history' => ['table' => 'user_login_history', 'column' => 'created_at', 'days' => 0],
    ]]);

    $user = customerUser();
    DB::table('user_login_history')->insert(['user_id' => $user->id, 'ip_address' => '9.9.9.9', 'created_at' => now()->subYears(5)]);

    $this->artisan('retention:apply')->assertSuccessful();

    expect(DB::table('user_login_history')->count())->toBe(1);
});

it('respects an extra where-constraint (only resolved incidents)', function (): void {
    config(['retention.policies' => [
        'resolved_incidents' => [
            'table' => 'service_health_incidents', 'column' => 'resolved_at', 'days' => 90,
            'where' => ['status' => 'resolved'],
        ],
    ]]);

    $service = \App\Domains\Provisioning\Models\Service::factory()->create(['customer_id' => customerUser()->customer->id]);

    // Old resolved → pruned; old but still open → kept.
    DB::table('service_health_incidents')->insert([
        ['service_id' => $service->id, 'title' => 'Old resolved', 'status' => 'resolved', 'resolved_at' => now()->subDays(200), 'created_at' => now()->subDays(210), 'updated_at' => now()],
        ['service_id' => $service->id, 'title' => 'Still open',   'status' => 'open',     'resolved_at' => null,                 'created_at' => now()->subDays(210), 'updated_at' => now()],
    ]);

    $this->artisan('retention:apply')->assertSuccessful();

    expect(DB::table('service_health_incidents')->where('status', 'resolved')->count())->toBe(0)
        ->and(DB::table('service_health_incidents')->where('status', 'open')->count())->toBe(1);
});

it('never prunes business records — invoices are out of scope', function (): void {
    // Retention config lists only operational tables; a config typo pointing at
    // invoices would be caught here.
    $tables = collect(config('retention.policies'))->pluck('table');

    expect($tables)->not->toContain('invoices')
        ->and($tables)->not->toContain('orders')
        ->and($tables)->not->toContain('payments')
        ->and($tables)->not->toContain('credit_transactions')
        ->and($tables)->not->toContain('consent_records');
});
