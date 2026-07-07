<?php

declare(strict_types=1);

use App\Models\MaintenanceWindow;
use App\Notifications\MaintenanceWindowNotification;
use Illuminate\Support\Facades\Notification;

// ── Model ─────────────────────────────────────────────────────────────────────

it('MaintenanceWindow stores customers_notified_at', function (): void {
    $window = MaintenanceWindow::create([
        'title'            => 'Test údržba',
        'message'          => 'Testovací zpráva',
        'starts_at'        => now()->addHours(12),
        'ends_at'          => now()->addHours(14),
        'show_on_frontend' => true,
        'show_on_admin'    => false,
        'color'            => 'warning',
        'is_active'        => true,
    ]);

    $window->update(['customers_notified_at' => now()]);

    expect($window->fresh()->customers_notified_at)->not->toBeNull();
});

// ── Command: sends notification for upcoming windows ─────────────────────────

it('command sends notifications for upcoming windows not yet notified', function (): void {
    Notification::fake();

    $user = customerUser();

    MaintenanceWindow::create([
        'title'            => 'Plánovaná odstávka',
        'message'          => 'Budeme provádět update.',
        'starts_at'        => now()->addHours(8),
        'ends_at'          => now()->addHours(10),
        'show_on_frontend' => true,
        'show_on_admin'    => true,
        'color'            => 'warning',
        'is_active'        => true,
        'customers_notified_at' => null,
    ]);

    $this->artisan('maintenance:send-reminders')
        ->assertSuccessful();

    Notification::assertSentTo($user, MaintenanceWindowNotification::class);
});

it('command skips windows already notified', function (): void {
    Notification::fake();

    customerUser();

    MaintenanceWindow::create([
        'title'                 => 'Už notifikováno',
        'message'               => 'Tato zpráva se neodešle.',
        'starts_at'             => now()->addHours(6),
        'ends_at'               => now()->addHours(8),
        'show_on_frontend'      => true,
        'show_on_admin'         => true,
        'color'                 => 'warning',
        'is_active'             => true,
        'customers_notified_at' => now()->subHour(),
    ]);

    $this->artisan('maintenance:send-reminders')
        ->assertSuccessful();

    Notification::assertNothingSent();
});

it('command skips windows starting more than 24h ahead', function (): void {
    Notification::fake();

    customerUser();

    MaintenanceWindow::create([
        'title'            => 'Daleko v budoucnosti',
        'message'          => 'Ještě není čas.',
        'starts_at'        => now()->addDays(3),
        'ends_at'          => now()->addDays(3)->addHours(2),
        'show_on_frontend' => true,
        'show_on_admin'    => true,
        'color'            => 'info',
        'is_active'        => true,
    ]);

    $this->artisan('maintenance:send-reminders')
        ->assertSuccessful();

    Notification::assertNothingSent();
});

it('command marks window as notified after sending', function (): void {
    Notification::fake();
    customerUser();

    $window = MaintenanceWindow::create([
        'title'            => 'Brzy',
        'message'          => 'Testovací zpráva',
        'starts_at'        => now()->addHours(2),
        'ends_at'          => now()->addHours(4),
        'show_on_frontend' => true,
        'show_on_admin'    => true,
        'color'            => 'danger',
        'is_active'        => true,
    ]);

    $this->artisan('maintenance:send-reminders')
        ->assertSuccessful();

    expect($window->fresh()->customers_notified_at)->not->toBeNull();
});

// ── Notification content ──────────────────────────────────────────────────────

it('MaintenanceWindowNotification includes window title in mail', function (): void {
    $window = new MaintenanceWindow([
        'title'     => 'Velká odstávka',
        'message'   => 'Bude trvat 2 hodiny.',
        'starts_at' => now()->addHours(5),
        'ends_at'   => now()->addHours(7),
    ]);

    $user         = customerUser();
    $notification = new MaintenanceWindowNotification($window);
    $mail         = $notification->toMail($user);

    expect($mail->subject)->toContain('Velká odstávka');
});
