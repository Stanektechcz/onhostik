<?php

declare(strict_types=1);

use App\Domains\Communication\Models\ProductUpdate;
use App\Domains\Communication\Services\CriticalAlertDispatcher;
use App\Domains\Provisioning\Enums\ServiceStatus;
use App\Domains\Provisioning\Models\Service;
use App\Domains\Shared\Support\IcsCalendar;
use App\Models\MaintenanceWindow;
use App\Models\SecurityEvent;
use App\Notifications\MaintenanceWindowNotification;
use App\Notifications\NewIpLoginNotification;
use Database\Seeders\IntegrationSeeder;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

/**
 * Phase I — notifications and communication.
 */
beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class, IntegrationSeeder::class]);
});

// ── I125: new-IP login detection and e-mail ───────────────────────────────────

it('does not flag an address the user has logged in from before', function (): void {
    /*
     | Regression: the old check compared only against last_login_ip, so a user
     | alternating between two addresses was "on a new IP" at every login,
     | forever. That noise is precisely why the alert could not be e-mailed.
     */
    $user = customerUser();
    $user->update(['last_login_ip' => '10.0.0.9']);

    SecurityEvent::create([
        'user_id'    => $user->id,
        'event_type' => 'login',
        'ip_address' => '127.0.0.1',
        'email'      => $user->email,
    ]);

    Notification::fake();

    event(new \Illuminate\Auth\Events\Login('web', $user, false));

    Notification::assertNothingSentTo($user);
});

it('flags an address the user has never used', function (): void {
    $user = customerUser();
    $user->update(['last_login_ip' => '10.0.0.9']);

    SecurityEvent::create([
        'user_id'    => $user->id,
        'event_type' => 'login',
        'ip_address' => '10.0.0.9',
        'email'      => $user->email,
    ]);

    Notification::fake();

    event(new \Illuminate\Auth\Events\Login('web', $user, false));

    Notification::assertSentTo($user, NewIpLoginNotification::class);
});

it('flags an address again once it has aged out', function (): void {
    $user = customerUser();
    $user->update(['last_login_ip' => '10.0.0.9']);

    // A login from long ago must not keep an attacker's address familiar.
    SecurityEvent::create([
        'user_id'    => $user->id,
        'event_type' => 'login',
        'ip_address' => '127.0.0.1',
        'email'      => $user->email,
    ])->forceFill(['created_at' => now()->subDays(400)])->save();

    Notification::fake();

    event(new \Illuminate\Auth\Events\Login('web', $user, false));

    Notification::assertSentTo($user, NewIpLoginNotification::class);
});

it('sends the new-IP alert in-app but not by mail by default', function (): void {
    $user     = customerUser();
    $channels = (new NewIpLoginNotification('1.2.3.4', 'curl/8'))->via($user);

    expect($channels)->toContain('database')
        ->and($channels)->not->toContain('mail');
});

it('sends the new-IP alert by mail once the user opts in', function (): void {
    $user = customerUser();
    $user->update(['notification_preferences' => ['opt_in' => ['mail' => ['new_ip_login']]]]);

    $channels = (new NewIpLoginNotification('1.2.3.4', 'curl/8'))->via($user->fresh());

    expect($channels)->toContain('mail')->toContain('database');
});

// ── I132: catalogue coverage ──────────────────────────────────────────────────

it('checks a notification type that actually exists in the catalogue', function (): void {
    /*
     | The failure this prevents is silent: wantsNotification() fails open, so
     | a typo'd or missing key means the switch on the preferences screen does
     | nothing and the notification always sends. Nothing errors — the user
     | just finds their choice ignored.
     */
    $catalogue = \App\Domains\Communication\Support\NotificationCatalog::keys();
    $unknown   = [];

    foreach (glob(app_path('Notifications/*.php')) ?: [] as $file) {
        $source = (string) file_get_contents($file);

        if (preg_match_all("/channelsFor\(\\\$notifiable, '([a-z_]+)'/", $source, $m) > 0) {
            foreach ($m[1] as $type) {
                if (! in_array($type, $catalogue, true)) {
                    $unknown[] = basename($file) . " → {$type}";
                }
            }
        }

        if (preg_match_all("/wantsNotification\('([a-z_]+)'/", $source, $m) > 0) {
            foreach ($m[1] as $type) {
                if (! in_array($type, $catalogue, true)) {
                    $unknown[] = basename($file) . " → {$type}";
                }
            }
        }
    }

    expect(array_unique($unknown))->toBe([], 'Neznámé typy notifikací: ' . implode(', ', $unknown));
});

