<?php

declare(strict_types=1);

use App\Domains\Customer\Models\Customer;
use App\Domains\Developer\Models\OAuthApplication;
use App\Models\User;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class]);
});

// ── OAuthApplication model ────────────────────────────────────────────────────

it('OAuthApplication casts correctly', function (): void {
    $customer = Customer::factory()->create();

    $app = OAuthApplication::create([
        'customer_id'        => $customer->id,
        'name'               => 'Test App',
        'client_id'          => \Illuminate\Support\Str::uuid()->toString(),
        'client_secret_hash' => bcrypt('secret'),
        'redirect_uris'      => ['https://example.com/callback'],
        'is_active'          => true,
    ]);

    expect($app->is_active)->toBeTrue();
    expect($app->redirect_uris)->toBeArray()->toHaveCount(1);
    expect($app->redirect_uris[0])->toBe('https://example.com/callback');
});

it('Customer oauthApplications relation works', function (): void {
    $customer = Customer::factory()->create();
    OAuthApplication::create([
        'customer_id'        => $customer->id,
        'name'               => 'App A',
        'client_id'          => \Illuminate\Support\Str::uuid()->toString(),
        'client_secret_hash' => bcrypt('s1'),
        'is_active'          => true,
    ]);

    expect($customer->oauthApplications()->count())->toBe(1);
});

// ── Panel Developer Portal index ───────────────────────────────────────────────

it('developer portal index requires auth', function (): void {
    $this->get(route('panel.developer.index'))
         ->assertRedirect(route('login'));
});

it('developer portal index is accessible to authenticated users', function (): void {
    $user     = User::factory()->create();
    Customer::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
         ->get(route('panel.developer.index'))
         ->assertOk()
         ->assertViewIs('panel.developer-portal.index');
});

it('developer portal shows existing tokens and oauth apps', function (): void {
    $user     = User::factory()->create();
    $customer = Customer::factory()->create(['user_id' => $user->id]);

    $user->createToken('My Token', ['read']);
    OAuthApplication::create([
        'customer_id'        => $customer->id,
        'name'               => 'My App',
        'client_id'          => \Illuminate\Support\Str::uuid()->toString(),
        'client_secret_hash' => bcrypt('x'),
        'is_active'          => true,
    ]);

    $response = $this->actingAs($user)->get(route('panel.developer.index'));
    $response->assertOk()
             ->assertViewHas('tokens')
             ->assertViewHas('oauthApps');

    expect($response->viewData('tokens'))->toHaveCount(1);
    expect($response->viewData('oauthApps'))->toHaveCount(1);
});

// ── Panel store OAuth app ──────────────────────────────────────────────────────

it('user can create an OAuth application', function (): void {
    $user     = User::factory()->create();
    Customer::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
         ->post(route('panel.developer.oauth-apps.store'), [
             'name'          => 'My Integration',
             'redirect_uris' => "https://example.com/cb\nhttps://example.com/cb2",
         ])
         ->assertRedirect()
         ->assertSessionHas('new_secret');

    $this->assertDatabaseHas('oauth_applications', [
        'name'      => 'My Integration',
        'is_active' => 1,
    ]);

    $app = OAuthApplication::where('name', 'My Integration')->first();
    expect($app)->not->toBeNull();
    expect($app->redirect_uris)->toHaveCount(2);
});

it('store validates name is required', function (): void {
    $user = User::factory()->create();
    Customer::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
         ->post(route('panel.developer.oauth-apps.store'), ['name' => ''])
         ->assertSessionHasErrors('name');
});

it('store limits to 10 OAuth applications', function (): void {
    $user     = User::factory()->create();
    $customer = Customer::factory()->create(['user_id' => $user->id]);

    for ($i = 0; $i < 10; $i++) {
        OAuthApplication::create([
            'customer_id'        => $customer->id,
            'name'               => "App {$i}",
            'client_id'          => \Illuminate\Support\Str::uuid()->toString(),
            'client_secret_hash' => bcrypt('s'),
            'is_active'          => true,
        ]);
    }

    $this->actingAs($user)
         ->post(route('panel.developer.oauth-apps.store'), ['name' => 'One Too Many'])
         ->assertSessionHasErrors('name');

    expect(OAuthApplication::where('customer_id', $customer->id)->count())->toBe(10);
});

