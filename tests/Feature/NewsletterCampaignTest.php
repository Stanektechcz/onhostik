<?php

declare(strict_types=1);

use App\Jobs\SendNewsletterJob;
use App\Models\NewsletterCampaign;
use App\Models\Subscriber;
use Database\Seeders\IntegrationSeeder;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class, IntegrationSeeder::class]);
});

// ── Access control ────────────────────────────────────────────────────────────

it('blocks customers from newsletter index', function (): void {
    $this->actingAs(customerUser())->get(route('admin.newsletter.index'))->assertForbidden();
});

it('allows admin to view newsletter index', function (): void {
    $this->actingAs(adminUser())->get(route('admin.newsletter.index'))->assertOk();
});

it('allows admin to view create form', function (): void {
    $this->actingAs(adminUser())->get(route('admin.newsletter.create'))->assertOk();
});

// ── CRUD ─────────────────────────────────────────────────────────────────────

it('admin can create a campaign', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
        ->post(route('admin.newsletter.store'), [
            'subject'   => 'Novinky červen 2026',
            'body_html' => '<p>Vítejte!</p>',
        ])
        ->assertRedirect();

    expect(NewsletterCampaign::where('subject', 'Novinky červen 2026')->exists())->toBeTrue();
});

it('new campaign starts as draft', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)->post(route('admin.newsletter.store'), [
        'subject'   => 'Draft test',
        'body_html' => '<p>Test</p>',
    ]);

    $campaign = NewsletterCampaign::where('subject', 'Draft test')->firstOrFail();
    expect($campaign->status)->toBe('draft');
    expect($campaign->isDraft())->toBeTrue();
});

it('admin can view campaign detail', function (): void {
    $campaign = NewsletterCampaign::create([
        'subject'   => 'Ukázková kampaň',
        'body_html' => '<p>Hello</p>',
        'status'    => 'draft',
        'created_by' => adminUser()->id,
    ]);

    $this->actingAs(adminUser())
        ->get(route('admin.newsletter.show', $campaign))
        ->assertOk()
        ->assertSee('Ukázková kampaň');
});

it('admin can edit a draft campaign', function (): void {
    $admin    = adminUser();
    $campaign = NewsletterCampaign::create([
        'subject'    => 'Starý předmět',
        'body_html'  => '<p>Starý obsah</p>',
        'status'     => 'draft',
        'created_by' => $admin->id,
    ]);

    $this->actingAs($admin)
        ->put(route('admin.newsletter.update', $campaign), [
            'subject'   => 'Nový předmět',
            'body_html' => '<p>Nový obsah</p>',
        ])
        ->assertRedirect();

    expect($campaign->fresh()->subject)->toBe('Nový předmět');
});

it('admin cannot edit a sent campaign', function (): void {
    $campaign = NewsletterCampaign::create([
        'subject'    => 'Odeslaná',
        'body_html'  => '<p>Hotovo</p>',
        'status'     => 'sent',
        'created_by' => adminUser()->id,
    ]);

    $this->actingAs(adminUser())
        ->get(route('admin.newsletter.edit', $campaign))
        ->assertForbidden();
});

it('admin can delete a draft campaign', function (): void {
    $admin    = adminUser();
    $campaign = NewsletterCampaign::create([
        'subject'    => 'Smazat',
        'body_html'  => '<p>X</p>',
        'status'     => 'draft',
        'created_by' => $admin->id,
    ]);

    $this->actingAs($admin)
        ->delete(route('admin.newsletter.destroy', $campaign))
        ->assertRedirect();

    expect(NewsletterCampaign::find($campaign->id))->toBeNull();
});

it('admin cannot delete a sent campaign', function (): void {
    $campaign = NewsletterCampaign::create([
        'subject'    => 'Odeslaná',
        'body_html'  => '<p>X</p>',
        'status'     => 'sent',
        'created_by' => adminUser()->id,
    ]);

    $this->actingAs(adminUser())
        ->delete(route('admin.newsletter.destroy', $campaign))
        ->assertForbidden();
});

