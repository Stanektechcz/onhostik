<?php

declare(strict_types=1);

use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Organizations\OrganizationService;
use Onhost\Domain\Partners\Models\PartnerCommission;
use Onhost\Domain\Partners\Models\PartnerPayout;
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
 * TASK-0031 closing review (security MEDIUM, billing MEDIUM): the name rule of review round 3 is controlled by the partner.
 * `name` is a self-service field, and changing it neither resets the recorded check nor queues a new one, so a partner that
 * typed a real Czech company's DIČ and that company's VIES name as its own organization name — before or after the check —
 * passed every rule, was billed S 21 % and paid 121 % on markPayoutPaid, with the 21 % booked as input VAT the tax office
 * would deny. VAT paid out in cash now needs finance's confirmation of the supplier (number + name), given through the
 * CRITICAL staff override; any later change of the name or the number takes it away. VIES is never called here.
 */

beforeEach(function () {
    $this->seed([TaxRuleSeeder::class, LegalEntitySeeder::class]);
    Http::preventStrayRequests();
    config(['onhost.vies.enabled' => false]);
});

/** Records what VIES would answer about the organization's current number, as the operator's check records it. */
function vatCrRecordVies(Organization $org, ?string $viesName): void
{
    app(CommandBus::class)->dispatch(new RecordVatCheckCommand($org->id, 'vat-cr:'.$org->id.':'.uniqid('', true), [
        'number' => (string) VatStanding::subject($org)?->value, 'status' => 'valid', 'consultation_number' => 'WAPICR', 'trigger' => 'operator', 'source' => 'vies', 'name' => $viesName,
    ]), CommandContext::system('test'));
}

/** An approved Czech partner with 2 000 CZK of payable commission. */
function vatCrPartner(array $attributes): Organization
{
    $org = Organization::query()->create($attributes + ['slug' => 'cr-'.uniqid(), 'owner_user_id' => 'usr_cr', 'customer_class' => 'b2b', 'currency' => 'CZK', 'country' => 'CZ']);
    $partners = app(PartnerService::class);
    $partner = $partners->approve($partners->apply($org->fresh(), ['model' => 'share'], CommandContext::system('test')), CommandContext::system('test'));
    PartnerCommission::query()->create(['partner_id' => $partner->id, 'organization_id' => $org->id, 'invoice_id' => 'inv-'.uniqid(), 'period' => now()->format('Y-m'), 'kind' => 'share', 'base_minor' => 1000000, 'rate_pct' => 20, 'amount_minor' => 200000, 'currency' => 'CZK', 'state' => 'payable', 'invoice_paid_at' => now()->subDay()]);

    return $org->fresh();
}

/** Requests, approves and pays 1 000 CZK; returns the self-billing snapshot, the ledger lines and what was transferred. */
function vatCrPayOut(Organization $org): array
{
    $partners = app(PartnerService::class);
    $payout = $partners->requestPayout($partners->partnerFor($org), Money::minor(100000, 'CZK'), 'CZ6508000000192000145399', CommandContext::system('test')->withScope($org->id));
    $partners->approvePayout($payout, CommandContext::system('test'));
    /** @var PartnerPayout $paid */
    $paid = $partners->markPayoutPaid($payout->fresh(), 'BANK-CR-'.uniqid(), CommandContext::system('test'));
    $postings = LedgerPosting::query()->where('transaction_id', (string) $paid->ledger_transaction_id)->get()
        ->mapWithKeys(fn (LedgerPosting $p) => [LedgerAccount::query()->findOrFail($p->account_id)->code.':'.$p->direction => (int) $p->amount_minor])->all();

    return [$paid->self_billing, $postings, $paid->transferAmount()->minor];
}

function vatCrConfirm(Organization $org): void
{
    app(OverrideVatStatusHandler::class)->handle(new OverrideVatStatusCommand('vat-cr-override:'.uniqid(), [
        'organization_id' => $org->id, 'status' => 'valid', 'reason' => 'Dodavatel ověřen: smlouva, výpis z registru plátců DPH', 'evidence' => 'výpis z registru plátců DPH', 'days' => 30,
    ]), CommandContext::system('test'));
}

it('pays no VAT to a partner that took a real company\'s DIČ and its VIES name before the check', function () {
    $org = vatCrPartner(['name' => 'Stavební Holding Morava a.s.', 'dic' => 'CZ12345678']);
    vatCrRecordVies($org, 'STAVEBNÍ HOLDING MORAVA a.s.');

    expect(VatStanding::payerStanding($org->fresh()))->toBe(['payer' => false, 'reason' => 'identity_unconfirmed']);
    [$snapshot, $postings, $transfer] = vatCrPayOut($org->fresh());
    expect($snapshot['tax_category'])->toBe('E')->and((float) $snapshot['tax_rate'])->toBe(0.0)
        ->and($snapshot['note_vat'])->toBe('Registrace dodavatele k DPH neověřena.')
        ->and($snapshot['vat_review'])->toBeTrue()->and($snapshot['vat_review_reason'])->toBe('identity_unconfirmed')
        ->and($transfer)->toBe(100000)->and($postings)->not->toHaveKey('liability:vat:CZK:debit');
});

