<?php

declare(strict_types=1);

use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Identity\Authorization\Models\Approval;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Identity\StepUp\StepUpService;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Partners\PartnerService;
use Onhost\Domain\Tax\Commands\OverrideVatStatusCommand;
use Onhost\Domain\Tax\Commands\OverrideVatStatusHandler;
use Onhost\Domain\Tax\Commands\RecordVatCheckCommand;
use Onhost\Domain\Tax\Models\VatValidation;
use Onhost\Domain\Tax\VatStanding;
use Onhost\Platform\Commands\CommandBus;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;

/*
 * TASK-0031, found at the stack polish (MEDIUM, money): the staff VAT override bound only {organization_id, status, reason,
 * evidence, days}. The handler read the organization's CURRENT number and name when the approved command ran — up to a day
 * after the request — so a partner that controls its own `dic`/`vat_id`/`name` could switch to another real company's DIČ
 * and name in that window: the override row then confirmed the wrong subject and VAT was paid out on it
 * (supplierIdentity() read it as confirmed). The subject the requester saw — the normalised number and the organization
 * name — is now part of the payload, so the approval's hash binds it, and the handler refuses a subject that changed.
 */

beforeEach(function () {
    $this->seed([TaxRuleSeeder::class, LegalEntitySeeder::class]);
    Http::preventStrayRequests();
    config(['onhost.vies.enabled' => false]);
});

function vatSubjectBody(): array
{
    return ['status' => 'valid', 'reason' => 'Dodavatel ověřen: smlouva, výpis z registru plátců DPH', 'evidence' => 'výpis z registru plátců DPH ze dne 2026-09-26', 'days' => 30];
}

/** An approved Czech partner whose number VIES confirmed under its own name — it waits for finance's confirmation. */
function vatSubjectPartner(string $name, string $dic): Organization
{
    $org = Organization::query()->create(['name' => $name, 'dic' => $dic, 'slug' => 'subj-'.uniqid(), 'owner_user_id' => 'usr_subj', 'customer_class' => 'b2b', 'currency' => 'CZK', 'country' => 'CZ']);
    $partners = app(PartnerService::class);
    $partners->approve($partners->apply($org->fresh(), ['model' => 'share'], CommandContext::system('test')), CommandContext::system('test'));
    app(CommandBus::class)->dispatch(new RecordVatCheckCommand($org->id, 'vat-subj:'.$org->id.':'.uniqid('', true), [
        'number' => (string) VatStanding::subject($org)?->value, 'status' => 'valid', 'consultation_number' => 'WAPISUBJ', 'trigger' => 'operator', 'source' => 'vies', 'name' => mb_strtoupper($name),
    ]), CommandContext::system('test'));

    return $org->fresh();
}

/** Finance asks for the override (step-up given); returns the id of the approval the refusal opened. */
function vatSubjectRequest(object $test, User $finance, Organization $org): string
{
    $test->actingAs($finance, 'sanctum');
    app(StepUpService::class)->grant($finance, 'totp', null, '127.0.0.1');

    return (string) $test->postJson("/v1/staff/customers/{$org->id}/vat-status", vatSubjectBody())->assertForbidden()->assertJsonPath('error', 'approval_required')->json('approval_id');
}

it('binds the number and the name the requester saw into the approval', function () {
    $org = vatSubjectPartner('Stavební Holding Morava a.s.', 'CZ12345678');
    $approval = Approval::query()->findOrFail(vatSubjectRequest($this, $this->staff('billing_finance_admin'), $org));

    expect(data_get($approval->payload, 'command.payload.vat_number'))->toBe('CZ12345678')
        ->and(data_get($approval->payload, 'command.payload.organization_name'))->toBe('Stavební Holding Morava a.s.');
});

