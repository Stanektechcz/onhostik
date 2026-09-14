<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Invoicing\InvoiceService;
use Onhost\Domain\Loyalty\LoyaltyService;
use Onhost\Domain\Loyalty\Models\Referral;
use Onhost\Domain\Loyalty\ReferralService;
use Onhost\Domain\Notifications\Models\MailOutbox;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\WalletLedger\WalletService;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Outbox\OutboxPublisher;

/*
 * Referrals on the loyalty rails (audit §5j-2): the invite code binds a new organization at registration, the first paid
 * document rewards both sides once — points and promo credit — and abuse (same non-public e-mail domain, held orders,
 * the same address twice, the monthly cap) is refused with a recorded reason.
 */

beforeEach(function () {
    $this->seed([CatalogSeeder::class, TaxRuleSeeder::class, LegalEntitySeeder::class]);
    Http::preventStrayRequests();
});

function referralPaidInvoice(Organization $org, int $net = 50000): void
{
    $ctx = CommandContext::system('test');
    $invoices = app(InvoiceService::class);
    $draft = $invoices->draft($org, 'invoice', 'CZK', [['sku' => 'web-start', 'description' => 'Webhosting Start', 'qty' => 1, 'unit' => 'ks', 'unit_net' => $net, 'discount' => 0, 'net' => $net, 'tax_rate' => '21', 'tax_category' => 'S', 'tax' => (int) round($net * 0.21), 'total' => $net + (int) round($net * 0.21)]], $ctx, null, ['postpaid' => true]);
    $invoice = $invoices->issue($draft, $ctx);
    $invoices->markPaid($invoice, $invoice->total(), 'bank', $ctx);
    app(OutboxPublisher::class)->relayPending(); // invoice.paid → the referral settles and publishes its own events
    app(OutboxPublisher::class)->relayPending(); // … which the router turns into notifications and mails
}

it('binds a referred organization at registration and rewards both sides once on the first paid document', function () {
    [$owner, $org] = $this->customerWithOrganization(['email' => 'jan@firma.cz'], ['name' => 'Firma Jan s.r.o.']);
    $this->actingAs($owner, 'sanctum');
    $h = ['X-Organization' => $org->id];
    expect($this->withHeaders($h)->getJson('/v1/account/referral')->assertOk()->json('data.code'))->toBeNull();
    $summary = $this->withHeaders($h + ['Idempotency-Key' => 'rf-1'])->postJson('/v1/account/referral/code')->assertCreated()->json();
    expect($summary['code'])->toMatch('/^FIRMA-[A-Z0-9]{4}$/')->and($summary['link'])->toContain('/registrace?ref='.$summary['code'])->and($summary['reward']['referrer_credit']['minor'])->toBe(20000);
    expect($this->withHeaders($h + ['Idempotency-Key' => 'rf-2'])->postJson('/v1/account/referral/code')->assertCreated()->json('code'))->toBe($summary['code']); // never changes

    // registration with the code binds the new organization; an unknown code is ignored
    Http::fake(['https://api.pwnedpasswords.com/*' => Http::response('', 200)]);
    $this->flushHeaders();
    auth()->forgetGuards();
    $registered = $this->postJson('/v1/auth/register', ['name' => 'Petra Nová', 'email' => 'petra@novy-eshop.cz', 'password' => 'Velmi-Silne-Heslo-2026', 'terms' => true, 'ref' => strtolower($summary['code'])])->assertCreated();
    $referred = Organization::query()->where('owner_user_id', User::query()->where('email', 'petra@novy-eshop.cz')->value('id'))->firstOrFail();
    $referral = Referral::query()->where('referred_organization_id', $referred->id)->firstOrFail();
    expect($referral->state)->toBe('pending')->and($referral->referrer_organization_id)->toBe($org->id);
    app(OutboxPublisher::class)->relayPending();
    expect(app(ReferralService::class)->attach($referred, 'NOPE-0000', null, CommandContext::system('test')))->toBeNull(); // already bound

    // the first paid document settles it: points and promo credit for both, never twice
    referralPaidInvoice($referred);
    expect($referral->fresh()->state)->toBe('rewarded');
    $loyalty = app(LoyaltyService::class);
    expect($loyalty->points($org->id))->toBe(200)->and($loyalty->points($referred->id))->toBe(100);
    $wallets = app(WalletService::class);
    expect($wallets->balances($org, 'CZK')['promo']->minor)->toBe(20000)->and($wallets->balances($referred, 'CZK')['promo']->minor)->toBe(10000);
    expect(MailOutbox::query()->where('template_key', 'referral-rewarded')->where('to', 'jan@firma.cz')->exists())->toBeTrue();
    referralPaidInvoice($referred);
    expect($loyalty->points($org->id))->toBe(200)->and($wallets->balances($org, 'CZK')['promo']->minor)->toBe(20000);

    $this->actingAs($owner, 'sanctum');
    $listed = $this->withHeaders($h)->getJson('/v1/account/referral')->assertOk()->json('data');
    expect($listed['counts'])->toMatchArray(['rewarded' => 1, 'pending' => 0, 'refused' => 0])->and($listed['referrals'][0]['organization'])->toStartWith('Pe')->and($listed['referrals'][0]['organization'])->not->toContain('Nová');
});

it('refuses the reward for the same company e-mail domain, held orders, a repeated address and the monthly cap', function () {
    [$owner, $org] = $this->customerWithOrganization(['email' => 'jan@firma.cz'], ['name' => 'Firma Jan s.r.o.']);
    $referrals = app(ReferralService::class);
    $code = $referrals->code($org);
    $ctx = CommandContext::system('test');

    [, $colleague] = $this->customerWithOrganization(['email' => 'petr@firma.cz'], ['name' => 'Petr z téže firmy']);
    $referrals->attach($colleague, $code, '203.0.113.5', $ctx);
    referralPaidInvoice($colleague);
    expect(Referral::query()->where('referred_organization_id', $colleague->id)->value('reason'))->toBe('same_email_domain');

    [, $gmail] = $this->customerWithOrganization(['email' => 'jan.novak@gmail.com'], ['name' => 'Jan z gmailu']);
    $referrals->attach($gmail, $code, '203.0.113.5', $ctx);
    referralPaidInvoice($gmail);
    expect(Referral::query()->where('referred_organization_id', $gmail->id)->value('state'))->toBe('held') // the colleague came from the same address earlier: score 60 holds it for finance (audit §5l-4)
        ->and(Referral::query()->where('referred_organization_id', $gmail->id)->value('score'))->toBe(85); // same_address 60 + rapid_signup 25 (the colleague came minutes earlier)

    [, $fresh] = $this->customerWithOrganization(['email' => 'eva@seznam.cz'], ['name' => 'Eva']);
    $referrals->attach($fresh, $code, '198.51.100.9', $ctx);
    referralPaidInvoice($fresh);
    expect(Referral::query()->where('referred_organization_id', $fresh->id)->value('state'))->toBe('rewarded');

    config()->set('onhost.loyalty.referral.max_per_30d', 1);
    [, $capped] = $this->customerWithOrganization(['email' => 'ota@centrum.cz'], ['name' => 'Ota']);
    $referrals->attach($capped, $code, '198.51.100.10', $ctx);
    referralPaidInvoice($capped);
    expect(Referral::query()->where('referred_organization_id', $capped->id)->value('reason'))->toBe('monthly_cap');
    expect(app(LoyaltyService::class)->points($org->id))->toBe(200); // one reward in total
});
