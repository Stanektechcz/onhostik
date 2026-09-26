<?php

declare(strict_types=1);

use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Partners\Models\Partner;
use Onhost\Domain\Partners\Models\PartnerCommission;
use Onhost\Domain\Partners\Models\PartnerPayout;
use Onhost\Domain\Partners\PartnerService;
use Onhost\Domain\Tax\Commands\RecordVatCheckCommand;
use Onhost\Domain\Tax\Models\TaxRuleVersion;
use Onhost\Platform\Commands\CommandBus;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Money\Money;

/*
 * TASK-0031 WP B (D31.6): the partner's self-billing document carries VAT when the partner is a VAT payer — decided from the
 * same recorded check as the tax decision (a Czech DIČ is in VIES), at the rate of the active rule set's `standard_rates`.
 * Before this, the code asked for `vat_status === 'payer'` (never written by anything) and read `rates.<CC>` (a key the rule
 * set does not have, silently 21): every self-billing document was 0 % VAT. A missing rate is refused, never guessed.
 */

beforeEach(function () {
    $this->seed([TaxRuleSeeder::class, LegalEntitySeeder::class]);
    Http::preventStrayRequests();
    config(['onhost.vies.enabled' => false]);
});

/** An approved partner with 2 000 CZK of payable commission. */
function vatPartnerWithBalance(Organization $organization): Partner
{
    $partners = app(PartnerService::class);
    $partner = $partners->approve($partners->apply($organization, ['model' => 'share'], CommandContext::system('test')), CommandContext::system('test'));
    PartnerCommission::query()->create(['partner_id' => $partner->id, 'organization_id' => $organization->id, 'invoice_id' => 'inv-'.uniqid(), 'period' => now()->format('Y-m'), 'kind' => 'share', 'base_minor' => 1000000, 'rate_pct' => 20, 'amount_minor' => 200000, 'currency' => 'CZK', 'state' => 'payable', 'invoice_paid_at' => now()->subDay()]);

    return $partner->fresh();
}

function vatPartnerRecordValid(Organization $organization, string $number): void
{
    app(CommandBus::class)->dispatch(new RecordVatCheckCommand($organization->id, 'vat-partner:'.$organization->id.':'.uniqid('', true), ['number' => $number, 'status' => 'valid', 'consultation_number' => 'WAPIPARTNER', 'trigger' => 'operator', 'source' => 'vies']), CommandContext::system('test'));
}

function vatPartnerPayout(Partner $partner): PartnerPayout
{
    return app(PartnerService::class)->requestPayout($partner, Money::minor(100000, 'CZK'), 'CZ6508000000192000145399', CommandContext::system('test')->withScope($partner->organization_id));
}

it('bills VAT at the standard rate of the rule set for a Czech partner whose DIČ VIES confirmed', function () {
    [, $org] = $this->customerWithOrganization([], ['name' => 'Agentura Pixel s.r.o.', 'country' => 'CZ', 'dic' => 'CZ12345678']);
    vatPartnerRecordValid($org, 'CZ12345678');

    $snapshot = vatPartnerPayout(vatPartnerWithBalance($org->fresh()))->self_billing;
    expect((float) $snapshot['tax_rate'])->toBe(21.0)->and(data_get($snapshot, 'tax.minor'))->toBe(21000)->and($snapshot['tax_category'])->toBe('S')
        ->and($snapshot['vat_check']['consultation_number'])->toBe('WAPIPARTNER');
});

it('bills no VAT for a partner that is not a VAT payer, and says so', function () {
    [, $org] = $this->customerWithOrganization([], ['name' => 'Jan Novák', 'country' => 'CZ', 'type' => 'person']);

    $snapshot = vatPartnerPayout(vatPartnerWithBalance($org))->self_billing;
    expect((float) $snapshot['tax_rate'])->toBe(0.0)->and($snapshot['tax_category'])->toBe('E')->and($snapshot['note_vat'])->toContain('Dodavatel není plátcem DPH');
});

it('refuses the payout when the active rule set has no rate for the partner country, and writes nothing', function () {
    [, $org] = $this->customerWithOrganization([], ['name' => 'Agentura Pixel s.r.o.', 'country' => 'CZ', 'dic' => 'CZ12345678']);
    vatPartnerRecordValid($org, 'CZ12345678');
    $partner = vatPartnerWithBalance($org->fresh());
    $rules = TaxRuleVersion::query()->where('version', 1)->firstOrFail()->rules;
    unset($rules['standard_rates']['CZ']);
    TaxRuleVersion::query()->create(['version' => 2, 'effective_from' => now()->subDay(), 'state' => 'active', 'note' => 'test without CZ', 'rules' => $rules]);

    expect(fn () => vatPartnerPayout($partner))->toThrow(fn (DomainError $e) => expect($e->error)->toBe('tax_rate_missing'));
    expect(PartnerPayout::query()->count())->toBe(0)->and(PartnerCommission::query()->where('state', 'allocated')->count())->toBe(0);
});

it('ignores the legacy rates key and reads standard_rates only', function () {
    [, $org] = $this->customerWithOrganization([], ['name' => 'Agentura Pixel s.r.o.', 'country' => 'CZ', 'dic' => 'CZ12345678']);
    vatPartnerRecordValid($org, 'CZ12345678');
    $partner = vatPartnerWithBalance($org->fresh());
    $rules = TaxRuleVersion::query()->where('version', 1)->firstOrFail()->rules;
    TaxRuleVersion::query()->create(['version' => 2, 'effective_from' => now()->subDay(), 'state' => 'active', 'note' => 'test legacy key', 'rules' => $rules + ['rates' => ['CZ' => 99]]]);

    expect((float) vatPartnerPayout($partner)->self_billing['tax_rate'])->toBe(21.0);
});

it('bills a verified partner of another member state under reverse charge', function () {
    [, $org] = $this->customerWithOrganization([], ['name' => 'Agentúra s.r.o.', 'country' => 'SK', 'vat_id' => 'SK1234567890']);
    vatPartnerRecordValid($org, 'SK1234567890');

    $snapshot = vatPartnerPayout(vatPartnerWithBalance($org->fresh()))->self_billing;
    expect((float) $snapshot['tax_rate'])->toBe(0.0)->and($snapshot['tax_category'])->toBe('AE')->and($snapshot['note_vat'])->toContain('reverse charge');
});

it('never rewrites a payout document already issued when the VAT status changes', function () {
    [, $org] = $this->customerWithOrganization([], ['name' => 'Agentura Pixel s.r.o.', 'country' => 'CZ', 'dic' => 'CZ12345678']);
    vatPartnerRecordValid($org, 'CZ12345678');
    $payout = vatPartnerPayout(vatPartnerWithBalance($org->fresh()));
    $before = $payout->fresh()->self_billing;

    $org->fresh()->forceFill(['vat_status' => 'invalid'])->save();
    expect($payout->fresh()->self_billing)->toBe($before);
});