// ── Validation ────────────────────────────────────────────────────────────────

it('store requires subject', function (): void {
    $this->actingAs(adminUser())
        ->post(route('admin.newsletter.store'), ['body_html' => '<p>X</p>'])
        ->assertSessionHasErrors('subject');
});

it('store requires body_html', function (): void {
    $this->actingAs(adminUser())
        ->post(route('admin.newsletter.store'), ['subject' => 'Test'])
        ->assertSessionHasErrors('body_html');
});

// ── Send dispatch ─────────────────────────────────────────────────────────────

it('send dispatches jobs for each active subscriber', function (): void {
    Queue::fake();

    $campaign = NewsletterCampaign::create([
        'subject'    => 'Odesílací test',
        'body_html'  => '<p>Hello</p>',
        'status'     => 'draft',
        'created_by' => adminUser()->id,
    ]);

    // Create 3 active confirmed subscribers
    Subscriber::factory()->count(3)->create([
        'is_active'    => true,
        'confirmed_at' => now(),
    ]);

    // Plus 1 unsubscribed — should NOT be targeted
    Subscriber::factory()->create([
        'is_active'       => false,
        'unsubscribed_at' => now(),
    ]);

    $this->actingAs(adminUser())
        ->post(route('admin.newsletter.send', $campaign))
        ->assertRedirect();

    Queue::assertPushed(SendNewsletterJob::class, 3);
    expect($campaign->fresh()->status)->toBe('sending');
    expect($campaign->fresh()->recipients_count)->toBe(3);
});

it('send rejects already-sent campaigns', function (): void {
    $campaign = NewsletterCampaign::create([
        'subject'    => 'Odeslaná',
        'body_html'  => '<p>X</p>',
        'status'     => 'sent',
        'created_by' => adminUser()->id,
    ]);

    $this->actingAs(adminUser())
        ->post(route('admin.newsletter.send', $campaign))
        ->assertForbidden();
});

it('send returns error when no active subscribers', function (): void {
    $campaign = NewsletterCampaign::create([
        'subject'    => 'Žádní odběratelé',
        'body_html'  => '<p>Hello</p>',
        'status'     => 'draft',
        'created_by' => adminUser()->id,
    ]);

    $this->actingAs(adminUser())
        ->post(route('admin.newsletter.send', $campaign))
        ->assertRedirect();

    // Status stays draft — no subscribers
    expect($campaign->fresh()->status)->toBe('draft');
});

// ── Model helpers ─────────────────────────────────────────────────────────────

it('NewsletterCampaign progressPercent returns 0 when recipients_count is 0', function (): void {
    $campaign = new NewsletterCampaign(['recipients_count' => 0, 'sent_count' => 0]);
    expect($campaign->progressPercent())->toBe(0);
});

it('NewsletterCampaign progressPercent calculates correctly', function (): void {
    $campaign = new NewsletterCampaign(['recipients_count' => 100, 'sent_count' => 75]);
    expect($campaign->progressPercent())->toBe(75);
});

it('scopeDraft returns only draft campaigns', function (): void {
    $admin = adminUser();
    NewsletterCampaign::create(['subject' => 'A', 'body_html' => '<p>x</p>', 'status' => 'draft',   'created_by' => $admin->id]);
    NewsletterCampaign::create(['subject' => 'B', 'body_html' => '<p>x</p>', 'status' => 'sent',    'created_by' => $admin->id]);
    NewsletterCampaign::create(['subject' => 'C', 'body_html' => '<p>x</p>', 'status' => 'sending', 'created_by' => $admin->id]);

    expect(NewsletterCampaign::draft()->count())->toBe(1);
    expect(NewsletterCampaign::draft()->first()->subject)->toBe('A');
});
