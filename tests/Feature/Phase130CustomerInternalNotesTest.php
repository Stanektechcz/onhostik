<?php

declare(strict_types=1);

use App\Models\CustomerInternalNote;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;

beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class]);
});

it('admin can view customer detail page with internal notes section', function (): void {
    $admin    = adminUser();
    $customer = customerUser()->customer;

    $this->actingAs($admin)
         ->get(route('admin.customers.show', $customer))
         ->assertOk()
         ->assertSee('Interní zápisky');
});

it('admin can add an internal note to a customer', function (): void {
    $admin    = adminUser();
    $customer = customerUser()->customer;

    $this->actingAs($admin)
         ->post(route('admin.customers.internal-notes.store', $customer), [
             'content' => 'Zákazník volal ohledně faktury č. 123.',
         ])
         ->assertRedirect();

    expect(CustomerInternalNote::where('customer_id', $customer->id)->count())->toBe(1);
    expect(CustomerInternalNote::where('customer_id', $customer->id)->first()->content)
        ->toBe('Zákazník volal ohledně faktury č. 123.');
});

it('admin can pin a note', function (): void {
    $admin    = adminUser();
    $customer = customerUser()->customer;

    $note = CustomerInternalNote::create([
        'customer_id' => $customer->id,
        'admin_id'    => $admin->id,
        'content'     => 'Test note',
        'is_pinned'   => false,
    ]);

    $this->actingAs($admin)
         ->post(route('admin.customers.internal-notes.pin', [$customer, $note]))
         ->assertRedirect();

    expect($note->fresh()->is_pinned)->toBeTrue();
});

it('admin can delete a note', function (): void {
    $admin    = adminUser();
    $customer = customerUser()->customer;

    $note = CustomerInternalNote::create([
        'customer_id' => $customer->id,
        'admin_id'    => $admin->id,
        'content'     => 'Note to delete',
        'is_pinned'   => false,
    ]);

    $this->actingAs($admin)
         ->delete(route('admin.customers.internal-notes.destroy', [$customer, $note]))
         ->assertRedirect();

    expect(CustomerInternalNote::find($note->id))->toBeNull();
});

it('customer cannot add internal notes', function (): void {
    $user     = customerUser();
    $customer = $user->customer;

    $this->actingAs($user)
         ->post(route('admin.customers.internal-notes.store', $customer), [
             'content' => 'Attempt by customer',
         ])
         ->assertForbidden();
});

it('notes content validation rejects empty content', function (): void {
    $admin    = adminUser();
    $customer = customerUser()->customer;

    $this->actingAs($admin)
         ->post(route('admin.customers.internal-notes.store', $customer), [
             'content' => '',
         ])
         ->assertSessionHasErrors('content');
});
