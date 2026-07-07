<?php

declare(strict_types=1);

use App\Domains\Customer\Models\Customer;

it('admin can set preferred contact method', function (): void {
    $admin    = adminUser();
    $customer = customerUser();

    $this->actingAs($admin)
         ->patch(route('admin.customers.preferred-contact', $customer->customer), [
             'preferred_contact' => 'email',
         ])
         ->assertRedirect();

    expect(Customer::find($customer->customer->id)->preferred_contact)->toBe('email');
});

it('admin can clear preferred contact method', function (): void {
    $admin    = adminUser();
    $customer = customerUser();

    $customer->customer->update(['preferred_contact' => 'phone']);

    $this->actingAs($admin)
         ->patch(route('admin.customers.preferred-contact', $customer->customer), [
             'preferred_contact' => null,
         ])
         ->assertRedirect();

    expect(Customer::find($customer->customer->id)->preferred_contact)->toBeNull();
});

it('invalid preferred contact value is rejected', function (): void {
    $admin    = adminUser();
    $customer = customerUser();

    $this->actingAs($admin)
         ->patch(route('admin.customers.preferred-contact', $customer->customer), [
             'preferred_contact' => 'fax',
         ])
         ->assertSessionHasErrors(['preferred_contact']);
});

it('all valid contact methods are accepted', function (): void {
    $admin    = adminUser();
    $customer = customerUser();

    foreach (['email', 'phone', 'ticket', 'none'] as $method) {
        $this->actingAs($admin)
             ->patch(route('admin.customers.preferred-contact', $customer->customer), [
                 'preferred_contact' => $method,
             ])
             ->assertRedirect();
    }

    expect(Customer::find($customer->customer->id)->preferred_contact)->toBe('none');
});

it('customer cannot update preferred contact', function (): void {
    adminUser();
    $customer = customerUser();

    $this->actingAs($customer)
         ->patch(route('admin.customers.preferred-contact', $customer->customer), [
             'preferred_contact' => 'email',
         ])
         ->assertForbidden();
});
