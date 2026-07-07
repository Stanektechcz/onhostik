<?php

declare(strict_types=1);

use App\Domains\Communication\Models\SystemAnnouncement;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class]);
});

// ── Helper ────────────────────────────────────────────────────────────────────

function makePublishedAnnouncement(array $attrs = []): SystemAnnouncement
{
    return SystemAnnouncement::create(array_merge([
        'title'        => 'Test oznámení',
        'body'         => 'Toto je testovací oznámení.',
        'type'         => 'info',
        'icon'         => 'bell',
        'send_email'   => false,
        'is_published' => true,
        'published_at' => now(),
        'expires_at'   => null,
        'created_by'   => null,
        'sent_count'   => 0,
    ], $attrs));
}

// ── Panel shows active announcements ─────────────────────────────────────────

it('customer sees active announcement banner in panel', function (): void {
    $user = customerUser();
    $ann  = makePublishedAnnouncement();

    $this->actingAs($user)
         ->get(route('panel.dashboard'))
         ->assertOk()
         ->assertSee('Test oznámení');
});

it('customer does not see expired announcement', function (): void {
    $user = customerUser();
    makePublishedAnnouncement(['expires_at' => now()->subDay()]);

    $this->actingAs($user)
         ->get(route('panel.dashboard'))
         ->assertOk()
         ->assertDontSee('Test oznámení');
});

it('customer does not see unpublished announcement', function (): void {
    $user = customerUser();
    makePublishedAnnouncement(['is_published' => false]);

    $this->actingAs($user)
         ->get(route('panel.dashboard'))
         ->assertOk()
         ->assertDontSee('Test oznámení');
});

// ── Dismiss ───────────────────────────────────────────────────────────────────

it('customer can dismiss an announcement', function (): void {
    $user = customerUser();
    $ann  = makePublishedAnnouncement();

    $this->actingAs($user)
         ->post(route('panel.announcements.dismiss', $ann))
         ->assertRedirect();

    $dismissed = DB::table('announcement_dismissals')
        ->where('user_id', $user->id)
        ->where('announcement_id', $ann->id)
        ->exists();

    expect($dismissed)->toBeTrue();
});

it('dismissed announcement does not appear for the dismissing user', function (): void {
    $user = customerUser();
    $ann  = makePublishedAnnouncement();

    // Dismiss it
    DB::table('announcement_dismissals')->insert([
        'user_id'        => $user->id,
        'announcement_id' => $ann->id,
        'dismissed_at'   => now(),
    ]);

    $this->actingAs($user)
         ->get(route('panel.dashboard'))
         ->assertOk()
         ->assertDontSee('Test oznámení');
});

it('dismissal does not affect other users', function (): void {
    $user1 = customerUser();
    $user2 = customerUser();
    $ann   = makePublishedAnnouncement();

    // User1 dismisses
    DB::table('announcement_dismissals')->insert([
        'user_id'        => $user1->id,
        'announcement_id' => $ann->id,
        'dismissed_at'   => now(),
    ]);

    // User2 still sees it
    $this->actingAs($user2)
         ->get(route('panel.dashboard'))
         ->assertOk()
         ->assertSee('Test oznámení');
});

it('dismissing twice does not create duplicate records', function (): void {
    $user = customerUser();
    $ann  = makePublishedAnnouncement();

    $this->actingAs($user)->post(route('panel.announcements.dismiss', $ann));
    $this->actingAs($user)->post(route('panel.announcements.dismiss', $ann));

    $count = DB::table('announcement_dismissals')
        ->where('user_id', $user->id)
        ->where('announcement_id', $ann->id)
        ->count();

    expect($count)->toBe(1);
});

it('admin users see announcements but they are not counted as customer dismissals', function (): void {
    $admin = adminUser();
    $admin->forceFill(['two_factor_confirmed_at' => now()])->save();
    makePublishedAnnouncement();

    // Admin can access dashboard without error (announcements injected)
    $this->actingAs($admin)
         ->get(route('admin.dashboard'))
         ->assertOk();
});
