<?php

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('admin can view promotional banners', function (): void {
    $this->actingAs(adminUser())
        ->get(route('admin.promotional-banners.index'))
        ->assertOk()
        ->assertViewHas('banners');
});

it('admin can create a promotional banner', function (): void {
    $this->actingAs(adminUser())
        ->post(route('admin.promotional-banners.store'), [
            'title'          => 'Summer Sale',
            'body'           => 'Get 20% off',
            'type'           => 'info',
            'placement'      => 'panel_top',
            'is_active'      => 1,
            'is_dismissible' => 1,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('promotional_banners', ['title' => 'Summer Sale']);
});

it('admin can toggle banner active status', function (): void {
    $banner = \App\Models\PromotionalBanner::create([
        'title'          => 'T',
        'body'           => 'B',
        'type'           => 'info',
        'placement'      => 'panel_top',
        'is_active'      => true,
        'is_dismissible' => true,
    ]);

    $this->actingAs(adminUser())
        ->patch(route('admin.promotional-banners.update', $banner), [
            'title'          => 'T',
            'body'           => 'B',
            'type'           => 'info',
            'placement'      => 'panel_top',
            'is_active'      => 0,
            'is_dismissible' => 1,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('promotional_banners', ['is_active' => false]);
});

it('admin can delete a promotional banner', function (): void {
    $banner = \App\Models\PromotionalBanner::create([
        'title'          => 'Delete Me',
        'body'           => 'Body text',
        'type'           => 'info',
        'placement'      => 'panel_top',
        'is_active'      => true,
        'is_dismissible' => true,
    ]);

    $this->actingAs(adminUser())
        ->delete(route('admin.promotional-banners.destroy', $banner))
        ->assertRedirect();

    $this->assertDatabaseMissing('promotional_banners', ['id' => $banner->id]);
});

it('guest cannot access promotional banners', function (): void {
    $this->get(route('admin.promotional-banners.index'))
        ->assertRedirect();
});
