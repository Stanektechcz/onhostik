<?php

declare(strict_types=1);

use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Partners\Models\PartnerCommission;
use Onhost\Domain\Partners\Models\PartnerPayout;
use Onhost\Domain\Partners\PartnerPresenters;
use Onhost\Domain\Partners\PartnerService;
use Onhost\Domain\Tax\Commands\OverrideVatStatusCommand;
use Onhost\Domain\Tax\Commands\OverrideVatStatusHandler;
use Onhost\Domain\Tax\Commands\RecordVatCheckCommand;
use Onhost\Domain\Tax\VatStanding;
use Onhost\Domain\WalletLedger\Models\LedgerAccount;
use Onhost\Domain\WalletLedger\Models\LedgerPosting;
use Onhost\Platform\Commands\CommandBus;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Money\Money;

/*
 * TASK-0031 review round 3 (security HIGH, billing MEDIUM): a partner controls its own organization's name, country and
 * number. VatStanding::isVatPayer took a VIES "valid" for the current number as proof of VAT-payer status without the two
 * rules the customer's standing applies — the number's country is the organization's, and a number VIES registers to another
 * trader proves nothing about this one. A partner could type a real Czech company's DIČ (or keep a valid SK number with a CZ
 * address) and be paid 21 % on markPayoutPaid, booked as input VAT the tax office would deny. Every document was 0 % before
 * TASK-0031, so the exposure was new. VIES is never called here (the verdicts are recorded as the system would record them).
 */

beforeEach(function () {
    $this->seed([TaxRuleSeeder::class, LegalEntitySeeder::class]);
    Http::preventStrayRequests();
    config(['onhost.vies.enabled' => false]);
});

/** An approved partner with 2 000 CZK of payable commission; its number is recorded VIES-valid with the trader name VIES gave. */
function vatR3Partner(array $attributes, ?string $viesName = null): Organization
{
    $org = Organization::query()->create($attributes + ['slug' => 'r3-'.uniqid(), 'owner_user_id' => 'usr_r3', 'customer_class' => 'b2b', 'currency' => 'CZK']);
    $number = (string) VatStanding::subject($org)?->value;
    app(CommandBus::class)->dispatch(new RecordVatCheckCommand($org->id, 'vat-r3:'.$org->id.':'.uniqid('', true), [
        'number' => $number, 'status' => 'valid', 'consultation_number' => 'WAPIR3', 'trigger' => 'operator', 'source' => 'vies', 'name' => $viesName,
    ]), CommandContext::system('test'));
    $partners = app(PartnerService::class);
    $partner = $partners->approve($partners->apply($org->fresh(), ['model' => 'share'], CommandContext::system('test')), CommandContext::system('test'));
    PartnerCommission::query()->create(['partner_id' => $partner->id, 'organization_id' => $org->id, 'invoice_id' => 'inv-'.uniqid(), 'period' => now()->format('Y-m'), 'kind' => 'share', 'base_minor' => 1000000, 'rate_pct' => 20, 'amount_minor' => 200000, 'currency' => 'CZK', 'state' => 'payable', 'invoice_paid_at' => now()->subDay()]);

    return $org->fresh();
}

function vatR3Payout(Organization $org): PartnerPayout
{
    $partners = app(PartnerService::class);

    return $partners->requestPayout($partners->partnerFor($org), Money::minor(100000, 'CZK'), 'CZ6508000000192000145399', CommandContext::system('test')->withScope($org->id));
}

/** Approves and pays the payout; returns its ledger lines as `account:direction => minor` and what was transferred. */
function vatR3Pay(PartnerPayout $payout): array
{
    $partners = app(PartnerService::class);
    $partners->approvePayout($payout, CommandContext::system('test'));
    $paid = $partners->markPayoutPaid($payout->fresh(), 'BANK-R3-'.uniqid(), CommandContext::system('test'));
    $postings = LedgerPosting::query()->where('transaction_id', (string) $paid->ledger_transaction_id)->get()
        ->mapWithKeys(fn (LedgerPosting $p) => [LedgerAccount::query()->findOrFail($p->account_id)->code.':'.$p->direction => (int) $p->amount_minor])->all();

    return [$postings, $paid->transferAmount()->minor];
}

function vatR3Override(Organization $org, string $status): void
{
    app(OverrideVatStatusHandler::class)->handle(new OverrideVatStatusCommand('vat-r3-override:'.uniqid(), [
        'organization_id' => $org->id, 'status' => $status, 'reason' => 'Registrace ověřena u správce daně, jiný zápis jména ve VIES', 'evidence' => 'výpis z registru plátců DPH', 'days' => 30,
    ]), CommandContext::system('test'));
}

