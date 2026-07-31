<?php

declare(strict_types=1);

use App\Domains\Communication\Services\WebPushService;
use App\Listeners\SendWebPushForNotification;
use App\Models\PushSubscription;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Notifications\Notification;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

/** A minimal database notification for exercising the push listener. */
final class PushStubNotification extends Notification
{
    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, string> */
    public function toArray(object $notifiable): array
    {
        return ['title' => 'Faktura zaplacena', 'body' => 'Děkujeme', 'url' => '/panel/faktury/1'];
    }
}

/**
 * Web push (audit 92) — subscription storage + a send path that stays inert
 * until VAPID keys are configured.
 */

function subPayload(string $endpoint = 'https://fcm.googleapis.com/fcm/send/abc'): array
{
    return ['endpoint' => $endpoint, 'publicKey' => 'BPublicKey123', 'authToken' => 'authSecret123'];
}

// ── subscription flow ────────────────────────────────────────────────────────────

it('stores a browser push subscription for the user', function (): void {
    $user = customerUser();

    $this->actingAs($user)
        ->postJson(route('panel.push.subscribe'), subPayload())
        ->assertOk()
        ->assertJson(['ok' => true]);

    expect(PushSubscription::where('user_id', $user->id)->count())->toBe(1);
});

it('re-subscribing the same endpoint updates rather than duplicates', function (): void {
    $user = customerUser();

    $this->actingAs($user)->postJson(route('panel.push.subscribe'), subPayload())->assertOk();
    $this->actingAs($user)->postJson(route('panel.push.subscribe'),
        array_merge(subPayload(), ['authToken' => 'rotatedSecret']))->assertOk();

    expect(PushSubscription::where('user_id', $user->id)->count())->toBe(1)
        ->and(PushSubscription::where('user_id', $user->id)->first()->auth_token)->toBe('rotatedSecret');
});

it('unsubscribes an endpoint', function (): void {
    $user = customerUser();
    $this->actingAs($user)->postJson(route('panel.push.subscribe'), subPayload())->assertOk();

    $this->actingAs($user)
        ->postJson(route('panel.push.unsubscribe'), ['endpoint' => 'https://fcm.googleapis.com/fcm/send/abc'])
        ->assertOk();

    expect(PushSubscription::where('user_id', $user->id)->count())->toBe(0);
});

it('exposes the enabled flag and public key to the browser', function (): void {
    config(['webpush.vapid.public_key' => 'BPub', 'webpush.vapid.private_key' => 'priv']);

    $this->actingAs(customerUser())
        ->getJson(route('panel.push.key'))
        ->assertOk()
        ->assertJson(['enabled' => true, 'publicKey' => 'BPub']);
});

it('prefers admin-vault VAPID keys over env', function (): void {
    config(['webpush.vapid.public_key' => 'EnvPub', 'webpush.vapid.private_key' => 'EnvPriv']);
    \App\Domains\Integrations\Models\IntegrationSetting::create([
        'provider'    => 'web_push',
        'label'       => 'Web push',
        'credentials' => ['subject' => 'mailto:a@b.cz', 'public_key' => 'AdminPub', 'private_key' => 'AdminPriv'],
        'is_active'   => true,
        'mock_mode'   => false,
        'dry_run'     => false,
    ]);

    expect(app(WebPushService::class)->publicKey())->toBe('AdminPub')
        ->and(app(WebPushService::class)->enabled())->toBeTrue();
});

it('generates and stores a VAPID keypair via the console command', function (): void {
    // VAPID keys are EC (prime256v1); some minimal PHP builds can't generate EC
    // keys (no openssl.cnf). Skip there — the command is exercised wherever
    // OpenSSL EC is available (CI/production).
    if (@openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']) === false) {
        $this->markTestSkipped('OpenSSL EC key generation unavailable in this environment.');
    }

    $this->artisan('webpush:vapid', ['--subject' => 'mailto:ops@onhost.cz'])->assertExitCode(0);

    $creds = \App\Domains\Integrations\Models\IntegrationSetting::credentialsFor('web_push');

    // Keys are generated and stored; the private key value is never surfaced to
    // the browser key endpoint.
    expect($creds['public_key'] ?? '')->not->toBe('')
        ->and($creds['private_key'] ?? '')->not->toBe('')
        ->and(app(WebPushService::class)->enabled())->toBeTrue()
        ->and(app(WebPushService::class)->publicKey())->toBe($creds['public_key']);
});

// ── send path ────────────────────────────────────────────────────────────────────

it('sends nothing when VAPID is not configured', function (): void {
    config(['webpush.enabled' => false]);
    $user = customerUser();
    PushSubscription::create(['user_id' => $user->id, 'endpoint' => 'https://x', 'public_key' => 'k', 'auth_token' => 'a']);

    // Inert until an operator sets keys — the subscription flow still worked.
    expect(app(WebPushService::class)->send($user, 'Hi', 'Body'))->toBe(0);
});

it('reports not-enabled without VAPID keys', function (): void {
    config(['webpush.enabled' => true, 'webpush.vapid.public_key' => null, 'webpush.vapid.private_key' => null]);

    expect(app(WebPushService::class)->enabled())->toBeFalse();
});

it('delivers to zero devices when the user has no subscriptions', function (): void {
    config(['webpush.enabled' => true, 'webpush.vapid.public_key' => 'BPub', 'webpush.vapid.private_key' => 'priv']);

    // Enabled, but nobody subscribed → no network call, returns 0.
    expect(app(WebPushService::class)->send(customerUser(), 'Hi', 'Body'))->toBe(0);
});

it('never persists the VAPID private key in the pushed payload config', function (): void {
    // Sanity: the service reads the private key from config, never stores it.
    config(['webpush.vapid.private_key' => 'topsecretprivkey']);
    $sub = PushSubscription::create(['user_id' => customerUser()->id, 'endpoint' => 'https://x', 'public_key' => 'k', 'auth_token' => 'a']);

    expect(json_encode($sub->getAttributes()))->not->toContain('topsecretprivkey');
});

it('mirrors an in-app notification to web push once, using its title and body', function (): void {
    $user = customerUser();

    $spy = Mockery::mock(WebPushService::class);
    $spy->shouldReceive('enabled')->andReturnTrue();
    $spy->shouldReceive('send')
        ->once()
        ->with(Mockery::on(fn ($n) => $n->is($user)), 'Faktura zaplacena', 'Děkujeme', '/panel/faktury/1')
        ->andReturn(1);

    (new SendWebPushForNotification($spy))->handle(
        new NotificationSent($user, new PushStubNotification(), 'database'),
    );
});

it('does not push for the mail channel (only database, to avoid duplicates)', function (): void {
    $spy = Mockery::mock(WebPushService::class);
    $spy->shouldReceive('enabled')->andReturnTrue();
    $spy->shouldNotReceive('send');

    (new SendWebPushForNotification($spy))->handle(
        new NotificationSent(customerUser(), new PushStubNotification(), 'mail'),
    );
});

it('does nothing when web push is disabled', function (): void {
    $spy = Mockery::mock(WebPushService::class);
    $spy->shouldReceive('enabled')->andReturnFalse();
    $spy->shouldNotReceive('send');

    (new SendWebPushForNotification($spy))->handle(
        new NotificationSent(customerUser(), new PushStubNotification(), 'database'),
    );
});

it('ships the service worker', function (): void {
    expect(is_file(public_path('sw.js')))->toBeTrue()
        ->and((string) file_get_contents(public_path('sw.js')))->toContain('push');
});
