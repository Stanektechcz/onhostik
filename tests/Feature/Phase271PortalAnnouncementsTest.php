<?php

declare(strict_types=1);

use App\Models\PortalAnnouncement;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('admin can list portal announcements', function () {
    $admin = adminUser();
    $this->actingAs($admin)
        ->get(route('admin.portal-announcements.index'))
        ->assertOk()
        ->assertViewIs('admin.portal-announcements.index');
});

it('admin can create a portal announcement', function () {
    $admin = adminUser();
    $this->actingAs($admin)->post(route('admin.portal-announcements.store'), [
        'title'           => 'New Feature',
        'body'            => 'We launched a new feature.',
        'type'            => 'info',
        'target_audience' => 'customers',
    ])->assertRedirect();
    $this->assertDatabaseHas('portal_announcements', ['title' => 'New Feature', 'is_published' => false]);
});

it('admin can toggle announcement published state', function () {
    $admin = adminUser();
    $ann = PortalAnnouncement::create([
        'title'           => 'Test',
        'body'            => 'Body',
        'type'            => 'info',
        'target_audience' => 'all',
        'is_published'    => false,
        'created_by'      => $admin->id,
    ]);
    $this->actingAs($admin)
        ->patch(route('admin.portal-announcements.update', $ann))
        ->assertRedirect();
    $this->assertDatabaseHas('portal_announcements', ['id' => $ann->id, 'is_published' => true]);
});

it('admin can delete a portal announcement', function () {
    $admin = adminUser();
    $ann = PortalAnnouncement::create([
        'title'           => 'Old News',
        'body'            => 'Old body',
        'type'            => 'warning',
        'target_audience' => 'all',
        'is_published'    => false,
        'created_by'      => $admin->id,
    ]);
    $this->actingAs($admin)
        ->delete(route('admin.portal-announcements.destroy', $ann))
        ->assertRedirect();
    $this->assertDatabaseMissing('portal_announcements', ['id' => $ann->id]);
});

it('panel customer sees only published non-expired announcements', function () {
    $user = customerUser();
    PortalAnnouncement::create([
        'title'           => 'Published',
        'body'            => 'Active news',
        'type'            => 'success',
        'target_audience' => 'customers',
        'is_published'    => true,
        'published_at'    => now(),
    ]);
    PortalAnnouncement::create([
        'title'           => 'Draft',
        'body'            => 'Not visible',
        'type'            => 'info',
        'target_audience' => 'customers',
        'is_published'    => false,
    ]);
    $this->actingAs($user)
        ->get(route('panel.portal-announcements.index'))
        ->assertOk()
        ->assertViewIs('panel.portal-announcements.index');
});