it('does not make a Czech partner a VAT payer on a valid number of another member state', function () {
    $org = vatR3Partner(['name' => 'Agentura Pixel s.r.o.', 'country' => 'CZ', 'vat_id' => 'SK1234567890'], 'Agentura Pixel s.r.o.');

    expect(VatStanding::isVatPayer($org))->toBeFalse();
    $snapshot = vatR3Payout($org)->self_billing;
    expect((float) $snapshot['tax_rate'])->toBe(0.0)->and($snapshot['tax_category'])->toBe('E')->and(data_get($snapshot, 'tax.minor'))->toBe(0)
        ->and($snapshot['vat_review'])->toBeTrue()->and($snapshot['vat_review_reason'])->toBe('vat_country_mismatch')
        ->and($snapshot['note_vat'])->toBe('Registrace dodavatele k DPH neověřena.');

    [$postings, $transfer] = vatR3Pay(PartnerPayout::query()->where('number', $snapshot['number'])->firstOrFail());
    expect($transfer)->toBe(100000)->and($postings)->toBe(['expense:partner_commission:CZK:debit' => 100000, 'asset:bank:bank:CZK:credit' => 100000]);
});

it('does not make a Czech partner a VAT payer on a DIČ that VIES registers to another trader', function () {
    $org = vatR3Partner(['name' => 'Agentura Pixel s.r.o.', 'country' => 'CZ', 'dic' => 'CZ12345678'], 'Stavební Holding Morava a.s.');

    expect(VatStanding::isVatPayer($org))->toBeFalse();
    $snapshot = vatR3Payout($org)->self_billing;
    expect((float) $snapshot['tax_rate'])->toBe(0.0)->and($snapshot['tax_category'])->toBe('E')
        ->and($snapshot['vat_review'])->toBeTrue()->and($snapshot['vat_review_reason'])->toBe('name_mismatch')
        ->and($snapshot['note_vat'])->toBe('Registrace dodavatele k DPH neověřena.');

    [$postings, $transfer] = vatR3Pay(PartnerPayout::query()->where('number', $snapshot['number'])->firstOrFail());
    expect($transfer)->toBe(100000)->and($postings)->not->toHaveKey('liability:vat:CZK:debit');
});

it('accepts a genuine name difference only through the staff override, then bills the standard rate', function () {
    $org = vatR3Partner(['name' => 'Agentura Pixel s.r.o.', 'country' => 'CZ', 'dic' => 'CZ12345678'], 'Stavební Holding Morava a.s.');
    vatR3Override($org, 'valid');

    expect(VatStanding::isVatPayer($org->fresh()))->toBeTrue();
    $snapshot = vatR3Payout($org->fresh())->self_billing;
    expect((float) $snapshot['tax_rate'])->toBe(21.0)->and($snapshot['tax_category'])->toBe('S')->and($snapshot['vat_review'])->toBeFalse()
        ->and($snapshot['vat_review_reason'])->toBeNull()->and($snapshot['vat_check']['reason'])->toBe('staff_override');

    [$postings, $transfer] = vatR3Pay(PartnerPayout::query()->where('number', $snapshot['number'])->firstOrFail());
    expect($transfer)->toBe(121000)->and($postings['liability:vat:CZK:debit'])->toBe(21000);
});

it('bills a partner of another member state with a valid number of its own country under reverse charge, paying no VAT', function () {
    $org = vatR3Partner(['name' => 'Agentúra Pixel s.r.o.', 'country' => 'SK', 'vat_id' => 'SK1234567890'], 'Agentúra Pixel s.r.o.');

    $snapshot = vatR3Payout($org)->self_billing;
    expect($snapshot['tax_category'])->toBe('AE')->and((float) $snapshot['tax_rate'])->toBe(0.0)->and($snapshot['vat_review'])->toBeFalse();
    [$postings, $transfer] = vatR3Pay(PartnerPayout::query()->where('number', $snapshot['number'])->firstOrFail());
    expect($transfer)->toBe(100000)->and($postings)->not->toHaveKey('liability:vat:CZK:debit');
});

it('keeps the customer standing and the payer decision on one rule: a number of another country is never either', function () {
    $org = vatR3Partner(['name' => 'Agentura Pixel s.r.o.', 'country' => 'CZ', 'vat_id' => 'SK1234567890'], 'Agentura Pixel s.r.o.');

    expect(VatStanding::standing($org))->toBe(['status' => 'unknown', 'reason' => 'vat_country_mismatch'])
        ->and(VatStanding::payerStanding($org))->toBe(['payer' => false, 'reason' => 'vat_country_mismatch']);
});

