<?php

declare(strict_types=1);

use App\Domains\Communication\Models\SystemAnnouncement;
use App\Domains\Communication\Services\AnnouncementService;
use App\Notifications\SystemAnnouncementNotification;
use Database\Seeders\IntegrationSeeder;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class, IntegrationSeeder::class]);
    Notification::fake();
});

// ── SystemAnnouncement model ──────────────────────────────────────────────────

it('typeLabel returns Czech labels', function (): void {
    expect((new SystemAnnouncement(['type' => 'info']))->typeLabel())->toBe('Informace')
        ->and((new SystemAnnouncement(['type' => 'warning']))->typeLabel())->toBe('Varování')
        ->and((new SystemAnnouncement(['type' => 'maintenance']))->typeLabel())->toBe('Maintenance')
        ->and((new SystemAnnouncement(['type' => 'feature']))->typeLabel())->toBe('Novinka');
});

it('isActive returns false for unpublished announcement', function (): void {
    $a = new SystemAnnouncement(['is_published' => false]);
    expect($a->isActive())->toBeFalse();
});

it('isActive returns true for published non-expired announcement', function (): void {
    $a = new SystemAnnouncement([
        'is_published' => true,
        'expires_at'   => null,
    ]);
    expect($a->isActive())->toBeTrue();
});

it('isActive returns false for expired announcement', function (): void {
    $a = new SystemAnnouncement([
        'is_published' => true,
        'expires_at'   => now()->subHour(),
    ]);
    expect($a->isActive())->toBeFalse();
});

// ── AnnouncementService::broadcast ───────────────────────────────────────────

it('broadcast sends notification to all active customers', function (): void {
    $user1 = customerUser();
    $user2 = customerUser();

    $announcement = SystemAnnouncement::create([
        'title'      => 'Test announcement',
        'body'       => 'Hello customers!',
        'type'       => 'info',
        'icon'       => 'bell',
        'send_email' => false,
    ]);

    $count = app(AnnouncementService::class)->broadcast($announcement);

    expect($count)->toBeGreaterThanOrEqual(2);

    Notification::assertSentTo($user1, SystemAnnouncementNotification::class);
    Notification::assertSentTo($user2, SystemAnnouncementNotification::class);
});

it('broadcast marks announcement as published', function (): void {
    customerUser();
    $announcement = SystemAnnouncement::create([
        'title' => 'Pub test',
        'body'  => 'Body',
        'type'  => 'info',
    ]);

    app(AnnouncementService::class)->broadcast($announcement);

    expect($announcement->fresh()->is_published)->toBeTrue()
        ->and($announcement->fresh()->published_at)->not->toBeNull()
        ->and($announcement->fresh()->sent_count)->toBeGreaterThan(0);
});

it('SystemAnnouncementNotification includes database channel', function (): void {
    $announcement = new SystemAnnouncement([
        'title'      => 'Test',
        'body'       => 'Body',
        'type'       => 'info',
        'send_email' => false,
    ]);

    $notification = new SystemAnnouncementNotification($announcement);
    $user         = customerUser();

    expect($notification->via($user))->toContain('database')
        ->and($notification->via($user))->not->toContain('mail');
});

it('SystemAnnouncementNotification includes mail when send_email is true', function (): void {
    $announcement = new SystemAnnouncement([
        'title'      => 'Test',
        'body'       => 'Body',
        'type'       => 'info',
        'send_email' => true,
    ]);

    $notification = new SystemAnnouncementNotification($announcement);
    $user         = customerUser();

    expect($notification->via($user))->toContain('mail');
});

it('toArray includes expected keys', function (): void {
    $announcement = new SystemAnnouncement([
        'title' => 'My Title',
        'body'  => 'My Body',
        'type'  => 'feature',
        'icon'  => 'star',
    ]);

    $data = (new SystemAnnouncementNotification($announcement))->toArray(customerUser());

    expect($data['title'])->toBe('My Title')
        ->and($data['type'])->toBe('announcement')
        ->and($data['color'])->toBe('success'); // feature → success
});

