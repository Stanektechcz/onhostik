<?php

declare(strict_types=1);

use App\Jobs\SendCampaignToCustomerJob;
use App\Models\NewsletterCampaign;
use Database\Seeders\IntegrationSeeder;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class, IntegrationSeeder::class]);
});

// ── Customer audience targeting ────────────────────────────────────────────────

it('campaign can target customers_all audience', function (): void {
    Queue::fake();

    $admin = adminUser();
    $user1 = customerUser();
    $user2 = customerUser();

    $user1->customer?->update(['email' => 'user1@example.com', 'segment' => 'healthy']);
    $user2->customer?->update(['email' => 'user2@example.com', 'segment' => 'at_risk']);

    $campaign = NewsletterCampaign::create([
        'subject'         => 'Zpráva všem zákazníkům',
        'body_html'       => '<p>Dobrý den!</p>',
        'status'          => 'draft',
        'target_audience' => 'customers_all',
        'created_by'      => $admin->id,
    ]);

    $this->actingAs($admin)
        ->post(route('admin.newsletter.send', $campaign))
        ->assertRedirect();

    Queue::assertPushed(SendCampaignToCustomerJob::class);
    expect($campaign->fresh()->status)->toBe('sending');
});

it('campaign targeting customers_all dispatches job to each customer', function (): void {
    Queue::fake();

    $admin = adminUser();

    $u1 = customerUser();
    $u2 = customerUser();
    $u1->customer?->update(['email' => 'a@test.com']);
    $u2->customer?->update(['email' => 'b@test.com']);

    $campaign = NewsletterCampaign::create([
        'subject'         => 'Všichni',
        'body_html'       => '<p>X</p>',
        'status'          => 'draft',
        'target_audience' => 'customers_all',
        'created_by'      => $admin->id,
    ]);

    $this->actingAs($admin)
        ->post(route('admin.newsletter.send', $campaign))
        ->assertRedirect();

    Queue::assertPushed(SendCampaignToCustomerJob::class, function (SendCampaignToCustomerJob $job): bool {
        return in_array($job->recipientEmail, ['a@test.com', 'b@test.com'], true);
    });
});

it('campaign targeting customers_vip only dispatches to vip segment', function (): void {
    Queue::fake();

    $admin = adminUser();

    $vip    = customerUser();
    $normal = customerUser();

    $vip->customer?->update(['email' => 'vip@test.com', 'segment' => 'vip']);
    $normal->customer?->update(['email' => 'normal@test.com', 'segment' => 'healthy']);

    $campaign = NewsletterCampaign::create([
        'subject'         => 'VIP kampaň',
        'body_html'       => '<p>VIP</p>',
        'status'          => 'draft',
        'target_audience' => 'customers_vip',
        'created_by'      => $admin->id,
    ]);

    $this->actingAs($admin)
        ->post(route('admin.newsletter.send', $campaign))
        ->assertRedirect();

    Queue::assertPushed(SendCampaignToCustomerJob::class, function (SendCampaignToCustomerJob $job): bool {
        return $job->recipientEmail === 'vip@test.com';
    });

    Queue::assertNotPushed(SendCampaignToCustomerJob::class, function (SendCampaignToCustomerJob $job): bool {
        return $job->recipientEmail === 'normal@test.com';
    });
});

it('campaign targeting customers_at_risk filters by segment', function (): void {
    Queue::fake();

    $admin   = adminUser();
    $atRisk  = customerUser();
    $healthy = customerUser();

    $atRisk->customer?->update(['email' => 'risk@test.com', 'segment' => 'at_risk']);
    $healthy->customer?->update(['email' => 'ok@test.com', 'segment' => 'healthy']);

    $campaign = NewsletterCampaign::create([
        'subject'         => 'At-risk kampaň',
        'body_html'       => '<p>X</p>',
        'status'          => 'draft',
        'target_audience' => 'customers_at_risk',
        'created_by'      => $admin->id,
    ]);

    $this->actingAs($admin)
        ->post(route('admin.newsletter.send', $campaign))
        ->assertRedirect();

    Queue::assertPushed(SendCampaignToCustomerJob::class, function (SendCampaignToCustomerJob $job): bool {
        return $job->recipientEmail === 'risk@test.com';
    });

    Queue::assertNotPushed(SendCampaignToCustomerJob::class, function (SendCampaignToCustomerJob $job): bool {
        return $job->recipientEmail === 'ok@test.com';
    });
});

it('send to customers with empty segment returns error redirect', function (): void {
    $admin = adminUser();

    $campaign = NewsletterCampaign::create([
        'subject'         => 'VIP prázdno',
        'body_html'       => '<p>X</p>',
        'status'          => 'draft',
        'target_audience' => 'customers_vip',
        'created_by'      => $admin->id,
    ]);

    $this->actingAs($admin)
        ->post(route('admin.newsletter.send', $campaign))
        ->assertRedirect();

    // Status stays draft when no customers match
    expect($campaign->fresh()->status)->toBe('draft');
});

// ── CUSTOMER_AUDIENCES constant ────────────────────────────────────────────────

it('NewsletterCampaign CUSTOMER_AUDIENCES constant contains expected audiences', function (): void {
    expect(NewsletterCampaign::CUSTOMER_AUDIENCES)
        ->toContain('customers_all')
        ->toContain('customers_vip')
        ->toContain('customers_healthy')
        ->toContain('customers_at_risk')
        ->toContain('customers_churned');
});

it('campaign can be created with target_audience', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)->post(route('admin.newsletter.store'), [
        'subject'         => 'Segmentovaná',
        'body_html'       => '<p>Obsah</p>',
        'target_audience' => 'customers_vip',
    ])->assertRedirect();

    $campaign = NewsletterCampaign::where('subject', 'Segmentovaná')->firstOrFail();
    expect($campaign->target_audience)->toBe('customers_vip');
});

it('campaign recipients_count is set on send', function (): void {
    Queue::fake();

    $admin = adminUser();
    $user  = customerUser();
    $user->customer?->update(['email' => 'c@test.com']);

    $campaign = NewsletterCampaign::create([
        'subject'         => 'Count test',
        'body_html'       => '<p>X</p>',
        'status'          => 'draft',
        'target_audience' => 'customers_all',
        'created_by'      => $admin->id,
    ]);

    $this->actingAs($admin)->post(route('admin.newsletter.send', $campaign))->assertRedirect();

    expect($campaign->fresh()->recipients_count)->toBeGreaterThanOrEqual(1);
});

it('markSent transitions sending to sent', function (): void {
    $admin = adminUser();

    $campaign = NewsletterCampaign::create([
        'subject'    => 'Odeslat',
        'body_html'  => '<p>X</p>',
        'status'     => 'sending',
        'created_by' => $admin->id,
    ]);

    $this->actingAs($admin)
        ->post(route('admin.newsletter.mark-sent', $campaign))
        ->assertRedirect();

    expect($campaign->fresh()->status)->toBe('sent');
});