it('pays no VAT to a partner that renamed itself to the VIES name after the check', function () {
    $org = vatCrPartner(['name' => 'Agentura Pixel s.r.o.', 'dic' => 'CZ12345678']);
    vatCrRecordVies($org, 'Stavební Holding Morava a.s.');
    expect(VatStanding::payerStanding($org->fresh())['reason'])->toBe('name_mismatch');

    // `name` is a self-service field: it resets nothing and queues nothing
    app(OrganizationService::class)->update($org->fresh(), ['name' => 'Stavební Holding Morava a.s.'], CommandContext::system('test')->withScope($org->id));

    expect(VatStanding::payerStanding($org->fresh()))->toBe(['payer' => false, 'reason' => 'identity_unconfirmed']);
    [$snapshot, $postings, $transfer] = vatCrPayOut($org->fresh());
    expect($snapshot['tax_category'])->toBe('E')->and($snapshot['vat_review_reason'])->toBe('identity_unconfirmed')
        ->and($transfer)->toBe(100000)->and($postings)->not->toHaveKey('liability:vat:CZK:debit');
});

it('pays VAT once finance confirmed the supplier, and keeps paying it on VIES after the override ends', function () {
    $org = vatCrPartner(['name' => 'Agentura Pixel s.r.o.', 'dic' => 'CZ12345678']);
    vatCrRecordVies($org, 'Agentura Pixel s.r.o.');
    vatCrConfirm($org->fresh());
    $this->travel(31)->days();
    expect(VatStanding::payerStanding($org->fresh())['reason'])->toBe('override_expired');

    // the operator's command lists the partner whose override ended, and its check restores the VIES standing
    $this->artisan('onhost:vat:verify')->expectsOutputToContain('partner')->assertSuccessful();
    vatCrRecordVies($org->fresh(), 'Agentura Pixel s.r.o.');

    expect(VatStanding::payerStanding($org->fresh()))->toBe(['payer' => true, 'reason' => 'fresh']);
    [$snapshot, $postings, $transfer] = vatCrPayOut($org->fresh());
    expect($snapshot['tax_category'])->toBe('S')->and((float) $snapshot['tax_rate'])->toBe(21.0)->and($snapshot['vat_review'])->toBeFalse()
        ->and($transfer)->toBe(121000)->and($postings['liability:vat:CZK:debit'])->toBe(21000);
});

it('keeps a genuine name difference finance confirmed after the override ends', function () {
    $org = vatCrPartner(['name' => 'Agentura Pixel s.r.o.', 'dic' => 'CZ12345678']);
    vatCrRecordVies($org, 'PX Holding a.s.');
    vatCrConfirm($org->fresh());
    $this->travel(31)->days();
    vatCrRecordVies($org->fresh(), 'PX Holding a.s.');

    expect(VatStanding::payerStanding($org->fresh()))->toBe(['payer' => true, 'reason' => 'fresh']);
});

it('takes the confirmation away when the partner changes its name after it', function () {
    $org = vatCrPartner(['name' => 'Agentura Pixel s.r.o.', 'dic' => 'CZ12345678']);
    vatCrConfirm($org->fresh());
    $this->travel(31)->days();
    vatCrRecordVies($org->fresh(), null); // a member state that does not disclose the name: nothing for the name rule to compare
    expect(VatStanding::isVatPayer($org->fresh()))->toBeTrue();

    app(OrganizationService::class)->update($org->fresh(), ['name' => 'Stavební Holding Morava a.s.'], CommandContext::system('test')->withScope($org->id));

    expect(VatStanding::payerStanding($org->fresh()))->toBe(['payer' => false, 'reason' => 'identity_changed']);
    [$snapshot] = vatCrPayOut($org->fresh());
    expect($snapshot['tax_category'])->toBe('E')->and($snapshot['vat_review_reason'])->toBe('identity_changed');
});

it('lists a VIES-valid partner that waits for finance\'s confirmation, and does not ask VIES about it again', function () {
    $org = vatCrPartner(['name' => 'Agentura Pixel s.r.o.', 'dic' => 'CZ12345678']);
    vatCrRecordVies($org, 'Agentura Pixel s.r.o.');

    $this->artisan('onhost:vat:verify')->expectsOutputToContain('supplier_identity')->assertSuccessful();
    config(['onhost.vies.enabled' => true]);
    $this->artisan('onhost:vat:verify', ['--apply' => true, '--pause-ms' => 0])->expectsOutputToContain('skipped 1')->assertSuccessful();
    Http::assertNothingSent();
});

it('still bills a partner of another member state under reverse charge without finance\'s confirmation', function () {
    $org = vatCrPartner(['name' => 'Agentúra Pixel s.r.o.', 'country' => 'SK', 'vat_id' => 'SK1234567890']);
    vatCrRecordVies($org, 'Agentúra Pixel s.r.o.');

    [$snapshot, $postings, $transfer] = vatCrPayOut($org->fresh());
    expect($snapshot['tax_category'])->toBe('AE')->and($snapshot['vat_review'])->toBeFalse()->and($transfer)->toBe(100000)
        ->and($postings)->not->toHaveKey('liability:vat:CZK:debit');
});
