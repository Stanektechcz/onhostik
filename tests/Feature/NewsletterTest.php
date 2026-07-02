<?php

declare(strict_types=1);

use App\Jobs\SendNewsletterJob;
use App\Models\NewsletterCampaign;
use App\Models\Subscriber;
use Database\Seeders\ProductCatalogSeeder;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    $this->seed(ProductCatalogSeeder::class);
});

function makeCampaign(array $overrides = []): NewsletterCampaign
{
    return NewsletterCampaign::create(array_merge([
        'subject'   => 'Testovací kampaň',
        'body_html' => '<p>Obsah emailu</p>',
        'status'    => 'draft',
    ], $overrides));
}

function makeSubscriber(array $overrides = []): Subscriber
{
    static $seq = 0;
    $seq++;
    return Subscriber::create(array_merge([
        'email'        => "subscriber{$seq}@example.com",
        'locale'       => 'cs',
        'source'       => 'website',
        'is_active'    => true,
        'confirmed_at' => now(),
    ], $overrides));
}

// ── CRUD ──────────────────────────────────────────────────────────────────────

it('admin can create a newsletter campaign', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
        ->post(route('admin.newsletter.store'), [
            'subject'   => 'Novinky z OnHost',
            'body_html' => '<h1>Vítejte</h1><p>Máme pro vás novinky.</p>',
        ])
        ->assertRedirect();

    $campaign = NewsletterCampaign::where('subject', 'Novinky z OnHost')->firstOrFail();
    expect($campaign->status)->toBe('draft')
        ->and($campaign->created_by)->toBe($admin->id);
});

it('admin can update a draft campaign', function (): void {
    $admin    = adminUser();
    $campaign = makeCampaign();

    $this->actingAs($admin)
        ->put(route('admin.newsletter.update', $campaign), [
            'subject'   => 'Aktualizovaný předmět',
            'body_html' => '<p>Nový obsah</p>',
        ])
        ->assertRedirect();

    expect($campaign->fresh()->subject)->toBe('Aktualizovaný předmět');
});

it('admin cannot update a sent campaign', function (): void {
    $admin    = adminUser();
    $campaign = makeCampaign(['status' => 'sent']);

    $this->actingAs($admin)
        ->put(route('admin.newsletter.update', $campaign), [
            'subject'   => 'Pokus o změnu',
            'body_html' => '<p>X</p>',
        ])
        ->assertForbidden();
});

it('admin can delete a draft campaign', function (): void {
    $admin    = adminUser();
    $campaign = makeCampaign(['subject' => 'Ke smazání']);

    $this->actingAs($admin)
        ->delete(route('admin.newsletter.destroy', $campaign))
        ->assertRedirect();

    expect(NewsletterCampaign::find($campaign->id))->toBeNull();
});

it('admin cannot delete a sent campaign', function (): void {
    $admin    = adminUser();
    $campaign = makeCampaign(['status' => 'sent']);

    $this->actingAs($admin)
        ->delete(route('admin.newsletter.destroy', $campaign))
        ->assertForbidden();
});

// ── Send ──────────────────────────────────────────────────────────────────────

it('send dispatches a job for each confirmed subscriber', function (): void {
    Queue::fake();

    $admin = adminUser();
    makeSubscriber();
    makeSubscriber();
    makeSubscriber(['is_active' => false]);  // should be excluded

    $campaign = makeCampaign();

    $this->actingAs($admin)
        ->post(route('admin.newsletter.send', $campaign))
        ->assertRedirect();

    Queue::assertPushed(SendNewsletterJob::class, 2);

    $campaign = $campaign->fresh();
    expect($campaign->status)->toBe('sending')
        ->and($campaign->recipients_count)->toBe(2);
});

it('send fails gracefully with no active subscribers', function (): void {
    $admin    = adminUser();
    $campaign = makeCampaign();

    $this->actingAs($admin)
        ->post(route('admin.newsletter.send', $campaign))
        ->assertRedirect()
        ->assertSessionHas('error');
});

it('SendNewsletterJob increments sent_count and sends mail', function (): void {
    Mail::fake();

    $campaign   = makeCampaign(['status' => 'sending', 'recipients_count' => 1]);
    $subscriber = makeSubscriber();

    $job = new SendNewsletterJob($campaign->id, $subscriber->id);
    $job->handle();

    expect($campaign->fresh()->sent_count)->toBe(1);
    Mail::assertSent(\App\Mail\NewsletterMail::class);
});

it('SendNewsletterJob skips inactive subscribers', function (): void {
    Mail::fake();

    $campaign   = makeCampaign(['status' => 'sending', 'recipients_count' => 1]);
    $subscriber = makeSubscriber(['is_active' => false]);

    (new SendNewsletterJob($campaign->id, $subscriber->id))->handle();

    Mail::assertNothingSent();
    expect($campaign->fresh()->sent_count)->toBe(0);
});