it('offers every catalogue type on the preferences screen', function (): void {
    // The other half of the drift: a type that exists but has no switch.
    $response = $this->actingAs(customerUser())
        ->get(route('panel.account.notification-preferences'))
        ->assertOk();

    foreach (\App\Domains\Communication\Support\NotificationCatalog::keys() as $key) {
        $response->assertSee('value="' . $key . '"', false);
    }
});

// ── I128: notification centre filtering ───────────────────────────────────────

it('filters the notification centre to unread only', function (): void {
    $user = customerUser();

    $user->notify(new \App\Notifications\WelcomeUserNotification($user));
    $user->notify(new \App\Notifications\WelcomeUserNotification($user));

    $user->unreadNotifications()->limit(1)->update(['read_at' => now()]);

    $this->actingAs($user)
        ->get(route('panel.notifications.index', ['status' => 'unread']))
        ->assertOk()
        ->assertViewHas('activeStatus', 'unread');

    expect($user->refresh()->unreadNotifications()->count())->toBe(1);
});

it('ignores an unrecognised status instead of showing an empty inbox', function (): void {
    $user = customerUser();
    $user->notify(new \App\Notifications\WelcomeUserNotification($user));

    // A mistyped query string should not look like "you have no notifications".
    $this->actingAs($user)
        ->get(route('panel.notifications.index', ['status' => 'nonsense']))
        ->assertOk()
        ->assertViewHas('activeStatus', '');
});

// ── I129: maintenance windows ─────────────────────────────────────────────────

it('only notifies customers with a service on the affected server', function (): void {
    Notification::fake();

    $affected   = customerUser();
    $unaffected = customerUser();

    $server = \App\Domains\Provisioning\Models\Server::query()->firstOrFail();

    $service = Service::factory()->create([
        'customer_id' => $affected->customer->id,
        'status'      => ServiceStatus::Active,
        'server_id'   => $server->id,
    ]);

    MaintenanceWindow::create([
        'title'            => 'Výměna disku',
        'message'          => 'Krátká odstávka.',
        'starts_at'        => now()->addHours(3),
        'ends_at'          => now()->addHours(4),
        'is_active'        => true,
        'notify_customers' => true,
        'server_id'        => $service->server_id,
    ]);

    $this->artisan('maintenance:send-reminders')->assertSuccessful();

    // Mailing everyone about one server's downtime is how customers learn to
    // ignore downtime notices.
    Notification::assertSentTo($affected, MaintenanceWindowNotification::class);
    Notification::assertNotSentTo($unaffected, MaintenanceWindowNotification::class);
});

it('notifies everyone when the window is not tied to a server', function (): void {
    Notification::fake();

    $user = customerUser();

    MaintenanceWindow::create([
        'title'            => 'Plošná odstávka',
        'message'          => 'Údržba celé platformy.',
        'starts_at'        => now()->addHours(3),
        'ends_at'          => now()->addHours(4),
        'is_active'        => true,
        'notify_customers' => true,
        'server_id'        => null,
    ]);

    $this->artisan('maintenance:send-reminders')->assertSuccessful();

    Notification::assertSentTo($user, MaintenanceWindowNotification::class);
});

it('stays quiet when the admin switched notifications off', function (): void {
    Notification::fake();

    $user = customerUser();

    MaintenanceWindow::create([
        'title'            => 'Tichá údržba',
        'message'          => 'Bez oznámení.',
        'starts_at'        => now()->addHours(3),
        'ends_at'          => now()->addHours(4),
        'is_active'        => true,
        'notify_customers' => false,
    ]);

    $this->artisan('maintenance:send-reminders')->assertSuccessful();

    // The old command ignored this flag, so the admin's choice did nothing.
    Notification::assertNotSentTo($user, MaintenanceWindowNotification::class);
});

it('does not notify twice about the same window', function (): void {
    Notification::fake();

    customerUser();

    $window = MaintenanceWindow::create([
        'title'            => 'Jednou stačí',
        'message'          => 'Údržba.',
        'starts_at'        => now()->addHours(3),
        'ends_at'          => now()->addHours(4),
        'is_active'        => true,
        'notify_customers' => true,
    ]);

    $this->artisan('maintenance:send-reminders')->assertSuccessful();
    $this->artisan('maintenance:send-reminders')->assertSuccessful();

    Notification::assertSentTimes(MaintenanceWindowNotification::class, 1);
    expect($window->refresh()->customers_notified_at)->not->toBeNull();
});

