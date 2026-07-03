<?php

declare(strict_types=1);

use App\Console\Commands\ProcessGdprErasureRequestsCommand;
use App\Domains\Customer\Models\Customer;
use App\Models\User;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;
use Illuminate\Support\Carbon;

beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class]);
});

it('anonymises a user after the grace period', function (): void {
    $user = customerUser();
    $user->update(['deletion_requested_at' => Carbon::now()->subDays(31)]);

    $this->artisan('gdpr:erase-requested')
        ->assertExitCode(0);

    $fresh = $user->fresh();
    expect($fresh->name)->toBe('[Deleted]');
    expect($fresh->email)->toContain('@deleted.invalid');
    expect($fresh->deletion_requested_at)->toBeNull();
});

it('does not erase a user still inside the grace period', function (): void {
    $originalEmail = 'still-active@example.com';
    $user          = User::factory()->create([
        'email'                  => $originalEmail,
        'deletion_requested_at'  => Carbon::now()->subDays(10),
    ]);

    $this->artisan('gdpr:erase-requested')
        ->assertExitCode(0);

    expect($user->fresh()->email)->toBe($originalEmail);
});

it('dry-run reports without modifying data', function (): void {
    $originalEmail = 'dry-run-user@example.com';
    $user          = User::factory()->create([
        'email'                 => $originalEmail,
        'deletion_requested_at' => Carbon::now()->subDays(31),
    ]);

    $this->artisan('gdpr:erase-requested', ['--dry-run' => true])
        ->assertExitCode(0);

    expect($user->fresh()->email)->toBe($originalEmail);
    expect($user->fresh()->name)->not->toBe('[Deleted]');
});

it('skips users already anonymised (idempotency)', function (): void {
    $user = User::factory()->create([
        'name'                  => '[Deleted]',
        'email'                 => 'deleted_99@deleted.invalid',
        'deletion_requested_at' => Carbon::now()->subDays(60),
    ]);

    $this->artisan('gdpr:erase-requested')
        ->assertExitCode(0);

    // Still anonymous but no error
    expect($user->fresh()->name)->toBe('[Deleted]');
});

it('anonymises customer profile fields too', function (): void {
    $user = customerUser(['email' => 'customer@company.cz', 'phone' => '+420123456789', 'company_name' => 'ACME s.r.o.']);
    $user->update(['deletion_requested_at' => Carbon::now()->subDays(31)]);

    $this->artisan('gdpr:erase-requested')
        ->assertExitCode(0);

    $customer = $user->customer->fresh();
    expect($customer->phone)->toBeNull();
    expect($customer->company_name)->toBeNull();
    expect($customer->email)->toContain('@deleted.invalid');
});

it('respects custom --grace-days option', function (): void {
    $email = 'short-grace@example.com';
    $user  = User::factory()->create([
        'email'                 => $email,
        'deletion_requested_at' => Carbon::now()->subDays(5),
    ]);

    $this->artisan('gdpr:erase-requested', ['--grace-days' => 3])
        ->assertExitCode(0);

    expect($user->fresh()->name)->toBe('[Deleted]');
});

it('reports no accounts when queue is empty', function (): void {
    $this->artisan('gdpr:erase-requested')
        ->expectsOutput('GDPR: no accounts pending erasure.')
        ->assertExitCode(0);
});
