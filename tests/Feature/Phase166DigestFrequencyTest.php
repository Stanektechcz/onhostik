<?php

declare(strict_types=1);

it('customer can view digest frequency page', function (): void {
    $customer = customerUser();

    $this->actingAs($customer)
         ->get(route('panel.account.digest-frequency.show'))
         ->assertOk()
         ->assertViewIs('panel.account.digest-frequency');
});

it('customer can update digest frequency to daily', function (): void {
    $customer = customerUser();

    $this->actingAs($customer)
         ->patch(route('panel.account.digest-frequency.update'), ['digest_frequency' => 'daily'])
         ->assertRedirect();

    $customer->refresh();
    expect($customer->digest_frequency)->toBe('daily');
});

it('customer can set digest frequency to never', function (): void {
    $customer = customerUser();

    $this->actingAs($customer)
         ->patch(route('panel.account.digest-frequency.update'), ['digest_frequency' => 'never'])
         ->assertRedirect();

    $customer->refresh();
    expect($customer->digest_frequency)->toBe('never');
});

it('invalid digest frequency value is rejected', function (): void {
    $customer = customerUser();

    $this->actingAs($customer)
         ->patch(route('panel.account.digest-frequency.update'), ['digest_frequency' => 'hourly'])
         ->assertSessionHasErrors('digest_frequency');
});

it('unauthenticated user cannot access digest frequency', function (): void {
    $this->get(route('panel.account.digest-frequency.show'))
         ->assertRedirect('/login');
});
