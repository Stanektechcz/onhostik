<?php

use App\Domains\Provisioning\Models\Service;
use App\Models\ServiceCustomerNote;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('customer can view notes for their own service', function (): void {
    $user    = customerUser();
    $service = Service::factory()->create(['customer_id' => $user->customer->id]);

    $this->actingAs($user)
        ->get(route('panel.service-notes.index', $service))
        ->assertOk()
        ->assertViewHas('notes');
});

it('customer cannot view notes for another customers service', function (): void {
    $user    = customerUser();
    $service = Service::factory()->create();

    $this->actingAs($user)
        ->get(route('panel.service-notes.index', $service))
        ->assertForbidden();
});

it('customer can create a note on their service', function (): void {
    $user    = customerUser();
    $service = Service::factory()->create(['customer_id' => $user->customer->id]);

    $this->actingAs($user)
        ->post(route('panel.service-notes.store', $service), ['content' => 'My note'])
        ->assertRedirect();

    expect(ServiceCustomerNote::where('service_id', $service->id)->where('user_id', $user->id)->count())->toBe(1);
});

it('note content is required and cannot be empty', function (): void {
    $user    = customerUser();
    $service = Service::factory()->create(['customer_id' => $user->customer->id]);

    $this->actingAs($user)
        ->post(route('panel.service-notes.store', $service), ['content' => ''])
        ->assertSessionHasErrors('content');
});

it('customer can delete their own note', function (): void {
    $user    = customerUser();
    $service = Service::factory()->create(['customer_id' => $user->customer->id]);
    $note    = ServiceCustomerNote::create(['service_id' => $service->id, 'user_id' => $user->id, 'content' => 'Delete me']);

    $this->actingAs($user)
        ->delete(route('panel.service-notes.destroy', $note))
        ->assertRedirect();

    expect(ServiceCustomerNote::find($note->id))->toBeNull();
});
