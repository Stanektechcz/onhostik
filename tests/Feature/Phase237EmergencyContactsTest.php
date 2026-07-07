<?php

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('panel user can view emergency contacts', function (): void {
    $user = customerUser();

    $this->actingAs($user)
        ->get(route('panel.emergency-contacts.index'))
        ->assertOk()
        ->assertViewHas('contacts');
});

it('panel user can add an emergency contact', function (): void {
    $user = customerUser();

    $this->actingAs($user)
        ->post(route('panel.emergency-contacts.store'), [
            'name'                 => 'John Doe',
            'email'                => 'john@test.com',
            'phone'                => '+420123456789',
            'relationship'         => 'Manager',
            'notify_on_suspension' => 1,
            'notify_on_expiry'     => 0,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('emergency_contacts', ['email' => 'john@test.com']);
});

it('panel user can delete their emergency contact', function (): void {
    $user    = customerUser();
    $contact = \App\Models\EmergencyContact::create([
        'customer_id'          => $user->customer->id,
        'name'                 => 'Jane',
        'email'                => 'jane@test.com',
        'notify_on_suspension' => false,
        'notify_on_expiry'     => false,
    ]);

    $this->actingAs($user)
        ->delete(route('panel.emergency-contacts.destroy', $contact))
        ->assertRedirect();

    $this->assertDatabaseMissing('emergency_contacts', ['id' => $contact->id]);
});

it('panel user cannot delete another customer contact', function (): void {
    $user      = customerUser();
    $otherUser = customerUser();

    $contact = \App\Models\EmergencyContact::create([
        'customer_id'          => $otherUser->customer->id,
        'name'                 => 'Other',
        'email'                => 'o@test.com',
        'notify_on_suspension' => false,
        'notify_on_expiry'     => false,
    ]);

    $this->actingAs($user)
        ->delete(route('panel.emergency-contacts.destroy', $contact))
        ->assertForbidden();
});

it('guest cannot access emergency contacts', function (): void {
    $this->get(route('panel.emergency-contacts.index'))
        ->assertRedirect();
});
