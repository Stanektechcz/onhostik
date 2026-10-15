<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Illuminate\Support\Facades\Http;
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
use Onhost\Domain\Services\Models\Website;
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

it('lets a business guest with an IČO and a VAT number order: the rehearsal does not stand in the way of a legitimate cart', function () {
    $endpoint = 'https://ec.europa.eu/taxation_customs/vies/rest-api/check-vat-number';
    config(['onhost.vies.enabled' => true, 'onhost.vies.endpoint' => $endpoint, 'onhost.vies.requester_vat_id' => 'CZ12345678']);
    Http::preventStrayRequests();
    Http::fake([
        $endpoint => fn ($request) => Http::response(['countryCode' => $request['countryCode'], 'vatNumber' => $request['vatNumber'], 'requestDate' => '2026-10-14T10:00:00.000Z', 'valid' => true, 'requestIdentifier' => 'WAPINOORPH1', 'name' => 'ORPHAN S.R.O.', 'address' => 'Krátká 3, Brno']),
        'api.pwnedpasswords.com/*' => Http::response(''),
    ]);

    $placed = noOrphanPost($this, noOrphanPayload('firma@orphan.cz', ['customer' => ['company' => 'Orphan s.r.o.', 'ico' => '12345679', 'vat_id' => 'CZ12345679']]), 'no-orphan-b2b')->assertCreated();

    $organization = Organization::query()->where('billing_email', 'firma@orphan.cz')->sole();
    expect($placed->json('account.created'))->toBeTrue()
        ->and($placed->json('account.organization.customer_class'))->toBe('b2b')
        ->and($organization->vat_status)->toBe('valid')
        ->and($organization->vat_checked_number)->toBe('CZ12345679')
        ->and(Order::query()->where('organization_id', $organization->id)->count())->toBe(1);
    Http::assertSent(fn ($request) => $request->url() === $endpoint);
});

it('leaves exactly one quote after a successful guest checkout: the rehearsed one is rolled back', function () {
    $placed = noOrphanPost($this, noOrphanPayload('jedna@orphan.cz'), 'no-orphan-one-quote')->assertCreated();

    $order = Order::query()->findOrFail($placed->json('order_id'));
    expect(Quote::query()->count())->toBe(1)
        ->and(Quote::query()->sole()->organization_id)->toBe($order->organization_id)
        ->and(Quote::query()->sole()->state)->toBe('accepted');
});

it('leaves no account behind when another customer already serves the site name', function () {
    [, $neighbour] = $this->customerWithOrganization();
    $service = featureWebService($neighbour, 'aapanel');
    $service->forceFill(['hostname' => 'soused-orphan.cz', 'desired_spec' => array_merge((array) $service->desired_spec, ['domain' => 'soused-orphan.cz'])])->save();
    Website::query()->create(['service_id' => $service->id, 'domain' => 'soused-orphan.cz', 'aliases' => [], 'executor' => 'aapanel', 'php_version' => '8.3', 'remote_site_id' => 77, 'remote_node' => 'aapanel-managed01', 'state' => 'active']);
    $before = noOrphanCounts();

    $refused = noOrphanPost($this, noOrphanPayload('cizi@orphan.cz', [], [['line_id' => 'web', 'product_key' => 'web-hosting', 'plan_key' => 'start', 'config' => ['domain' => 'soused-orphan.cz']]]), 'no-orphan-name')->assertStatus(409);

    expect($refused->json('error'))->toBe('site_name_taken')
        ->and(User::query()->where('email', 'cizi@orphan.cz')->exists())->toBeFalse()
        ->and(noOrphanCounts())->toBe($before);
    Notification::assertNothingSent();
    Mail::assertNothingOutgoing();
});
