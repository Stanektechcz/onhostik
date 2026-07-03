<?php

declare(strict_types=1);

use App\Domains\Customer\Models\Customer;
use App\Models\User;
use Spatie\Permission\Models\Role;

it('registers a new user with a customer billing profile', function (): void {
    $response = $this->post('/register', [
        'name'                  => 'Jan Novák',
        'email'                 => 'jan@example.com',
        'password'              => 'Secret-password-123',
        'password_confirmation' => 'Secret-password-123',
    ]);

    $response->assertRedirect('/panel');
    $this->assertAuthenticated();

    $user = User::where('email', 'jan@example.com')->firstOrFail();
    expect($user->hasRole('customer'))->toBeTrue()
        ->and($user->customer)->not->toBeNull()
        ->and($user->customer->email)->toBe('jan@example.com')
        ->and($user->customer->preferred_currency->value)->toBe('CZK');
});

it('logs a user in with valid credentials', function (): void {
    $user = User::factory()->create();
    Customer::factory()->for($user)->create();

    $response = $this->post('/login', [
        'email'    => $user->email,
        'password' => 'password',
    ]);

    $response->assertRedirect('/panel');
    $this->assertAuthenticatedAs($user);
});

it('rejects invalid credentials', function (): void {
    $user = User::factory()->create();

    $response = $this->from('/login')->post('/login', [
        'email'    => $user->email,
        'password' => 'wrong-password',
    ]);

    $response->assertRedirect('/login');
    $this->assertGuest();
});

it('logs a user out', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/logout');

    $this->assertGuest();
});

it('blocks deactivated route access for guests', function (): void {
    $this->get('/panel')->assertRedirect('/login');
});

it('keeps admin area closed to customers', function (): void {
    Role::findOrCreate('customer', 'web');
    $user = User::factory()->create();
    $user->assignRole('customer');
    Customer::factory()->for($user)->create();

    $this->actingAs($user)->get('/admin')->assertForbidden();
});

it('opens admin area to admins', function (): void {
    Role::findOrCreate('admin', 'web');
    $admin = User::factory()->create(['two_factor_confirmed_at' => now()]);
    $admin->assignRole('admin');

    $this->actingAs($admin)->get('/admin')->assertOk();
});
