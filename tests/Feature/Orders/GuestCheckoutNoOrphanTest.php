<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Onhost\Domain\Catalog\Models\Product;
use Onhost\Domain\Catalog\Models\PromoCode;
use Onhost\Domain\Identity\Models\EmailVerificationToken;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Identity\Notifications\GuestAccountNotification;
use Onhost\Domain\Orders\Models\Order;
use Onhost\Domain\Orders\Models\Quote;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Payments\Models\PaymentIntent;
use Onhost\Platform\Audit\AuditEvent;
use Onhost\Platform\Outbox\OutboxMessage;

/*
 * TASK-0236 (follow-up of W8): a refused guest checkout leaves nothing behind. W8 asked the catalogue before the account was
 * created, but the rest of the quote (a CDN add-on without a CDN account, an unknown location, an invalid promo code, a TLD
 * that is not offered …) and the consents of the order still refused AFTER the account existed: the person got no order, no
 * set-password mail, and the next try said `account_exists`. The guest checkout now rehearses the whole quote — and the
 * consents the order will ask for — before the account exists, inside a transaction that is always rolled back.
 */

beforeEach(function () {
    $this->seed([CatalogSeeder::class, TaxRuleSeeder::class, LegalEntitySeeder::class]);
    Notification::fake();
    Mail::fake();
});

function noOrphanPayload(string $email, array $overrides = [], ?array $items = null): array
{
    $payload = array_replace_recursive([
        'customer' => ['email' => $email, 'name' => 'Olga Orphanová', 'country' => 'CZ', 'street' => 'Krátká 3', 'city' => 'Brno', 'postal_code' => '602 00'],
        'commit_months' => 1, 'currency' => 'CZK',
        'consents' => ['terms' => ['version' => '2026-09'], 'privacy' => [], 'dpa' => [], 'withdrawal_waiver' => [], 'registrar_terms' => ['person' => 'Olga Orphanová'], 'registry_terms_cz' => ['person' => 'Olga Orphanová']],
        'payment' => ['mode' => 'bank'], 'terms' => true,
    ], $overrides);
    $payload['items'] = $items ?? [['line_id' => 'web', 'product_key' => 'web-hosting', 'plan_key' => 'start']];

    return $payload;
}

function noOrphanPost(object $test, array $payload, string $key)
{
    return $test->withHeaders(['Referer' => 'http://localhost', 'Accept-Language' => 'cs', 'Idempotency-Key' => $key])->postJson('/v1/checkout/guest', $payload);
}

/** @return array<string,int> */
function noOrphanCounts(): array
{
    return [
        'users' => User::query()->count(), 'organizations' => Organization::query()->count(), 'orders' => Order::query()->count(),
        'intents' => PaymentIntent::query()->count(), 'quotes' => Quote::query()->count(), 'outbox' => OutboxMessage::query()->count(),
        'tokens' => EmailVerificationToken::query()->count(), 'registrations' => AuditEvent::query()->where('action', 'auth.register')->count(),
    ];
}

it('leaves no account, organization, order, intent, mail or event behind when the quote refuses, and the same e-mail orders once the cart is fixed', function (array $refusedItems, array $refusedOverrides, int $status, string $error, ?Closure $arrange) {
    if ($arrange !== null) {
        $arrange();
    }
    $email = 'olga@orphan.cz';
    $before = noOrphanCounts();

    $refused = noOrphanPost($this, noOrphanPayload($email, $refusedOverrides, $refusedItems), 'no-orphan-refused')->assertStatus($status);

    expect($refused->json('error'))->toBe($error)
        ->and(User::query()->where('email', $email)->exists())->toBeFalse()
        ->and(noOrphanCounts())->toBe($before); // nothing written: no user, organization, quote, order, intent, token, audit row or outbox event
    Notification::assertNothingSent();
    Mail::assertNothingOutgoing();
    $this->assertGuest('web');

    // the same person fixes the cart and gets the account and the order — not `account_exists`
    $placed = noOrphanPost($this, noOrphanPayload($email), 'no-orphan-fixed')->assertCreated();
    $user = User::query()->where('email', $email)->firstOrFail();
    expect($placed->json('account.created'))->toBeTrue()
        ->and(Order::query()->where('user_id', $user->id)->count())->toBe(1);
    Notification::assertSentTo($user, GuestAccountNotification::class, fn ($n) => $n->orderNumber === $placed->json('number'));
})->with([
    'CDN add-on without a CDN account' => [
        [['line_id' => 'web', 'product_key' => 'web-hosting', 'plan_key' => 'start'], ['product_key' => 'cdn', 'plan_key' => 'cdn-start', 'config' => ['parent_line_id' => 'web']]],
        [], 409, 'cdn_unavailable',
        function () {
            config(['onhost.cdn.cloudflare.secret_ref' => null]);
            Product::query()->where('key', 'cdn')->update(['state' => 'active']); // on sale, so only the CDN account can refuse it
        },
    ],
    'a location that is not on offer' => [[['line_id' => 'web', 'product_key' => 'web-hosting', 'plan_key' => 'start', 'config' => ['region' => 'mars9']]], [], 422, 'region_unknown', null],
    'an invalid promo code' => [[['line_id' => 'web', 'product_key' => 'web-hosting', 'plan_key' => 'start']], ['promo_code' => 'NO-SUCH-CODE'], 422, 'promo_invalid', null],
    'an exhausted promo code' => [
        [['line_id' => 'web', 'product_key' => 'web-hosting', 'plan_key' => 'start']], ['promo_code' => 'NOORPHAN-SPENT'], 422, 'promo_invalid',
        fn () => PromoCode::query()->create(['code' => 'NOORPHAN-SPENT', 'kind' => 'percent', 'value' => 10, 'max_uses' => 1, 'uses' => 1, 'state' => 'active']),
    ],
    'a TLD that is not offered' => [[['product_key' => 'domain', 'config' => ['fqdn' => 'olga-orphan.xyz', 'period_years' => 1]]], [], 409, 'tld_not_supported', null],
    'a domain period the TLD does not allow' => [[['product_key' => 'domain', 'config' => ['fqdn' => 'olga-orphan.com', 'period_years' => 4]]], [], 422, 'domain_period_invalid', null],
    'a consent the order requires is missing' => [
        [['product_key' => 'domain', 'config' => ['fqdn' => 'olga-orphan.cz', 'period_years' => 1]]],
        ['consents' => ['registrar_terms' => null, 'registry_terms_cz' => null]], 422, 'consent_required', null,
    ],
]);

it('gives a guest from Slovakia without a currency euro prices, the same default the organization gets', function () {
    $placed = noOrphanPost($this, noOrphanPayload('jana@orphan.sk', ['customer' => ['country' => 'SK']] + ['currency' => null]), 'no-orphan-sk')->assertCreated();

    $organization = Organization::query()->where('billing_email', 'jana@orphan.sk')->firstOrFail();
    $order = Order::query()->findOrFail($placed->json('order_id'));
    expect($placed->json('currency'))->toBe('EUR')
        ->and($order->currency)->toBe('EUR')
        ->and($organization->currency)->toBe('EUR')
        ->and($organization->country)->toBe('SK');
});
