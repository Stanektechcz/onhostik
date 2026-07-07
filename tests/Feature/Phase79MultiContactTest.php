<?php

declare(strict_types=1);

use App\Domains\Customer\Models\Customer;
use App\Domains\Customer\Models\CustomerContact;
use App\Http\Controllers\Admin\CustomerContactController;
use Database\Seeders\IntegrationSeeder;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;

beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class, IntegrationSeeder::class]);
});

// ── CustomerContact model ─────────────────────────────────────────────────────

it('roleLabel returns correct Czech label', function (): void {
    $contact = new CustomerContact(['role' => 'billing']);
    expect($contact->roleLabel())->toBe('Fakturační');

    $contact->role = 'technical';
    expect($contact->roleLabel())->toBe('Technický');
});

it('ROLES constant has all required keys', function (): void {
    expect(CustomerContact::ROLES)->toHaveKey('general')
        ->and(CustomerContact::ROLES)->toHaveKey('billing')
        ->and(CustomerContact::ROLES)->toHaveKey('technical')
        ->and(CustomerContact::ROLES)->toHaveKey('manager');
});

// ── Customer relation ─────────────────────────────────────────────────────────

it('customer can have multiple contacts', function (): void {
    $customer = customerUser()->customer;

    $customer->contacts()->createMany([
        ['name' => 'Jan Novák', 'email' => 'jan@example.com', 'role' => 'billing'],
        ['name' => 'Eva Dvořák', 'email' => 'eva@example.com', 'role' => 'technical'],
    ]);

    expect($customer->contacts()->count())->toBe(2);
});

// ── Admin routes ──────────────────────────────────────────────────────────────

it('admin can view contacts page for a customer', function (): void {
    $admin    = adminUser();
    $customer = customerUser()->customer;

    $this->actingAs($admin)
        ->get(route('admin.customer-contacts.index', $customer))
        ->assertOk()
        ->assertSee('Kontakty');
});

it('admin can add a contact to a customer', function (): void {
    $admin    = adminUser();
    $customer = customerUser()->customer;

    $this->actingAs($admin)
        ->post(route('admin.customer-contacts.store', $customer), [
            'name'                   => 'Billing Person',
            'email'                  => 'billing@company.com',
            'phone'                  => '+420 123 456 789',
            'role'                   => 'billing',
            'receives_invoices'      => 1,
            'receives_notifications' => 0,
            'is_primary'             => 0,
        ])
        ->assertRedirect(route('admin.customer-contacts.index', $customer));

    expect(CustomerContact::where('email', 'billing@company.com')->exists())->toBeTrue();
});

it('contact creation validates required fields', function (): void {
    $admin    = adminUser();
    $customer = customerUser()->customer;

    $this->actingAs($admin)
        ->post(route('admin.customer-contacts.store', $customer), [])
        ->assertSessionHasErrors(['name', 'email', 'role']);
});

it('admin can update a contact', function (): void {
    $admin    = adminUser();
    $customer = customerUser()->customer;
    $contact  = $customer->contacts()->create([
        'name'  => 'Old Name',
        'email' => 'old@example.com',
        'role'  => 'general',
    ]);

    $this->actingAs($admin)
        ->put(route('admin.customer-contacts.update', [$customer, $contact]), [
            'name'  => 'New Name',
            'email' => 'new@example.com',
            'role'  => 'technical',
        ])
        ->assertRedirect(route('admin.customer-contacts.index', $customer));

    expect($contact->fresh()->name)->toBe('New Name')
        ->and($contact->fresh()->email)->toBe('new@example.com')
        ->and($contact->fresh()->role)->toBe('technical');
});

it('admin can delete a contact', function (): void {
    $admin    = adminUser();
    $customer = customerUser()->customer;
    $contact  = $customer->contacts()->create([
        'name'  => 'To Delete',
        'email' => 'delete@example.com',
        'role'  => 'general',
    ]);

    $this->actingAs($admin)
        ->delete(route('admin.customer-contacts.destroy', [$customer, $contact]))
        ->assertRedirect(route('admin.customer-contacts.index', $customer));

    expect(CustomerContact::find($contact->id))->toBeNull();
});

it('setting is_primary on new contact demotes old primary', function (): void {
    $admin    = adminUser();
    $customer = customerUser()->customer;

    $first = $customer->contacts()->create([
        'name'       => 'First',
        'email'      => 'first@example.com',
        'role'       => 'general',
        'is_primary' => true,
    ]);

    $this->actingAs($admin)
        ->post(route('admin.customer-contacts.store', $customer), [
            'name'       => 'Second',
            'email'      => 'second@example.com',
            'role'       => 'billing',
            'is_primary' => 1,
        ])
        ->assertRedirect();

    expect($first->fresh()->is_primary)->toBeFalse();
    expect($customer->contacts()->where('is_primary', true)->count())->toBe(1);
});

it('non-admin cannot access customer contacts', function (): void {
    $customer = customerUser()->customer;
    $user     = customerUser();

    $this->actingAs($user)
        ->get(route('admin.customer-contacts.index', $customer))
        ->assertStatus(403);
});

// ── invoiceEmails helper ──────────────────────────────────────────────────────

it('invoiceEmails returns primary email plus receives_invoices contacts', function (): void {
    $customer = customerUser()->customer;

    $customer->contacts()->create([
        'name'              => 'Billing',
        'email'             => 'billing@company.com',
        'role'              => 'billing',
        'receives_invoices' => true,
    ]);
    $customer->contacts()->create([
        'name'              => 'Technical',
        'email'             => 'tech@company.com',
        'role'              => 'technical',
        'receives_invoices' => false,
    ]);

    $emails = CustomerContactController::invoiceEmails($customer);

    expect($emails)->toContain('billing@company.com')
        ->and($emails)->not->toContain('tech@company.com');
});
