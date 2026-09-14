<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Illuminate\Support\Facades\Notification;
use Onhost\Domain\Identity\Models\EmailVerificationToken;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Identity\Notifications\GuestAccountNotification;
use Onhost\Domain\Orders\Models\Order;
use Onhost\Domain\Orders\OrderStateMachine;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Platform\Audit\AuditEvent;

/* A visitor without an account finishes the order in the web checkout: the platform creates the account from the entered details, places the order as that customer and signs the browser in. */

beforeEach(function () {
    $this->seed([CatalogSeeder::class, TaxRuleSeeder::class, LegalEntitySeeder::class]);
    Notification::fake();
});

function guestOrderPayload(array $overrides = []): array
{
    return array_replace_recursive([
        'customer' => ['email' => 'Nova@Firma.cz', 'name' => 'Petra Nová', 'company' => 'Nová s.r.o.', 'ico' => '87654321', 'country' => 'CZ', 'street' => 'Dlouhá 12', 'city' => 'Praha', 'postal_code' => '110 00'],
        'items' => [['line_id' => 'web', 'product_key' => 'web-hosting', 'plan_key' => 'start', 'config' => ['options' => ['staging' => true]]], ['product_key' => 'domain', 'config' => ['fqdn' => 'nova-firma.cz', 'period_years' => 1]]],
        'commit_months' => 12, 'currency' => 'CZK',
        'consents' => ['terms' => ['version' => '2026-09'], 'privacy' => [], 'dpa' => [], 'withdrawal_waiver' => [], 'registrar_terms' => ['person' => 'Petra Nová'], 'registry_terms_cz' => ['person' => 'Petra Nová']],
        'payment' => ['mode' => 'bank'], 'terms' => true,
    ], $overrides);
}

it('creates a basic account from the checkout details, places the order as that customer and signs the browser in', function () {
    $response = $this->withHeaders(['Referer' => 'http://localhost', 'Idempotency-Key' => 'guest-1'])->postJson('/v1/checkout/guest', guestOrderPayload());
    $response->assertCreated()->assertJsonPath('account.created', true)->assertJsonPath('account.user.email', 'nova@firma.cz')->assertJsonPath('account.organization.name', 'Nová s.r.o.')->assertJsonPath('account.organization.customer_class', 'b2b')->assertJsonPath('state', OrderStateMachine::PENDING_PAYMENT);
    expect($response->json('number'))->toMatch('/^OH-\d{4}-\d{4}$/')->and($response->json('bank_instructions'))->not->toBeNull()->and($response->json('payment_mode'))->toBe('bank')->and($response->json('currency'))->toBe('CZK');

    $user = User::query()->where('email', 'nova@firma.cz')->firstOrFail();
    $organization = Organization::query()->where('owner_user_id', $user->id)->firstOrFail();
    $order = Order::query()->findOrFail($response->json('order_id'));
    expect($order->organization_id)->toBe($organization->id)->and($order->user_id)->toBe($user->id)->and($order->commit_months)->toBe(12)->and($order->items()->count())->toBe(2)->and($order->source)->toBe('web')
        ->and($organization->ico)->toBe('87654321')->and($organization->type)->toBe('company')->and($organization->street)->toBe('Dlouhá 12')->and($organization->city)->toBe('Praha')->and($organization->postal_code)->toBe('110 00')
        ->and($response->json('total'))->toBe((int) $order->total_minor)->and($response->json('tax'))->toBe((int) $order->tax_minor)->and($response->json('subtotal'))->toBe((int) $order->subtotal_minor)
        ->and($response->json('bank_instructions.variable_symbol'))->not->toBeEmpty()->and($response->json('bank_instructions.message'))->toBe('Objednávka '.$order->number);
    expect(EmailVerificationToken::query()->where('user_id', $user->id)->where('purpose', 'reset')->exists())->toBeTrue();
    Notification::assertSentTo($user, GuestAccountNotification::class, fn ($n) => $n->orderNumber === $order->number);
    expect(AuditEvent::query()->where('action', 'auth.register')->exists())->toBeTrue()->and(AuditEvent::query()->where('action', 'order.place')->exists())->toBeTrue();

    // the browser holds a session for the new account and the order is visible in the panel
    $this->withHeaders(['Referer' => 'http://localhost'])->getJson('/v1/me')->assertOk()->assertJsonPath('data.user.id', $user->id);
    $this->withHeaders(['Referer' => 'http://localhost'])->getJson('/v1/orders')->assertOk()->assertJsonPath('data.0.number', $order->number);

    // a retried request (same idempotency key) returns the same order and creates no second account
    $again = $this->withHeaders(['Referer' => 'http://localhost', 'Idempotency-Key' => 'guest-1'])->postJson('/v1/checkout/guest', guestOrderPayload());
    expect(User::query()->count())->toBe(1)->and(Order::query()->count())->toBe(1);
    expect($again->status())->toBeIn([200, 201, 409]); // signed in already after the first call → refused, or the replay of the same order
});

it('refuses an e-mail that already has an account, wallet payment and orders without consent', function () {
    $this->customer(['email' => 'taken@example.cz']);
    $this->withHeaders(['Referer' => 'http://localhost'])->postJson('/v1/checkout/guest', guestOrderPayload(['customer' => ['email' => 'taken@example.cz']]))->assertStatus(409)->assertJsonPath('error', 'account_exists');
    $this->withHeaders(['Referer' => 'http://localhost'])->postJson('/v1/checkout/guest', guestOrderPayload(['payment' => ['mode' => 'wallet']]))->assertUnprocessable()->assertJsonValidationErrors(['payment.mode']);
    $this->withHeaders(['Referer' => 'http://localhost', 'Accept-Language' => 'cs'])->postJson('/v1/checkout/guest', guestOrderPayload(['terms' => false]))->assertUnprocessable()->assertJsonPath('errors.terms.0', 'Bez souhlasu s podmínkami účet nezaložíme.');
    expect(User::query()->where('email', 'nova@firma.cz')->exists())->toBeFalse();
});
