<?php

declare(strict_types=1);

use App\Domains\Compliance\Enums\GdprRequestStatus;
use App\Domains\Compliance\Enums\GdprRequestType;
use App\Domains\Compliance\Models\GdprRequest;
use App\Domains\Customer\Models\Customer;
use App\Models\User;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class]);
});

// ── Enum helpers ───────────────────────────────────────────────────────────────

it('GdprRequestType has correct values and labels', function (): void {
    expect(GdprRequestType::Export->value)->toBe('export');
    expect(GdprRequestType::Deletion->value)->toBe('deletion');
    expect(GdprRequestType::Export->label())->toBe('Export dat');
    expect(GdprRequestType::Deletion->label())->toBe('Smazání účtu');
    expect(GdprRequestType::Export->color())->toBe('info');
    expect(GdprRequestType::Deletion->color())->toBe('danger');
});

it('GdprRequestStatus has correct values and labels', function (): void {
    expect(GdprRequestStatus::Pending->label())->toBe('Čeká');
    expect(GdprRequestStatus::Completed->label())->toBe('Dokončeno');
    expect(GdprRequestStatus::Rejected->label())->toBe('Zamítnuto');
    expect(GdprRequestStatus::Pending->color())->toBe('warning');
    expect(GdprRequestStatus::Completed->color())->toBe('success');
});

// ── Model helpers ──────────────────────────────────────────────────────────────

it('GdprRequest isPending and isCompleted work correctly', function (): void {
    $customer = Customer::factory()->create();
    $req = GdprRequest::create([
        'customer_id' => $customer->id,
        'type'        => GdprRequestType::Export,
        'status'      => GdprRequestStatus::Pending,
    ]);

    expect($req->isPending())->toBeTrue();
    expect($req->isCompleted())->toBeFalse();

    $req->update(['status' => GdprRequestStatus::Completed]);
    expect($req->fresh()->isPending())->toBeFalse();
    expect($req->fresh()->isCompleted())->toBeTrue();
});

it('Customer gdprRequests relation works', function (): void {
    $customer = Customer::factory()->create();
    GdprRequest::create([
        'customer_id' => $customer->id,
        'type'        => GdprRequestType::Deletion,
        'status'      => GdprRequestStatus::Pending,
    ]);

    expect($customer->gdprRequests()->count())->toBe(1);
});

// ── Panel Compliance index ─────────────────────────────────────────────────────

it('compliance index requires auth', function (): void {
    $this->get(route('panel.compliance.index'))
         ->assertRedirect(route('login'));
});

it('compliance index is accessible to authenticated users', function (): void {
    $user = User::factory()->create();
    Customer::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
         ->get(route('panel.compliance.index'))
         ->assertOk()
         ->assertViewIs('panel.compliance.index');
});

// ── Panel export request ───────────────────────────────────────────────────────

it('user can request data export', function (): void {
    $user     = User::factory()->create();
    $customer = Customer::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
         ->post(route('panel.compliance.export'))
         ->assertRedirect()
         ->assertSessionHas('status');

    $this->assertDatabaseHas('gdpr_requests', [
        'customer_id' => $customer->id,
        'type'        => 'export',
        'status'      => 'completed',
    ]);
});

it('user cannot create duplicate export request', function (): void {
    $user     = User::factory()->create();
    $customer = Customer::factory()->create(['user_id' => $user->id]);

    GdprRequest::create([
        'customer_id' => $customer->id,
        'type'        => GdprRequestType::Export,
        'status'      => GdprRequestStatus::Pending,
    ]);

    $this->actingAs($user)
         ->post(route('panel.compliance.export'))
         ->assertSessionHasErrors('type');

    expect($customer->gdprRequests()->count())->toBe(1);
});

// ── Panel deletion request ─────────────────────────────────────────────────────

it('user can request account deletion', function (): void {
    $user     = User::factory()->create();
    $customer = Customer::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
         ->post(route('panel.compliance.deletion'))
         ->assertRedirect()
         ->assertSessionHas('status');

    $this->assertDatabaseHas('gdpr_requests', [
        'customer_id' => $customer->id,
        'type'        => 'deletion',
        'status'      => 'pending',
    ]);
});

it('user cannot create duplicate deletion request', function (): void {
    $user     = User::factory()->create();
    $customer = Customer::factory()->create(['user_id' => $user->id]);

    GdprRequest::create([
        'customer_id' => $customer->id,
        'type'        => GdprRequestType::Deletion,
        'status'      => GdprRequestStatus::Pending,
    ]);

    $this->actingAs($user)
         ->post(route('panel.compliance.deletion'))
         ->assertSessionHasErrors('type');
});

// ── Admin Compliance index ─────────────────────────────────────────────────────

it('admin compliance index is accessible to admin', function (): void {
    Role::findOrCreate('admin', 'web');
    $admin = User::factory()->create(['two_factor_confirmed_at' => now()]);
    $admin->assignRole('admin');

    $this->actingAs($admin)
         ->get(route('admin.compliance.index'))
         ->assertOk()
         ->assertViewIs('admin.compliance.index');
});

it('admin compliance index is forbidden to regular users', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user)
         ->get(route('admin.compliance.index'))
         ->assertForbidden();
});

// ── Admin approve / reject ─────────────────────────────────────────────────────

it('admin can approve a GDPR deletion request', function (): void {
    Role::findOrCreate('admin', 'web');
    $admin    = User::factory()->create(['two_factor_confirmed_at' => now()]);
    $admin->assignRole('admin');

    $customer = Customer::factory()->create();
    $req      = GdprRequest::create([
        'customer_id' => $customer->id,
        'type'        => GdprRequestType::Deletion,
        'status'      => GdprRequestStatus::Pending,
    ]);

    $this->actingAs($admin)
         ->patch(route('admin.compliance.approve', $req), ['admin_note' => 'Schváleno'])
         ->assertRedirect();

    expect($req->fresh()->status)->toBe(GdprRequestStatus::Completed);
    expect($req->fresh()->admin_note)->toBe('Schváleno');
    expect($req->fresh()->completed_at)->not->toBeNull();
});

it('admin can reject a GDPR request', function (): void {
    Role::findOrCreate('admin', 'web');
    $admin    = User::factory()->create(['two_factor_confirmed_at' => now()]);
    $admin->assignRole('admin');

    $customer = Customer::factory()->create();
    $req      = GdprRequest::create([
        'customer_id' => $customer->id,
        'type'        => GdprRequestType::Deletion,
        'status'      => GdprRequestStatus::Pending,
    ]);

    $this->actingAs($admin)
         ->patch(route('admin.compliance.reject', $req), ['admin_note' => 'Aktivní služby'])
         ->assertRedirect();

    expect($req->fresh()->status)->toBe(GdprRequestStatus::Rejected);
    expect($req->fresh()->admin_note)->toBe('Aktivní služby');
});

// ── Data export download ───────────────────────────────────────────────────────

it('owner can download their data export', function (): void {
    $user     = User::factory()->create();
    $customer = Customer::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)
         ->get(route('panel.compliance.download', $customer));

    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/json');

    $json = json_decode($response->getContent(), true);
    expect($json)->toHaveKey('customer');
    expect($json)->toHaveKey('exported_at');
});

it('user cannot download another customers data export', function (): void {
    $user1     = User::factory()->create();
    Customer::factory()->create(['user_id' => $user1->id]);

    $user2     = User::factory()->create();
    $customer2 = Customer::factory()->create(['user_id' => $user2->id]);

    $this->actingAs($user1)
         ->get(route('panel.compliance.download', $customer2))
         ->assertForbidden();
});
