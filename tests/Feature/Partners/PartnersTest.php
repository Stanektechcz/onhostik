<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Onhost\Domain\Content\Models\Lead;
use Onhost\Domain\Identity\StepUp\StepUpService;
use Onhost\Domain\Invoicing\InvoiceService;
use Onhost\Domain\Invoicing\Models\Invoice;
use Onhost\Domain\Notifications\Models\MailOutbox;
use Onhost\Domain\Notifications\Models\Notification;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Partners\Models\Partner;
use Onhost\Domain\Partners\Models\PartnerCommission;
use Onhost\Domain\Partners\PartnerService;
use Onhost\Domain\WalletLedger\Models\LedgerTransaction;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Money\Money;
use Onhost\Platform\Outbox\OutboxPublisher;

/** Issue and pay a 1 000 CZK net invoice for a client organization (commission base). */
function paidClientInvoice(Organization $client, int $net = 100000): Invoice
{
    $ctx = CommandContext::system('test');
    $invoices = app(InvoiceService::class);
    $tax = (int) round($net * 0.21);
    $draft = $invoices->draft($client, 'invoice', 'CZK', [[
        'sku' => 'vps-4-8', 'description' => 'VPS 4/8', 'qty' => 1, 'unit' => 'ks', 'unit_net' => $net, 'discount' => 0, 'net' => $net, 'tax_rate' => '21', 'tax_category' => 'S', 'tax' => $tax, 'total' => $net + $tax,
    ]], $ctx, null, ['postpaid' => true]);
    $invoice = $invoices->issue($draft, $ctx);
    $invoices->markPaid($invoice, $invoice->total(), 'bank', $ctx);
    app(OutboxPublisher::class)->relayPending();

    return $invoice->fresh();
}

it('turns a reseller application into a partner awaiting approval, and approval opens the portal', function () {
    [$owner, $partnerOrg] = $this->customerWithOrganization([], ['name' => 'Agentura Pixel s.r.o.']);
    $this->actingAs($owner, 'sanctum');
    $applied = $this->withHeader('X-Organization', $partnerOrg->id)->postJson('/v1/reseller/apply', ['name' => $owner->name, 'email' => $owner->email, 'company' => 'Agentura Pixel s.r.o.', 'clients' => '11–50', 'model' => 'share', 'consent' => true])->assertCreated();
    expect($applied->json('data.partner.state'))->toBe('applied')->and($applied->json('data.partner.code'))->toStartWith('AGENTU-');
    expect(Lead::query()->where('kind', 'reseller')->where('organization_id', $partnerOrg->id)->exists())->toBeTrue();
    $this->withHeader('X-Organization', $partnerOrg->id)->getJson('/v1/partner/overview')->assertForbidden()->assertJsonPath('error', 'partner_not_active');

    $this->actingAs($this->staff('sre'), 'sanctum');
    $this->getJson('/v1/staff/partners')->assertForbidden();
    $this->actingAs($this->staff('billing_finance_admin'), 'sanctum');
    $partnerId = $applied->json('data.partner.id');
    expect($this->getJson('/v1/staff/partners?state=applied')->assertOk()->json('data.0.id'))->toBe($partnerId);
    $this->postJson("/v1/staff/partners/{$partnerId}/approve")->assertOk()->assertJsonPath('state', 'active')->assertJsonPath('tier', 'bronze')->assertJsonPath('rate', 15);
    app(OutboxPublisher::class)->relayPending();
    expect(Notification::query()->where('audience', 'customer')->where('organization_id', $partnerOrg->id)->where('kind', 'partner')->exists())->toBeTrue();

    $this->actingAs($owner, 'sanctum');
    $overview = $this->withHeader('X-Organization', $partnerOrg->id)->getJson('/v1/partner/overview')->assertOk();
    expect($overview->json('data.partner.state'))->toBe('active')->and($overview->json('data.kpis.clients'))->toBe(0)->and($overview->json('data.tier.next.name'))->toBe('silver');
    expect($this->withHeader('X-Organization', $partnerOrg->id)->getJson('/v1/partner/assets')->assertOk()->json('data'))->toHaveCount(3);
});

