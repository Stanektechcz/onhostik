<?php

declare(strict_types=1);

use App\Models\WinbackCampaign;
use App\Notifications\WinbackCampaignNotification;
use Illuminate\Support\Facades\Notification;

it('admin can create a winback campaign', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
         ->post(route('admin.winback-campaigns.store'), [
             'name'           => 'Léto 2026 — odchozí zákazníci',
             'target_segment' => 'churned',
             'message'        => 'Vracíme se s nabídkou speciálního sleva pro vás.',
         ])
         ->assertRedirect();

    expect(WinbackCampaign::where('name', 'Léto 2026 — odchozí zákazníci')->exists())->toBeTrue();
});

it('admin can view winback campaigns list', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
         ->get(route('admin.winback-campaigns.index'))
         ->assertOk()
         ->assertSee('Winback');
});

it('admin can send winback campaign to churned customers', function (): void {
    Notification::fake();
    $admin    = adminUser();
    $customer = customerUser(['segment' => 'churned']);

    $campaign = WinbackCampaign::create([
        'name'           => 'Test Winback',
        'target_segment' => 'churned',
        'message'        => 'Speciální nabídka pro vás.',
        'created_by'     => $admin->id,
    ]);

    $this->actingAs($admin)
         ->post(route('admin.winback-campaigns.send', $campaign))
         ->assertRedirect();

    Notification::assertSentTo($customer, WinbackCampaignNotification::class);
});

it('campaign cannot be sent twice', function (): void {
    $admin    = adminUser();

    $campaign = WinbackCampaign::create([
        'name'           => 'Already Sent',
        'target_segment' => 'churned',
        'message'        => 'Bylo odesláno.',
        'created_by'     => $admin->id,
        'sent_at'        => now(),
    ]);

    $this->actingAs($admin)
         ->post(route('admin.winback-campaigns.send', $campaign))
         ->assertSessionHasErrors(['error']);
});

it('message must be at least 10 characters', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
         ->post(route('admin.winback-campaigns.store'), [
             'name'           => 'Short',
             'target_segment' => 'churned',
             'message'        => 'Too short',
         ])
         ->assertSessionHasErrors(['message']);
});