/*
 * The open question of the round: `vat_review` is staff-only (critic). The customer keeps the explanation of the VAT it is
 * billed (the category, the rate and the note on the document); the flag, its reason and who VIES names are finance's.
 */
it('shows the partner its self-billing VAT and why, but not finance\'s review flag or the VIES evidence behind it', function () {
    $org = vatR3Partner(['name' => 'Agentura Pixel s.r.o.', 'country' => 'CZ', 'dic' => 'CZ12345678'], 'Stavební Holding Morava a.s.');
    $payout = vatR3Payout($org);

    $customer = PartnerPresenters::payout($payout)['self_billing'];
    expect($customer)->not->toHaveKey('vat_review')->not->toHaveKey('vat_review_reason')
        ->and($customer['note_vat'])->toBe('Registrace dodavatele k DPH neověřena.')->and($customer['tax_category'])->toBe('E')
        ->and(array_keys($customer['vat_check']))->toBe(['checked_at', 'consultation_number', 'source']);

    $staff = PartnerPresenters::payout($payout, true)['self_billing'];
    expect($staff['vat_review'])->toBeTrue()->and($staff['vat_review_reason'])->toBe('name_mismatch')->and($staff['vat_check']['name_mismatch'])->toBeTrue();
});

it('answers the partner portal without the review flag and staff with it', function () {
    [$owner, $org] = $this->customerWithOrganization([], ['name' => 'Agentura Pixel s.r.o.', 'country' => 'CZ', 'dic' => 'CZ12345678']);
    app(CommandBus::class)->dispatch(new RecordVatCheckCommand($org->id, 'vat-r3-portal:'.$org->id, [
        'number' => 'CZ12345678', 'status' => 'valid', 'consultation_number' => 'WAPIR3', 'trigger' => 'operator', 'source' => 'vies', 'name' => 'Stavební Holding Morava a.s.',
    ]), CommandContext::system('test'));
    $partners = app(PartnerService::class);
    $partner = $partners->approve($partners->apply($org->fresh(), ['model' => 'share'], CommandContext::system('test')), CommandContext::system('test'));
    PartnerCommission::query()->create(['partner_id' => $partner->id, 'organization_id' => $org->id, 'invoice_id' => 'inv-'.uniqid(), 'period' => now()->format('Y-m'), 'kind' => 'share', 'base_minor' => 1000000, 'rate_pct' => 20, 'amount_minor' => 200000, 'currency' => 'CZK', 'state' => 'payable', 'invoice_paid_at' => now()->subDay()]);

    $this->actingAs($owner, 'sanctum');
    $h = ['X-Organization' => $org->id];
    $created = $this->withHeaders($h + ['Idempotency-Key' => 'vat-r3-payout-'.uniqid()])->postJson('/v1/partner/payouts', ['amount' => 1000, 'iban' => 'CZ6508000000192000145399'])->assertCreated();
    expect($created->json('self_billing'))->not->toHaveKey('vat_review')->and($created->json('self_billing.note_vat'))->toBe('Registrace dodavatele k DPH neověřena.');
    $listed = $this->withHeaders($h)->getJson('/v1/partner/payouts')->assertOk()->json('data.payouts.0.self_billing');
    expect($listed)->not->toHaveKey('vat_review')->not->toHaveKey('vat_review_reason')->and($listed['vat_check'])->not->toHaveKey('name_mismatch');

    $this->actingAs($this->staff('billing_finance_admin'), 'sanctum');
    $staff = $this->getJson('/v1/staff/partners/payouts')->assertOk()->json('data.0.self_billing');
    expect($staff['vat_review'])->toBeTrue()->and($staff['vat_review_reason'])->toBe('name_mismatch');
});

it('does not tell a partner whose override ended that it is not a VAT payer', function () {
    $org = vatR3Partner(['name' => 'Agentura Pixel s.r.o.', 'country' => 'CZ', 'dic' => 'CZ12345678'], 'Stavební Holding Morava a.s.');
    vatR3Override($org, 'valid');
    $this->travel(31)->days();

    expect(VatStanding::payerStanding($org->fresh()))->toBe(['payer' => false, 'reason' => 'override_expired']);
    $snapshot = vatR3Payout($org->fresh())->self_billing;
    expect((float) $snapshot['tax_rate'])->toBe(0.0)->and($snapshot['note_vat'])->toBe('Registrace dodavatele k DPH neověřena.')
        ->and($snapshot['vat_review'])->toBeTrue()->and($snapshot['vat_review_reason'])->toBe('unknown');
});
