<?php

declare(strict_types=1);

use App\Domains\Monitoring\Models\Monitor;
use Illuminate\Support\Carbon;

it('dry-run lists domains from monitors expiring soon', function (): void {
    Monitor::factory()->create([
        'target'        => 'https://expiring-soon.cz',
        'ssl_expires_at' => Carbon::now()->addDays(5),
        'is_active'     => true,
    ]);

    $this->artisan('ssl:renew', ['--dry-run' => true, '--threshold' => 14])
        ->expectsOutputToContain('expiring-soon.cz')
        ->assertExitCode(0);
});

it('dry-run with explicit domain skips database lookup', function (): void {
    $this->artisan('ssl:renew', ['domain' => 'example.com', '--dry-run' => true])
        ->expectsOutputToContain('example.com')
        ->assertExitCode(0);
});

it('dry-run skips monitors not yet due', function (): void {
    Monitor::factory()->create([
        'target'        => 'https://not-due-yet.cz',
        'ssl_expires_at' => Carbon::now()->addDays(60),
        'is_active'     => true,
    ]);

    $this->artisan('ssl:renew', ['--dry-run' => true, '--threshold' => 14])
        ->expectsOutput('No SSL certificates due for renewal.')
        ->assertExitCode(0);
});

it('dry-run skips inactive monitors', function (): void {
    Monitor::factory()->create([
        'target'        => 'https://inactive-monitor.cz',
        'ssl_expires_at' => Carbon::now()->addDays(3),
        'is_active'     => false,
    ]);

    $this->artisan('ssl:renew', ['--dry-run' => true, '--threshold' => 14])
        ->expectsOutput('No SSL certificates due for renewal.')
        ->assertExitCode(0);
});

it('dry-run deduplicates same domain across multiple monitors', function (): void {
    Monitor::factory()->create([
        'target'        => 'https://shared-domain.cz/http',
        'ssl_expires_at' => Carbon::now()->addDays(5),
        'is_active'     => true,
    ]);
    Monitor::factory()->create([
        'target'        => 'https://shared-domain.cz/ping',
        'ssl_expires_at' => Carbon::now()->addDays(5),
        'is_active'     => true,
    ]);

    $output = [];
    $this->artisan('ssl:renew', ['--dry-run' => true, '--threshold' => 14])
        ->assertExitCode(0);

    // Both monitors resolve to the same hostname — certbot should only be called once
    // We verify via the "renewed" summary line
    $this->artisan('ssl:renew', ['--dry-run' => true, '--threshold' => 14])
        ->expectsOutputToContain('1 renewed')
        ->assertExitCode(0);
});