// ── Admin routes ──────────────────────────────────────────────────────────────

it('admin can list announcements', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
        ->get(route('admin.announcements.index'))
        ->assertOk()
        ->assertSee('Systémová oznámení');
});

it('admin can create an announcement', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
        ->post(route('admin.announcements.store'), [
            'title'      => 'Planned maintenance',
            'body'       => 'We will be offline Saturday 02:00-04:00.',
            'type'       => 'maintenance',
            'icon'       => 'tool',
            'send_email' => 0,
        ])
        ->assertRedirect(route('admin.announcements.index'));

    expect(SystemAnnouncement::where('title', 'Planned maintenance')->exists())->toBeTrue();
});

it('admin can publish (broadcast) an announcement', function (): void {
    $admin = adminUser();
    customerUser(); // ensure at least one customer

    $announcement = SystemAnnouncement::create([
        'title'      => 'Broadcast test',
        'body'       => 'Test body',
        'type'       => 'info',
        'created_by' => $admin->id,
    ]);

    $this->actingAs($admin)
        ->post(route('admin.announcements.publish', $announcement))
        ->assertRedirect(route('admin.announcements.index'));

    expect($announcement->fresh()->is_published)->toBeTrue();
    Notification::assertSentTimes(SystemAnnouncementNotification::class, $announcement->fresh()->sent_count);
});

it('admin can delete an announcement', function (): void {
    $admin = adminUser();
    $announcement = SystemAnnouncement::create([
        'title' => 'Delete me',
        'body'  => 'Body',
        'type'  => 'info',
    ]);

    $this->actingAs($admin)
        ->delete(route('admin.announcements.destroy', $announcement))
        ->assertRedirect(route('admin.announcements.index'));

    expect(SystemAnnouncement::find($announcement->id))->toBeNull();
});

it('non-admin cannot manage announcements', function (): void {
    $user = customerUser();

    $this->actingAs($user)
        ->get(route('admin.announcements.index'))
        ->assertStatus(403);
});

// ── Customer notification panel ───────────────────────────────────────────────

it('customer can view notification center', function (): void {
    $user = customerUser();

    $this->actingAs($user)
        ->get(route('panel.notifications.index'))
        ->assertOk();
});

it('customer can mark a notification as read', function (): void {
    $user = customerUser();
    // Insert DB notification directly (Notification::fake() is active)
    $notification = $user->notifications()->create([
        'id'              => \Str::uuid()->toString(),
        'type'            => \App\Notifications\SystemAnnouncementNotification::class,
        'data'            => ['title' => 'Test', 'body' => 'Body', 'type' => 'announcement'],
        'read_at'         => null,
    ]);

    $this->actingAs($user)
        ->post(route('panel.notifications.read', ['id' => $notification->id]))
        ->assertRedirect();

    expect($user->fresh()->unreadNotifications()->count())->toBe(0);
});

it('customer can mark all notifications as read', function (): void {
    $user = customerUser();

    // Insert two DB notifications directly
    foreach (range(1, 2) as $_) {
        $user->notifications()->create([
            'id'      => \Str::uuid()->toString(),
            'type'    => \App\Notifications\SystemAnnouncementNotification::class,
            'data'    => ['title' => 'T', 'body' => 'B', 'type' => 'announcement'],
            'read_at' => null,
        ]);
    }

    expect($user->unreadNotifications()->count())->toBe(2);

    $this->actingAs($user)
        ->post(route('panel.notifications.read-all'))
        ->assertRedirect();

    expect($user->fresh()->unreadNotifications()->count())->toBe(0);
});

it('notification center supports type filter', function (): void {
    $user = customerUser();

    $this->actingAs($user)
        ->get(route('panel.notifications.index', ['type' => 'announcement']))
        ->assertOk();
});
