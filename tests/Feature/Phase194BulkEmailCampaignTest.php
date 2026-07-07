<?php

use App\Models\BulkEmailCampaign;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('admin can view bulk email campaign list', function (): void {
    $admin = adminUser();
    BulkEmailCampaign::create([
        'subject'    => 'Test kampaň',
        'body_html'  => '<p>Hello</p>',
        'status'     => 'draft',
        'created_by' => $admin->id,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.bulk-email-campaigns.index'))
        ->assertOk()
        ->assertViewHas('campaigns');
});

it('admin can view create campaign form', function (): void {
    $this->actingAs(adminUser())
        ->get(route('admin.bulk-email-campaigns.create'))
        ->assertOk()
        ->assertViewIs('admin.bulk-email.create');
});

it('admin can create a campaign', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
        ->post(route('admin.bulk-email-campaigns.store'), [
            'subject'   => 'Novinky — červenec 2026',
            'body_html' => '<p>Váš hosting byl aktualizován.</p>',
        ])
        ->assertRedirect(route('admin.bulk-email-campaigns.index'));

    expect(BulkEmailCampaign::where('subject', 'Novinky — červenec 2026')->exists())->toBeTrue();
});

it('admin can delete a draft campaign', function (): void {
    $admin    = adminUser();
    $campaign = BulkEmailCampaign::create(['subject' => 'Smazat', 'body_html' => '<p>x</p>', 'status' => 'draft', 'created_by' => $admin->id]);

    $this->actingAs($admin)
        ->delete(route('admin.bulk-email-campaigns.destroy', $campaign))
        ->assertRedirect();

    expect(BulkEmailCampaign::find($campaign->id))->toBeNull();
});

it('campaign subject is required', function (): void {
    $this->actingAs(adminUser())
        ->post(route('admin.bulk-email-campaigns.store'), ['body_html' => '<p>missing subject</p>'])
        ->assertSessionHasErrors('subject');
});
