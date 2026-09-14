<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Invoicing\InvoiceService;
use Onhost\Domain\Loyalty\LoyaltyService;
use Onhost\Domain\Loyalty\Models\Referral;
use Onhost\Domain\Loyalty\ReferralService;
use Onhost\Domain\Notifications\Models\Notification;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Events\GenericEvent;
use Onhost\Platform\Outbox\OutboxPublisher;
use Onhost\Platform\Settings\SettingsStore;

/*
 * Referral fraud scoring (audit §5l-4): signals add up to a score; the refuse mark refuses with the strongest signal, the
 * hold mark waits for finance; staff decisions teach the weights; a chargeback of a rewarded referral claws it back.
 */

beforeEach(function () {
    $this->seed([CatalogSeeder::class, TaxRuleSeeder::class, LegalEntitySeeder::class]);
    Http::preventStrayRequests();
});

function scoringPaidInvoice(Organization $org): void
{
    $ctx = CommandContext::system('test');
    $invoices = app(InvoiceService::class);
    $draft = $invoices->draft($org, 'invoice', 'CZK', [['sku' => 'web-start', 'description' => 'Webhosting', 'qty' => 1, 'unit' => 'ks', 'unit_net' => 50000, 'discount' => 0, 'net' => 50000, 'tax_rate' => '21', 'tax_category' => 'S', 'tax' => 10500, 'total' => 60500]], $ctx, null, ['postpaid' => true]);
    $invoice = $invoices->issue($draft, $ctx);
    $invoices->markPaid($invoice, $invoice->total(), 'bank', $ctx);
    app(OutboxPublisher::class)->relayPending();
    app(OutboxPublisher::class)->relayPending();
}

it('holds a suspicious referral for finance, learns from the decision and claws back after a chargeback', function () {
    [$owner, $org] = $this->customerWithOrganization(['email' => 'jan@firma.cz'], ['name' => 'Firma Jan s.r.o.']);
    $referrals = app(ReferralService::class);
    $code = $referrals->code($org);
    $ctx = CommandContext::system('test');
    expect($referrals->weights())->toBe(ReferralService::WEIGHTS);

    // two invites from the same address within an hour: same_address (60) + rapid_signup (25) = 85 → held, not refused
    [, $first] = $this->customerWithOrganization(['email' => 'eva@seznam.cz'], ['name' => 'Eva']);
    $referrals->attach($first, $code, '203.0.113.5', $ctx);
    [, $second] = $this->customerWithOrganization(['email' => 'ota@centrum.cz'], ['name' => 'Ota']);
    $referrals->attach($second, $code, '203.0.113.5', $ctx);
    scoringPaidInvoice($second);
    $held = Referral::query()->where('referred_organization_id', $second->id)->firstOrFail();
    expect($held->state)->toBe(Referral::HELD)->and($held->score)->toBe(85)->and($held->signals)->toBe(['same_address', 'rapid_signup']);
    expect(app(LoyaltyService::class)->points($org->id))->toBe(0);
    expect(Notification::query()->where('audience', 'internal')->where('event', 'referral.held')->exists())->toBeTrue();

    // finance releases it: the reward follows and the two signals get lighter
    $staff = $this->staff('platform_owner');
    $this->actingAs($staff, 'sanctum');
    expect($this->getJson('/v1/staff/referrals?state=held')->assertOk()->json('data.0.id'))->toBe($held->id);
    $this->withHeader('Idempotency-Key', 'rr-1')->postJson("/v1/staff/referrals/{$held->id}/review", ['decision' => 'release', 'note' => 'known agency'])->assertOk()->assertJsonPath('state', 'rewarded');
    $this->flushHeaders();
    expect(app(LoyaltyService::class)->points($org->id))->toBe(200)->and($referrals->weights())->toMatchArray(['same_address' => 55, 'rapid_signup' => 20]);
    $this->withHeader('Idempotency-Key', 'rr-2')->postJson("/v1/staff/referrals/{$held->id}/review", ['decision' => 'reject'])->assertStatus(409)->assertJsonPath('error', 'referral_not_held');
    $this->flushHeaders();

    // the first one now scores same_address only (55 < 60): rewarded straight away
    scoringPaidInvoice($first);
    expect(Referral::query()->where('referred_organization_id', $first->id)->value('state'))->toBe(Referral::REWARDED)->and(app(LoyaltyService::class)->points($org->id))->toBe(400);

    // a rejected review makes the signals heavier; a refuse-mark signal refuses outright
    app(SettingsStore::class)->set(ReferralService::WEIGHTS_SETTING, ['same_address' => 60, 'rapid_signup' => 25]);
    [, $third] = $this->customerWithOrganization(['email' => 'petr@post.cz'], ['name' => 'Petr']);
    $referrals->attach($third, $code, '203.0.113.5', $ctx);
    [, $fourth] = $this->customerWithOrganization(['email' => 'karel@volny.cz'], ['name' => 'Karel']);
    $referrals->attach($fourth, $code, '203.0.113.5', $ctx);
    scoringPaidInvoice($fourth);
    $heldAgain = Referral::query()->where('referred_organization_id', $fourth->id)->firstOrFail();
    expect($heldAgain->state)->toBe(Referral::HELD);
    $this->withHeader('Idempotency-Key', 'rr-3')->postJson("/v1/staff/referrals/{$heldAgain->id}/review", ['decision' => 'reject', 'note' => 'farm'])->assertOk()->assertJsonPath('state', 'refused');
    $this->flushHeaders();
    expect($referrals->weights())->toMatchArray(['same_address' => 65, 'rapid_signup' => 30])->and($heldAgain->refresh()->reason)->toStartWith('staff');
    [, $colleague] = $this->customerWithOrganization(['email' => 'petr@firma.cz'], ['name' => 'Kolega']);
    $referrals->attach($colleague, $code, '198.51.100.7', $ctx);
    scoringPaidInvoice($colleague);
    expect(Referral::query()->where('referred_organization_id', $colleague->id)->first())->toMatchArray(['state' => Referral::REFUSED, 'reason' => 'same_email_domain', 'score' => 100]);

    // a chargeback of a rewarded referral within the window claws it back and raises the chargeback signal
    app(OutboxPublisher::class)->publish(GenericEvent::of('chargeback.approved', 'chargeback', 'cbk-1', ['label' => 'x'], $first->id));
    app(OutboxPublisher::class)->relayPending();
    app(OutboxPublisher::class)->relayPending();
    expect(Referral::query()->where('referred_organization_id', $first->id)->first())->toMatchArray(['state' => Referral::CLAWBACK, 'reason' => 'chargeback']);
    expect($referrals->weights()['chargeback'])->toBe(100)->and($referrals->weights()['same_address'])->toBe(75);
    expect(Notification::query()->where('audience', 'internal')->where('event', 'referral.clawback')->exists())->toBeTrue();
    expect($this->getJson('/v1/staff/referrals?state=all')->assertOk()->json('data'))->toHaveCount(5);
});
