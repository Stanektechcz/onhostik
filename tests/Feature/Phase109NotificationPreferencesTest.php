<?php

declare(strict_types=1);

use App\Models\User;
use App\Notifications\LateFeeAppliedNotification;
use Database\Factories\InvoiceFactory;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;

beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class]);
});

// ── View renders all controls ─────────────────────────────────────────────────

it('notification-preferences page renders all 6 type rows', function (): void {
    $user = customerUser();

    $response = $this->actingAs($user)
        ->get(route('panel.account.notification-preferences'))
        ->assertOk();

    foreach (['Obnovy služeb', 'Faktury', 'Platby', 'Podpora', 'Zálohy', 'Monitoring'] as $label) {
        $response->assertSee($label);
    }
});

it('notification-preferences page renders both channel columns', function (): void {
    $user = customerUser();

    $this->actingAs($user)
        ->get(route('panel.account.notification-preferences'))
        ->assertOk()
        ->assertSee('E-mail')
        ->assertSee('V aplikaci');
});

it('notification-preferences page shows checkboxes pre-checked based on saved prefs', function (): void {
    $user = customerUser([]);
    $user->update(['notification_preferences' => ['mail' => ['invoice']]]);

    $this->actingAs($user)
        ->get(route('panel.account.notification-preferences'))
        ->assertOk()
        ->assertSeeInOrder(['invoice', 'mail[]']);
});

// ── LateFeeAppliedNotification respects preferences ───────────────────────────

it('LateFeeAppliedNotification via() skips mail when user opts out of invoice mail', function (): void {
    $user    = customerUser();
    $user->update(['notification_preferences' => ['mail' => ['invoice']]]);

    $invoice = InvoiceFactory::new()->create(['customer_id' => $user->customer->id]);

    $notif = new LateFeeAppliedNotification($invoice, 20000);
    $via   = $notif->via($user);

    expect($via)->not->toContain('mail')
        ->and($via)->toContain('database');
});

it('LateFeeAppliedNotification via() skips database when user opts out of invoice database', function (): void {
    $user    = customerUser();
    $user->update(['notification_preferences' => ['database' => ['invoice']]]);

    $invoice = InvoiceFactory::new()->create(['customer_id' => $user->customer->id]);

    $notif = new LateFeeAppliedNotification($invoice, 20000);
    $via   = $notif->via($user);

    expect($via)->toContain('mail')
        ->and($via)->not->toContain('database');
});

it('LateFeeAppliedNotification via() sends both channels when preferences not set', function (): void {
    $user    = User::factory()->create(['notification_preferences' => null]);

    $invoice = InvoiceFactory::new()->create();

    $notif = new LateFeeAppliedNotification($invoice, 20000);
    $via   = $notif->via($user);

    expect($via)->toContain('mail')
        ->and($via)->toContain('database');
});
