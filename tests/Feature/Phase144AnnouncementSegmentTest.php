<?php

declare(strict_types=1);

use App\Domains\Communication\Models\SystemAnnouncement;

it('admin can create announcement with target segment', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
         ->post(route('admin.announcements.store'), [
             'title'          => 'VIP only',
             'body'           => 'For VIP customers',
             'type'           => 'info',
             'target_segment' => 'vip',
         ])
         ->assertRedirect();

    expect(SystemAnnouncement::where('title', 'VIP only')->where('target_segment', 'vip')->exists())->toBeTrue();
});

it('announcement with matching segment is visible to matching customer', function (): void {
    $admin    = adminUser();
    $customer = customerUser(['segment' => 'vip']);

    SystemAnnouncement::create([
        'title'          => 'VIP Announcement',
        'body'           => 'VIP content here',
        'type'           => 'info',
        'is_published'   => true,
        'published_at'   => now(),
        'target_segment' => 'vip',
        'created_by'     => $admin->id,
    ]);

    $this->actingAs($customer)
         ->get(route('panel.dashboard'))
         ->assertOk()
         ->assertSee('VIP Announcement');
});

it('announcement with segment is hidden from non-matching customer', function (): void {
    $admin    = adminUser();
    $customer = customerUser(['segment' => 'healthy']);

    SystemAnnouncement::create([
        'title'          => 'AtRisk Announcement',
        'body'           => 'At risk only',
        'type'           => 'warning',
        'is_published'   => true,
        'published_at'   => now(),
        'target_segment' => 'at_risk',
        'created_by'     => $admin->id,
    ]);

    $response = $this->actingAs($customer)->get(route('panel.dashboard'));
    $response->assertOk();
    expect($response->getContent())->not->toContain('AtRisk Announcement');
});

it('announcement with null segment is visible to all customers', function (): void {
    $admin    = adminUser();
    $customer = customerUser(['segment' => 'churned']);

    SystemAnnouncement::create([
        'title'          => 'Universal Announcement',
        'body'           => 'Everyone sees this',
        'type'           => 'info',
        'is_published'   => true,
        'published_at'   => now(),
        'target_segment' => null,
        'created_by'     => $admin->id,
    ]);

    $this->actingAs($customer)
         ->get(route('panel.dashboard'))
         ->assertOk()
         ->assertSee('Universal Announcement');
});

it('invalid target_segment is rejected', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
         ->post(route('admin.announcements.store'), [
             'title'          => 'Bad',
             'body'           => 'content',
             'type'           => 'info',
             'target_segment' => 'invalid_segment',
         ])
         ->assertSessionHasErrors(['target_segment']);
});