it('refuses the approved override when the partner switched to another company\'s DIČ and name meanwhile, and pays no VAT', function () {
    $org = vatSubjectPartner('Malá Firma s.r.o.', 'CZ12345678');
    $approvalId = vatSubjectRequest($this, $this->staff('billing_finance_admin'), $org);

    // in the approval window the partner takes a real company's number and name (self-service fields)
    $org->forceFill(['dic' => 'CZ87654321', 'name' => 'Stavební Holding Morava a.s.'])->save();
    secondPersonApproves($approvalId);

    $response = $this->postJson("/v1/staff/customers/{$org->id}/vat-status", vatSubjectBody() + ['approval_ids' => [$approvalId]]);
    expect($response->status())->toBe(403, "repeat: {$response->status()} {$response->json('error')}")
        ->and($response->json('error'))->toBe('approval_required'); // a new request, for the subject as it is now

    expect(VatValidation::query()->where('organization_id', $org->id)->where('source', 'staff')->exists())->toBeFalse()
        ->and($org->fresh()->vat_status_source)->not->toBe('staff')
        ->and(VatStanding::payerStanding($org->fresh())['payer'])->toBeFalse()
        ->and(Approval::query()->findOrFail($approvalId)->consumed_at)->toBeNull();
    $fresh = Approval::query()->where('id', '!=', $approvalId)->where('action', 'tax.vat_status.override')->sole();
    expect(data_get($fresh->payload, 'command.payload.vat_number'))->toBe('CZ87654321');
});

it('refuses in the handler an override whose subject is not the organization\'s any more — number or name — and writes nothing', function (array $change) {
    $org = vatSubjectPartner('Malá Firma s.r.o.', 'CZ12345678');
    $bound = vatSubjectBody() + ['organization_id' => $org->id, 'vat_number' => 'CZ12345678', 'organization_name' => 'Malá Firma s.r.o.'];
    $org->forceFill($change)->save();

    try {
        app(OverrideVatStatusHandler::class)->handle(new OverrideVatStatusCommand('vat-subj-stale:'.uniqid(), $bound), CommandContext::system('test'));
        $this->fail('an override was applied to a subject the requester never saw');
    } catch (DomainError $e) {
        expect($e->status)->toBe(409)->and($e->error)->toBe('vat_override_subject_changed');
    }
    expect(VatValidation::query()->where('organization_id', $org->id)->where('source', 'staff')->exists())->toBeFalse()
        ->and($org->fresh()->vat_status_source)->not->toBe('staff')
        ->and(VatStanding::payerStanding($org->fresh())['payer'])->toBeFalse();
})->with([
    'another DIČ' => [['dic' => 'CZ87654321']],
    'another name' => [['name' => 'Stavební Holding Morava a.s.']],
    'both' => [['dic' => 'CZ87654321', 'name' => 'Stavební Holding Morava a.s.']],
]);

it('refuses an override that does not name its subject at all', function () {
    $org = vatSubjectPartner('Malá Firma s.r.o.', 'CZ12345678');

    expect(fn () => app(OverrideVatStatusHandler::class)->handle(new OverrideVatStatusCommand('vat-subj-bare:'.uniqid(), vatSubjectBody() + ['organization_id' => $org->id]), CommandContext::system('test')))
        ->toThrow(DomainError::class, 'The VAT number or the name of the organization changed');
    expect(VatValidation::query()->where('organization_id', $org->id)->where('source', 'staff')->exists())->toBeFalse();
});

it('applies the approved override when the subject is unchanged, and the supplier is confirmed', function () {
    $org = vatSubjectPartner('Malá Firma s.r.o.', 'CZ12345678');
    $approvalId = vatSubjectRequest($this, $this->staff('billing_finance_admin'), $org);
    secondPersonApproves($approvalId);

    $this->postJson("/v1/staff/customers/{$org->id}/vat-status", vatSubjectBody() + ['approval_ids' => [$approvalId]])->assertStatus(202);
    $row = VatValidation::query()->where('organization_id', $org->id)->where('source', 'staff')->sole();
    expect($row->vat_id)->toBe('CZ12345678')->and($row->name)->toBe('Malá Firma s.r.o.')
        ->and(VatStanding::payerStanding($org->fresh())['payer'])->toBeTrue();
});
