<?php

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('admin can view customer segment tags', function (): void {
    $this->actingAs(adminUser())
        ->get(route('admin.customer-segment-tags.index'))
        ->assertOk()
        ->assertViewHas('tags');
});

it('admin can create a segment tag', function (): void {
    $this->actingAs(adminUser())
        ->post(route('admin.customer-segment-tags.store'), [
            'name'        => 'VIP',
            'color'       => '#ff0000',
            'description' => 'High value customers',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('customer_segment_tags', ['name' => 'VIP']);
});

it('admin can delete a segment tag', function (): void {
    $tag = \App\Models\CustomerSegmentTag::create([
        'name'  => 'ToDelete',
        'color' => '#000000',
    ]);

    $this->actingAs(adminUser())
        ->delete(route('admin.customer-segment-tags.destroy', $tag))
        ->assertRedirect();

    $this->assertDatabaseMissing('customer_segment_tags', ['name' => 'ToDelete']);
});

it('guest cannot access segment tags', function (): void {
    $this->get(route('admin.customer-segment-tags.index'))
        ->assertRedirect();
});

it('store rejects duplicate tag name', function (): void {
    \App\Models\CustomerSegmentTag::create([
        'name'  => 'DupTag',
        'color' => '#123456',
    ]);

    $this->actingAs(adminUser())
        ->post(route('admin.customer-segment-tags.store'), [
            'name'  => 'DupTag',
            'color' => '#abcdef',
        ])
        ->assertSessionHasErrors('name');
});