it('builds a calendar invite a client will accept', function (): void {
    $ics = IcsCalendar::event(
        uid: 'maintenance-1@onhost.cz',
        summary: 'Údržba serveru',
        description: "První řádek\nDruhý; s čárkou, a středníkem",
        start: now()->addDay(),
        end: now()->addDay()->addHour(),
        url: 'https://onhost.cz/panel',
    );

    expect($ics)->toContain('BEGIN:VCALENDAR')
        ->toContain('BEGIN:VEVENT')
        ->toContain('UID:maintenance-1@onhost.cz')
        ->toContain('END:VCALENDAR')
        // CRLF, not LF — some clients reject bare LF outright.
        ->toContain("\r\n")
        // Separators inside a value must be escaped or they terminate it.
        ->toContain('\\;')
        ->toContain('\\,')
        ->toContain('\\n');
});

it('folds long calendar lines without splitting a multi-byte character', function (): void {
    $ics = IcsCalendar::event(
        uid: 'long@onhost.cz',
        summary: str_repeat('Přeložení šablony ', 12),
        description: 'x',
        start: now(),
        end: now()->addHour(),
    );

    foreach (explode("\r\n", $ics) as $line) {
        expect(strlen($line))->toBeLessThanOrEqual(75);
    }

    // Valid UTF-8 throughout — a character split across a fold is what makes
    // a calendar file unreadable rather than merely ugly.
    expect(mb_check_encoding($ics, 'UTF-8'))->toBeTrue();
});

it('attaches the invite to the maintenance e-mail', function (): void {
    $window = MaintenanceWindow::create([
        'title'            => 'S pozvánkou',
        'message'          => 'Údržba.',
        'starts_at'        => now()->addDay(),
        'ends_at'          => now()->addDay()->addHour(),
        'is_active'        => true,
        'notify_customers' => true,
    ]);

    $mail = (new MaintenanceWindowNotification($window))->toMail(customerUser());

    expect($mail->rawAttachments)->not->toBeEmpty()
        ->and($mail->rawAttachments[0]['name'])->toBe('udrzba.ics')
        ->and($mail->rawAttachments[0]['data'])->toContain('BEGIN:VCALENDAR');
});

// ── I126: critical incident routing ───────────────────────────────────────────

it('does nothing when no webhook is configured', function (): void {
    config(['notifications.critical.slack_webhook' => null]);

    Http::fake();

    $dispatcher = new CriticalAlertDispatcher();

    expect($dispatcher->isConfigured())->toBeFalse()
        ->and($dispatcher->send('Test', 'Zpráva'))->toBeFalse();

    Http::assertNothingSent();
});

it('posts a critical alert to the configured webhook', function (): void {
    config(['notifications.critical.slack_webhook' => 'https://hooks.slack.test/abc']);

    Http::fake(['*' => Http::response('ok', 200)]);

    expect((new CriticalAlertDispatcher())->send('Výpadek', 'Databáze nedostupná', ['Server' => 'srv-1']))
        ->toBeTrue();

    // Asserted on the decoded payload, not the raw body: json_encode escapes
    // non-ASCII, so the Czech text never appears literally in the wire format.
    Http::assertSent(function ($request): bool {
        $payload = $request->data();

        return str_contains($payload['text'] ?? '', 'Výpadek')
            && str_contains(json_encode($payload['blocks'] ?? [], JSON_UNESCAPED_UNICODE) ?: '', 'srv-1');
    });
});

it('never lets an alerting failure escalate the incident', function (): void {
    config(['notifications.critical.slack_webhook' => 'https://hooks.slack.test/abc']);

    Http::fake(fn () => throw new \RuntimeException('network down'));

    // Called from the middle of incident handling — throwing here would turn
    // one outage into two.
    expect((new CriticalAlertDispatcher())->send('Výpadek', 'Zpráva'))->toBeFalse();
});

it('keeps the webhook url out of the logged error', function (): void {
    config(['notifications.critical.slack_webhook' => 'https://hooks.slack.test/super-secret-token']);

    \Illuminate\Support\Facades\Log::spy();

    Http::fake(fn () => throw new \RuntimeException('failed to connect'));

    (new CriticalAlertDispatcher())->send('Výpadek', 'Zpráva');

    \Illuminate\Support\Facades\Log::shouldHaveReceived('warning')
        ->withArgs(fn (string $message, array $context): bool => ! str_contains(
            json_encode($context) ?: '',
            'super-secret-token',
        ));
});

// ── I130: in-app changelog ────────────────────────────────────────────────────

it('shows published changelog entries to a customer', function (): void {
    ProductUpdate::factory()->create(['title' => 'Nová správa DNS']);

    $this->actingAs(customerUser())
        ->get(route('panel.changelog.index'))
        ->assertOk()
        ->assertSee('Nová správa DNS');
});

