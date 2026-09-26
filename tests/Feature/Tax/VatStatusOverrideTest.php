<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Identity\StepUp\StepUpService;
use Onhost\Domain\Orders\QuoteService;
use Onhost\Domain\Tax\Models\VatValidation;
use Onhost\Domain\Tax\VatStanding;
use Onhost\Platform\Audit\AuditEvent;
use Onhost\Platform\Outbox\OutboxMessage;

/*
 * TASK-0031 WP B (D31.5): when VIES is down or the customer proves the registration otherwise, finance sets the VAT status by
 * hand — with a reason and the evidence, behind a fresh step-up and a second person (unless the platform runs with one
 * operator), recorded as a vat_validations row and in the audit trail, and only for a limited time.
 */

beforeEach(function () {
    $this->seed([CatalogSeeder::class, TaxRuleSeeder::class, LegalEntitySeeder::class]);
    Http::preventStrayRequests();
    config(['onhost.vies.enabled' => false]);
});

function vatOverrideBody(array $extra = []): array
{
    return array_merge(['status' => 'valid', 'reason' => 'VIES pro DE nedostupné tři dny, zákazník doložil registraci.', 'evidence' => 'Bestätigung BZSt ze dne 2026-09-24, tiket T-4711', 'days' => 30], $extra);
}

it('lets finance set the VAT status by hand behind step-up and four eyes, keeps the evidence, and lets it lapse', function () {
    [, $org] = $this->customerWithOrganization([], ['name' => 'ACME GmbH', 'country' => 'DE', 'vat_id' => 'DE123456789']);
    $url = "/v1/staff/customers/{$org->id}/vat-status";

    $this->actingAs($this->staff('support_l2'), 'sanctum');
    $this->postJson($url, vatOverrideBody())->assertForbidden();

    $finance = $this->staff('billing_finance_admin');
    $this->actingAs($finance, 'sanctum');
    $this->postJson($url, vatOverrideBody())->assertForbidden()->assertJsonPath('error', 'step_up_required');
    app(StepUpService::class)->grant($finance, 'totp', null, '127.0.0.1');
    $approval = $this->postJson($url, vatOverrideBody())->assertForbidden()->assertJsonPath('error', 'approval_required')->json('approval_id');
    expect($approval)->toBeString();
    $this->postJson($url, vatOverrideBody(['approval_ids' => [secondPersonApproves($approval)]]))->assertStatus(202);

    $fresh = $org->fresh();
    expect(VatStanding::effectiveStatus($fresh))->toBe('valid')->and(VatStanding::standing($fresh)['reason'])->toBe('staff_override')
        ->and($fresh->vat_status_source)->toBe('staff')->and($fresh->customer_class)->toBe('b2b');
    $row = VatValidation::query()->where('organization_id', $org->id)->sole();
    expect($row->source)->toBe('staff')->and($row->status)->toBe('valid')->and($row->note)->toContain('VIES pro DE')->and($row->evidence)->toContain('BZSt')
        ->and($row->actor)->toBe($finance->id)->and($row->expires_at)->not->toBeNull();
    expect(AuditEvent::query()->where('action', 'tax.vat_status.override')->exists())->toBeTrue();
    expect(OutboxMessage::query()->where('name', 'tax.vat_number.checked')->where('payload->source', 'staff')->exists())->toBeTrue();

    $quote = fn () => app(QuoteService::class)->quote([['product_key' => 'web-hosting', 'plan_key' => 'start', 'config' => ['fqdn' => 'acme-override.de']]], 'CZK', [], 1, null, $org->fresh());
    expect($quote()->lines[0]['tax_category'])->toBe('AE');

    // the override lapses: destination VAT again, with the review flag
    $this->travelTo(now()->addDays(31));
    expect(VatStanding::effectiveStatus($org->fresh()))->toBe('unknown');
    $lapsed = $quote();
    expect($lapsed->lines[0]['tax_rate'])->toBe('19')->and($lapsed->lines[0]['tax_category'])->toBe('S')->and($lapsed->versions['vat_review'])->toBeTrue();
});

it('needs only the step-up when the platform runs with one operator', function () {
    config(['onhost.identity.four_eyes' => false]);
    [, $org] = $this->customerWithOrganization([], ['name' => 'ACME GmbH', 'country' => 'DE', 'vat_id' => 'DE123456789']);
    $finance = $this->staff('billing_finance_admin');
    $this->actingAs($finance, 'sanctum');
    app(StepUpService::class)->grant($finance, 'totp', null, '127.0.0.1');

    $this->postJson("/v1/staff/customers/{$org->id}/vat-status", vatOverrideBody(['status' => 'invalid', 'days' => 5]))->assertStatus(202);
    expect(VatStanding::effectiveStatus($org->fresh()))->toBe('invalid');
});

it('refuses an override without a reason or evidence, or for a number that cannot be checked', function () {
    [, $org] = $this->customerWithOrganization([], ['name' => 'ACME GmbH', 'country' => 'DE', 'vat_id' => 'DE123456789']);
    [, $swiss] = $this->customerWithOrganization([], ['name' => 'Alpen AG', 'country' => 'CH', 'vat_id' => 'CHE123456789']);
    config(['onhost.identity.four_eyes' => false]);
    $finance = $this->staff('billing_finance_admin');
    $this->actingAs($finance, 'sanctum');
    app(StepUpService::class)->grant($finance, 'totp', null, '127.0.0.1');
    $url = "/v1/staff/customers/{$org->id}/vat-status";

    $this->postJson($url, array_diff_key(vatOverrideBody(), ['reason' => 1]))->assertUnprocessable();
    $this->postJson($url, array_diff_key(vatOverrideBody(), ['evidence' => 1]))->assertUnprocessable();
    $this->postJson($url, vatOverrideBody(['status' => 'unknown']))->assertUnprocessable();
    $this->postJson($url, vatOverrideBody(['days' => 90]))->assertUnprocessable();
    $this->postJson("/v1/staff/customers/{$swiss->id}/vat-status", vatOverrideBody())->assertUnprocessable()->assertJsonPath('error', 'vat_country_not_eu');
    expect(VatValidation::query()->count())->toBe(0);
});