// ── Panel destroy OAuth app ────────────────────────────────────────────────────

it('owner can delete their OAuth application', function (): void {
    $user     = User::factory()->create();
    $customer = Customer::factory()->create(['user_id' => $user->id]);
    $app      = OAuthApplication::create([
        'customer_id'        => $customer->id,
        'name'               => 'To Delete',
        'client_id'          => \Illuminate\Support\Str::uuid()->toString(),
        'client_secret_hash' => bcrypt('s'),
        'is_active'          => true,
    ]);

    $this->actingAs($user)
         ->delete(route('panel.developer.oauth-apps.destroy', $app))
         ->assertRedirect();

    $this->assertDatabaseMissing('oauth_applications', ['id' => $app->id]);
});

it('user cannot delete another customers OAuth application', function (): void {
    $user1    = User::factory()->create();
    $customer1 = Customer::factory()->create(['user_id' => $user1->id]);
    $user2    = User::factory()->create();
    Customer::factory()->create(['user_id' => $user2->id]);

    $app = OAuthApplication::create([
        'customer_id'        => $customer1->id,
        'name'               => 'Not Mine',
        'client_id'          => \Illuminate\Support\Str::uuid()->toString(),
        'client_secret_hash' => bcrypt('s'),
        'is_active'          => true,
    ]);

    $this->actingAs($user2)
         ->delete(route('panel.developer.oauth-apps.destroy', $app))
         ->assertForbidden();

    $this->assertDatabaseHas('oauth_applications', ['id' => $app->id]);
});

// ── Panel regen secret ─────────────────────────────────────────────────────────

it('owner can regenerate OAuth app secret', function (): void {
    $user     = User::factory()->create();
    $customer = Customer::factory()->create(['user_id' => $user->id]);
    $app      = OAuthApplication::create([
        'customer_id'        => $customer->id,
        'name'               => 'Regen Test',
        'client_id'          => \Illuminate\Support\Str::uuid()->toString(),
        'client_secret_hash' => bcrypt('original-secret'),
        'is_active'          => true,
    ]);

    $oldHash = $app->client_secret_hash;

    $this->actingAs($user)
         ->patch(route('panel.developer.oauth-apps.regen', $app))
         ->assertRedirect()
         ->assertSessionHas('new_secret');

    expect($app->fresh()->client_secret_hash)->not->toBe($oldHash);
});

// ── Admin Developer Portal ─────────────────────────────────────────────────────

it('admin developer portal index is accessible to admin', function (): void {
    Role::findOrCreate('admin', 'web');
    $admin = User::factory()->create(['two_factor_confirmed_at' => now()]);
    $admin->assignRole('admin');

    $this->actingAs($admin)
         ->get(route('admin.developer.index'))
         ->assertOk()
         ->assertViewIs('admin.developer-portal.index');
});

it('admin developer portal index is forbidden to regular users', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user)
         ->get(route('admin.developer.index'))
         ->assertForbidden();
});

it('admin can delete any OAuth application', function (): void {
    Role::findOrCreate('admin', 'web');
    $admin    = User::factory()->create(['two_factor_confirmed_at' => now()]);
    $admin->assignRole('admin');

    $customer = Customer::factory()->create();
    $app      = OAuthApplication::create([
        'customer_id'        => $customer->id,
        'name'               => 'Admin Delete Me',
        'client_id'          => \Illuminate\Support\Str::uuid()->toString(),
        'client_secret_hash' => bcrypt('s'),
        'is_active'          => true,
    ]);

    $this->actingAs($admin)
         ->delete(route('admin.developer.oauth-apps.destroy', $app))
         ->assertRedirect();

    $this->assertDatabaseMissing('oauth_applications', ['id' => $app->id]);
});