it('accrues commission from paid client invoices, reverses on credit notes, follows tiers, and pays out by self-billing after step-up', function () {
    $this->seed([CatalogSeeder::class, TaxRuleSeeder::class, LegalEntitySeeder::class]);
    [$owner, $partnerOrg] = $this->customerWithOrganization([], ['name' => 'Agentura Pixel s.r.o.']);
    $partnerOrg->forceFill(['vat_status' => 'payer', 'dic' => 'CZ12345678'])->save(); // VAT payer → self-billed invoice carries 21 % VAT
    $partners = app(PartnerService::class);
    $partner = $partners->approve($partners->apply($partnerOrg, ['model' => 'share'], CommandContext::system('test')), CommandContext::system('test'));
    [$clientOwner, $client] = $this->customerWithOrganization(['email' => 'petra@bezvazasilky.cz'], ['name' => 'Bezvazásilky s.r.o.']);

    // attribution by referral code is bound to the client account and is permanent
    expect($partners->attribute($client, strtolower($partner->code), CommandContext::system('test'))?->id)->toBe($partner->id);
    expect($client->fresh()->partner_organization_id)->toBe($partnerOrg->id);
    expect($partners->attribute($client, $partner->code, CommandContext::system('test')))->toBeNull();

    $invoice = paidClientInvoice($client);
    $commission = PartnerCommission::query()->where('invoice_id', $invoice->id)->firstOrFail();
    expect($commission->kind)->toBe('share')->and($commission->rate_pct)->toBe(15)->and($commission->base_minor)->toBe(100000)->and($commission->amount_minor)->toBe(15000)->and($commission->state)->toBe('payable');
    paidClientInvoice($client, 100000); // second invoice
    expect(PartnerCommission::query()->where('partner_id', $partner->id)->count())->toBe(2);

    // a credit note on the first invoice reverses its commission
    app(InvoiceService::class)->creditNote($invoice, 'Chybná fakturace', CommandContext::system('test'));
    app(OutboxPublisher::class)->relayPending();
    $reversal = PartnerCommission::query()->where('partner_id', $partner->id)->where('kind', 'reversal')->firstOrFail();
    expect($reversal->amount_minor)->toBe(-15000);
    expect($partners->balance($partner)['payable']->minor)->toBe(15000);

    // tiers: trailing three months of paid volume; a drop keeps the old rate for three months
    foreach ([1, 2, 3] as $m) {
        PartnerCommission::query()->create(['partner_id' => $partner->id, 'organization_id' => $client->id, 'invoice_id' => "hist-{$m}", 'period' => now()->subMonths($m)->format('Y-m'), 'kind' => 'share', 'base_minor' => 3000000, 'rate_pct' => 15, 'amount_minor' => 450000, 'currency' => 'CZK', 'state' => 'payable', 'invoice_paid_at' => now()->subMonths($m)->startOfMonth()->addDays(5)]);
    }
    $partner = $partners->recomputeTier($partner);
    expect($partner->tier)->toBe('silver')->and($partner->rate_pct)->toBe(18)->and($partner->volume_3m_minor)->toBe(3000000);
    PartnerCommission::query()->where('invoice_id', 'like', 'hist-%')->update(['base_minor' => 0]);
    $partner = $partners->recomputeTier($partner);
    expect($partner->tier)->toBe('silver')->and($partner->rate_pct)->toBe(18)->and($partner->rate_locked_until)->not->toBeNull();
    $partner = $partners->recomputeTier($partner, now()->addMonths(4));
    expect($partner->tier)->toBe('bronze')->and($partner->rate_pct)->toBe(15)->and($partner->rate_locked_until)->toBeNull();

    // portal: clients and commission months
    $this->actingAs($owner, 'sanctum');
    $h = ['X-Organization' => $partnerOrg->id];
    $clients = $this->withHeaders($h)->getJson('/v1/partner/clients')->assertOk();
    expect($clients->json('data.0.name'))->toBe('Bezvazásilky s.r.o.')->and($clients->json('data.0.contact'))->toBe('petra@bezvazasilky.cz')->and($clients->json('data.0.st'))->toBe('ok');
    $commissions = $this->withHeaders($h)->getJson('/v1/partner/commissions')->assertOk();
    expect($commissions->json('data.balance.payable.minor'))->toBe(1365000)->and($commissions->json('data.months'))->toHaveCount(4);

    // payout: minimum, balance cap, IBAN, FIFO allocation with a split so the amount matches exactly
    $this->withHeaders($h)->postJson('/v1/partner/payouts', ['amount' => 500, 'iban' => 'CZ6508000000192000145399'])->assertUnprocessable()->assertJsonPath('error', 'payout_below_minimum');
    $this->withHeaders($h)->postJson('/v1/partner/payouts', ['amount' => 99999, 'iban' => 'CZ6508000000192000145399'])->assertUnprocessable()->assertJsonPath('error', 'payout_exceeds_balance');
    $this->withHeaders($h)->postJson('/v1/partner/payouts', ['amount' => 5000, 'iban' => 'CZ65'])->assertUnprocessable()->assertJsonPath('error', 'payout_iban_invalid');
    $payout = $this->withHeaders($h)->postJson('/v1/partner/payouts', ['amount' => 5000, 'iban' => 'CZ65 0800 0000 1920 0014 5399'])->assertCreated();
    expect($payout->json('number'))->toBe('PO-'.now()->format('Y-m'))->and($payout->json('state'))->toBe('requested')->and($payout->json('amount.minor'))->toBe(500000)
        ->and($payout->json('self_billing.self_billing'))->toBeTrue()->and((float) $payout->json('self_billing.tax_rate'))->toBe(21.0)->and($payout->json('self_billing.total.minor'))->toBe(605000)->and($payout->json('self_billing.lines.0.client'))->toBe('Bezvazásilky s.r.o.');
    expect(PartnerCommission::query()->where('state', 'allocated')->sum('amount_minor'))->toBe(500000)->and((int) PartnerCommission::query()->where('state', 'payable')->sum('amount_minor'))->toBe(865000);
    app(OutboxPublisher::class)->relayPending();
    expect(Notification::query()->where('audience', 'internal')->where('kind', 'partner')->exists())->toBeTrue();

    $finance = $this->staff('billing_finance_admin');
    $this->actingAs($finance, 'sanctum');
    $this->postJson("/v1/staff/partners/payouts/{$payout->json('id')}/pay", ['reference' => 'BANK-2026-0912'])->assertForbidden()->assertJsonPath('error', 'step_up_required');
    app(StepUpService::class)->grant($finance, 'totp', null, '127.0.0.1');
    $this->postJson("/v1/staff/partners/payouts/{$payout->json('id')}/pay", ['reference' => 'BANK-2026-0912'])->assertOk()->assertJsonPath('state', 'paid');
    expect(LedgerTransaction::query()->where('kind', 'partner_payout')->exists())->toBeTrue()->and(PartnerCommission::query()->where('state', 'paid')->count())->toBe(2);
    app(OutboxPublisher::class)->relayPending();
    expect(MailOutbox::query()->where('template_key', 'payout')->where('to', $owner->email)->exists())->toBeTrue();

    $this->actingAs($owner, 'sanctum');
    expect($this->withHeaders($h)->getJson('/v1/partner/payouts')->assertOk()->json('data.payouts.0.state'))->toBe('paid');
    $this->withHeaders($h)->putJson('/v1/partner/whitelabel', ['domain' => 'not a domain'])->assertUnprocessable()->assertJsonPath('error', 'whitelabel_domain_invalid');
    $wl = $this->withHeaders($h)->putJson('/v1/partner/whitelabel', ['domain' => 'panel.agentura.cz', 'hide_brand' => true])->assertOk();
    expect($wl->json('whitelabel.domain'))->toBe('panel.agentura.cz')->and($wl->json('whitelabel.hide_brand'))->toBeTrue()->and($wl->json('whitelabel.verified_at'))->toBeNull();
    expect(Money::minor(Partner::query()->findOrFail($partner->id)->volume_3m_minor, 'CZK')->minor)->toBe(0);
});