it('hides drafts and scheduled entries', function (): void {
    ProductUpdate::factory()->draft()->create(['title' => 'Ještě neuvolněno']);
    ProductUpdate::factory()->scheduled()->create(['title' => 'Až příští týden']);

    // Publishing a scheduled entry early would leak an unannounced change.
    $this->actingAs(customerUser())
        ->get(route('panel.changelog.index'))
        ->assertOk()
        ->assertDontSee('Ještě neuvolněno')
        ->assertDontSee('Až příští týden');
});

it('marks the changelog as seen once it has been opened', function (): void {
    $user = customerUser();
    ProductUpdate::factory()->create();

    expect($user->changelog_seen_at)->toBeNull();

    $this->actingAs($user)->get(route('panel.changelog.index'))->assertOk();

    expect($user->refresh()->changelog_seen_at)->not->toBeNull();
});

it('still flags entries as new on the visit that marks them seen', function (): void {
    $user = customerUser();
    $user->update(['changelog_seen_at' => now()->subMonth()]);

    ProductUpdate::factory()->create([
        'title'        => 'Čerstvá novinka',
        'published_at' => now()->subDay(),
    ]);

    // Advancing the marker before rendering would make the page mark itself
    // read and appear to contain nothing new.
    $this->actingAs($user)
        ->get(route('panel.changelog.index'))
        ->assertOk()
        ->assertSee('Nové');
});

it('does not greet a brand-new customer with a pile of unseen entries', function (): void {
    ProductUpdate::factory()->count(5)->create();

    expect(ProductUpdate::unseenCountFor(customerUser()))->toBe(0);
});

it('counts entries published since the user last looked', function (): void {
    $user = customerUser();
    $user->update(['changelog_seen_at' => now()->subDays(10)]);

    ProductUpdate::factory()->create(['published_at' => now()->subDays(20)]);
    ProductUpdate::factory()->count(2)->create(['published_at' => now()->subDays(2)]);

    expect(ProductUpdate::unseenCountFor($user->fresh()))->toBe(2);
});

it('lets an admin publish a changelog entry', function (): void {
    $this->actingAs(adminUser())
        ->post(route('admin.product-updates.store'), [
            'title'        => 'Vydali jsme něco nového',
            'body'         => 'Podrobnosti změny.',
            'category'     => 'feature',
            'is_published' => '1',
        ])
        ->assertRedirect();

    $update = ProductUpdate::firstOrFail();

    // Publishing without a date would leave it invisible, since the visible
    // scope requires published_at to be set and in the past.
    expect($update->is_published)->toBeTrue()
        ->and($update->published_at)->not->toBeNull()
        ->and(ProductUpdate::query()->visible()->count())->toBe(1);
});

it('forbids a customer from managing the changelog', function (): void {
    $this->actingAs(customerUser())
        ->get(route('admin.product-updates.index'))
        ->assertForbidden();
});

// ── I127: digest opt-out ──────────────────────────────────────────────────────

it('respects a customer who set the digest to never', function (): void {
    Notification::fake();

    $user = customerUser();
    $user->update(['digest_frequency' => 'never']);

    // Give the digest something to report, so silence is the opt-out and not
    // simply an empty week.
    \App\Domains\Billing\Models\Invoice::factory()->create([
        'customer_id' => $user->customer->id,
        'status'      => \App\Domains\Billing\Enums\InvoiceStatus::Overdue,
    ]);

    $this->artisan('notifications:send-weekly-digest')->assertSuccessful();

    Notification::assertNotSentTo($user, \App\Notifications\WeeklyDigestNotification::class);
});

it('still sends the digest to a customer who wants it', function (): void {
    Notification::fake();

    $user = customerUser();
    $user->update(['digest_frequency' => 'weekly']);

    \App\Domains\Billing\Models\Invoice::factory()->create([
        'customer_id' => $user->customer->id,
        'status'      => \App\Domains\Billing\Enums\InvoiceStatus::Overdue,
    ]);

    $this->artisan('notifications:send-weekly-digest')->assertSuccessful();

    Notification::assertSentTo($user, \App\Notifications\WeeklyDigestNotification::class);
});

it('lets the digest be switched off from the notification preferences too', function (): void {
    $user = customerUser();

    $this->actingAs($user)
        ->put(route('panel.account.notification-preferences.update'), ['mail' => ['invoice']])
        ->assertRedirect();

    expect($user->refresh()->wantsNotification('digest', 'mail'))->toBeFalse();
});
