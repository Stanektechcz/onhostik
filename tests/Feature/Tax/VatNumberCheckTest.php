<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification as NotificationFake;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Notifications\Models\MailOutbox;
use Onhost\Domain\Notifications\Models\Notification;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Tax\Commands\RecordVatCheckCommand;
use Onhost\Domain\Tax\Models\VatValidation;
use Onhost\Domain\Tax\VatNumberChecks;
use Onhost\Platform\Commands\CommandBus;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Outbox\OutboxMessage;
use Onhost\Platform\Outbox\OutboxPublisher;

/*
 * TASK-0031 WP A (D31.2, D31.3a, D31.8): a VAT number that was given is checked in VIES and the answer is kept as evidence on
 * the organization. Before this, domains/Tax/ViesClient had no caller: vat_status never became `valid`, so every EU business
 * customer outside CZ who gave a VAT ID was charged destination VAT although the public pages promise the VIES check. The
 * check runs on the queue after the number is set or changed (system actor, through the bus); an outage writes nothing.
 * VIES is always faked here: the switch is on only inside these tests, and stray requests are refused.
 */

const VAT_CHECK_ENDPOINT = 'https://ec.europa.eu/taxation_customs/vies/rest-api/check-vat-number';

beforeEach(function () {
    Http::preventStrayRequests();
    config(['onhost.vies.enabled' => true, 'onhost.vies.endpoint' => VAT_CHECK_ENDPOINT, 'onhost.vies.requester_vat_id' => 'CZ12345678']);
});

/** VIES answers valid for every number it is asked about (with the trader's name, which must stay out of events). */
function vatCheckFakeValid(string $consultation = 'WAPIAAAAZ9'): void
{
    Http::fake([
        VAT_CHECK_ENDPOINT => fn ($request) => Http::response(['countryCode' => $request['countryCode'], 'vatNumber' => $request['vatNumber'], 'requestDate' => '2026-09-25T10:00:00.000Z', 'valid' => true, 'requestIdentifier' => $consultation, 'name' => 'ACME GmbH', 'address' => 'Hauptstr. 1, 10115 Berlin']),
        'api.pwnedpasswords.com/*' => Http::response(''),
    ]);
}

it('checks the VAT number given at registration and keeps the evidence, without touching the customer class', function () {
    vatCheckFakeValid();
    NotificationFake::fake();

    $this->withHeaders(['Referer' => 'http://localhost'])->postJson('/v1/auth/register', ['name' => 'Hans Muster', 'email' => 'hans@acme.de', 'password' => 'Correct-Horse-Battery-9-Staple', 'organization' => 'ACME GmbH', 'vat_id' => 'DE123456789', 'country' => 'DE', 'terms' => true])->assertCreated();

    $org = Organization::query()->where('owner_user_id', User::query()->where('email', 'hans@acme.de')->value('id'))->sole();
    expect($org->vat_status)->toBe('valid')->and($org->vat_checked_at)->not->toBeNull()->and($org->vat_consultation_number)->toBe('WAPIAAAAZ9')
        ->and($org->vat_checked_number)->toBe('DE123456789')->and($org->vat_status_source)->toBe('vies')->and($org->customer_class)->toBe('b2b');
    $validation = VatValidation::query()->where('organization_id', $org->id)->sole();
    expect($validation->name)->toBe('ACME GmbH')->and($validation->status)->toBe('valid')->and($validation->reason)->toBe('vat_id_changed')->and($validation->requester_vat_id)->toBe('CZ12345678')->and($validation->raw)->toBeNull();
    Http::assertSent(fn ($request) => str_starts_with($request->url(), VAT_CHECK_ENDPOINT) && $request['vatNumber'] === '123456789');

    app(OutboxPublisher::class)->relayPending();
    $event = OutboxMessage::query()->where('name', 'tax.vat_number.checked')->sole();
    expect($event->payload['result'])->toBe('valid')->and($event->payload['previous'])->toBe('unknown')->and($event->payload['changed'])->toBeTrue()
        ->and($event->payload['number_hint'])->toBe('DE…789')->and($event->payload)->not->toHaveKeys(['name', 'address', 'number']);
});

it('keeps the customer class of a company that gave only a VAT number unchanged by the check', function () {
    vatCheckFakeValid();
    [, $org] = $this->customerWithOrganization([], ['country' => 'AT', 'vat_id' => 'ATU12345678', 'type' => 'person']);

    // OrganizationService decides the class from the number given (b2b); the check itself never writes it (C1f is separate)
    expect($org->fresh()->customer_class)->toBe('b2b')->and($org->fresh()->vat_status)->toBe('valid');
});

it('resets the evidence when the number changes and checks the new one', function () {
    vatCheckFakeValid();
    [$owner, $org] = $this->customerWithOrganization([], ['country' => 'DE', 'vat_id' => 'DE123456789']);
    expect($org->fresh()->vat_checked_number)->toBe('DE123456789');

    $this->actingAs($owner, 'sanctum');
    $this->withHeaders(['X-Organization' => $org->id, 'Idempotency-Key' => 'vat-change-1'])->patchJson("/v1/organizations/{$org->id}", ['vat_id' => 'DE987654321'])->assertOk();

    $fresh = $org->fresh();
    expect($fresh->vat_checked_number)->toBe('DE987654321')->and($fresh->vat_status)->toBe('valid')->and(VatValidation::query()->where('organization_id', $org->id)->count())->toBe(2);
    Http::assertSentCount(2);
});

it('resets the evidence when the number is removed, and checks nothing', function () {
    vatCheckFakeValid();
    [$owner, $org] = $this->customerWithOrganization([], ['country' => 'DE', 'vat_id' => 'DE123456789']);

    $this->actingAs($owner, 'sanctum');
    $this->withHeaders(['X-Organization' => $org->id, 'Idempotency-Key' => 'vat-change-2'])->patchJson("/v1/organizations/{$org->id}", ['vat_id' => null])->assertOk();

    $fresh = $org->fresh();
    expect($fresh->vat_status)->toBe('unknown')->and($fresh->vat_checked_number)->toBeNull()->and($fresh->vat_checked_at)->toBeNull()->and($fresh->vat_consultation_number)->toBeNull()->and($fresh->vat_status_source)->toBeNull();
    Http::assertSentCount(1);
});

it('checks the VAT number a guest gave in the checkout', function () {
    $this->seed([CatalogSeeder::class, TaxRuleSeeder::class, LegalEntitySeeder::class]);
    NotificationFake::fake();
    vatCheckFakeValid();

    $this->withHeaders(['Referer' => 'http://localhost', 'Idempotency-Key' => 'vat-guest-1'])->postJson('/v1/checkout/guest', [
        'customer' => ['email' => 'buyer@acme.de', 'name' => 'Hans Muster', 'company' => 'ACME GmbH', 'vat_id' => 'DE123456789', 'country' => 'DE', 'street' => 'Hauptstr. 1', 'city' => 'Berlin', 'postal_code' => '10115'],
        'items' => [['product_key' => 'web-hosting', 'plan_key' => 'start']],
        'commit_months' => 12, 'currency' => 'CZK',
        'consents' => ['terms' => ['version' => '2026-09'], 'privacy' => [], 'dpa' => [], 'withdrawal_waiver' => []],
        'payment' => ['mode' => 'bank'], 'terms' => true,
    ])->assertCreated();

    $org = Organization::query()->where('billing_email', 'buyer@acme.de')->sole();
    expect($org->vat_status)->toBe('valid')->and($org->vat_checked_number)->toBe('DE123456789');
    Http::assertSentCount(1);
});

it('writes nothing and publishes nothing while VIES cannot answer, and keeps the stored status', function () {
    Http::fake([VAT_CHECK_ENDPOINT => Http::response(['actionSucceed' => false, 'errorWrappers' => [['error' => 'MS_UNAVAILABLE', 'message' => 'member state down']]], 500)]);
    [, $org] = $this->customerWithOrganization([], ['country' => 'DE', 'vat_id' => 'DE123456789']);

    expect($org->fresh()->vat_status)->toBe('unknown')->and($org->fresh()->vat_checked_at)->toBeNull()
        ->and(VatValidation::query()->count())->toBe(0)->and(OutboxMessage::query()->where('name', 'tax.vat_number.checked')->exists())->toBeFalse();
    // a checkout within the retry window after an unknown answer does not ask again: every cart quote would hammer a VIES that is down
    expect(app(VatNumberChecks::class)->check($org->fresh(), 'checkout', 5))->toBe('unknown');
    Http::assertSentCount(1);
    // the operator's command is not throttled
    expect(app(VatNumberChecks::class)->check($org->fresh(), 'operator', 8))->toBe('unknown');
    Http::assertSentCount(2);
});

it('tells the billing contacts when the number came back invalid', function () {
    Http::fake([VAT_CHECK_ENDPOINT => Http::response(['countryCode' => 'DE', 'vatNumber' => '123456789', 'valid' => false, 'userError' => 'INVALID', 'name' => '---', 'address' => '---'])]);
    [, $org] = $this->customerWithOrganization([], ['country' => 'DE', 'vat_id' => 'DE123456789', 'billing_email' => 'billing@acme.de']);
    app(OutboxPublisher::class)->relayPending();

    expect($org->fresh()->vat_status)->toBe('invalid');
    $note = Notification::query()->where('organization_id', $org->id)->where('event', 'tax.vat_number.checked')->sole();
    expect($note->kind)->toBe('billing')->and($note->audience)->toBe('customer')->and($note->body)->toContain('DE…789');
    $mail = MailOutbox::query()->where('template_key', 'vat-number-invalid')->sole();
    expect($mail->to)->toBe('billing@acme.de')->and((array) $mail->vars)->toHaveKey('dic', 'DE…789');
});

it('never tells a Czech organization that it is charged the VAT of its country', function () {
    Http::fake([VAT_CHECK_ENDPOINT => Http::response(['countryCode' => 'CZ', 'vatNumber' => '12345678', 'valid' => false, 'userError' => 'INVALID'])]);
    [, $org] = $this->customerWithOrganization([], ['country' => 'CZ', 'dic' => 'CZ12345678']);
    app(OutboxPublisher::class)->relayPending();

    $note = Notification::query()->where('organization_id', $org->id)->where('event', 'tax.vat_number.checked')->sole();
    expect($org->fresh()->vat_status)->toBe('invalid')->and($note->body)->not->toContain('vaší země');
});

it('never checks a number from outside the EU and never calls it invalid', function () {
    Http::fake();
    [, $swiss] = $this->customerWithOrganization([], ['country' => 'CH', 'vat_id' => 'CHE-123.456.789']);
    [, $british] = $this->customerWithOrganization([], ['country' => 'DE', 'vat_id' => 'GB123456789']);

    expect($swiss->fresh()->vat_status)->toBe('unknown')->and($british->fresh()->vat_status)->toBe('unknown')
        ->and(VatValidation::query()->count())->toBe(0)->and(OutboxMessage::query()->where('name', 'tax.vat_number.checked')->exists())->toBeFalse();
    Http::assertNothingSent();
});

it('asks nothing and keeps nothing for a number longer than the evidence columns', function () {
    Http::fake();
    // 20 digits without a prefix become DE + 20 = 22 characters: no member state's number, and vat_validations.vat_id holds 20
    $org = Organization::query()->create(['slug' => 'vat-too-long', 'name' => 'Long GmbH', 'owner_user_id' => 'usr_long', 'country' => 'DE', 'vat_id' => '12345678901234567890', 'customer_class' => 'b2b']);

    expect(app(VatNumberChecks::class)->check($org, 'operator', 8))->toBe('skipped')
        ->and(VatValidation::query()->count())->toBe(0)->and($org->fresh()->vat_checked_number)->toBeNull();
    Http::assertNothingSent();
});

it('refuses a recorded check from anybody but the system', function () {
    [$owner, $org] = $this->customerWithOrganization([], ['country' => 'DE']);
    $org->forceFill(['vat_id' => 'DE123456789'])->save();

    expect(fn () => app(CommandBus::class)->dispatch(new RecordVatCheckCommand($org->id, 'vat-forged-1', ['number' => 'DE123456789', 'status' => 'valid', 'trigger' => 'operator']), $this->contextFor($owner, $org)))
        ->toThrow(DomainError::class);
    expect($org->fresh()->vat_status)->toBe('unknown')->and(VatValidation::query()->count())->toBe(0);
});

it('discards a result for a number that changed meanwhile', function () {
    [, $org] = $this->customerWithOrganization([], ['country' => 'DE']);
    $org->forceFill(['vat_id' => 'DE987654321'])->save();

    $result = app(CommandBus::class)->dispatch(new RecordVatCheckCommand($org->id, 'vat-late-1', ['number' => 'DE123456789', 'status' => 'valid', 'trigger' => 'vat_id_changed', 'source' => 'vies']), CommandContext::system('test'));

    expect($result)->toMatchArray(['recorded' => false, 'reason' => 'number_changed'])->and($org->fresh()->vat_status)->toBe('unknown')->and(VatValidation::query()->count())->toBe(0);
});

it('does nothing at all while the VIES switch is off (the default)', function () {
    config(['onhost.vies.enabled' => false]);
    Http::fake();
    [$owner, $org] = $this->customerWithOrganization([], ['country' => 'DE', 'vat_id' => 'DE123456789']);

    expect(app(VatNumberChecks::class)->check($org, 'operator', 8))->toBe('skipped')
        ->and($org->fresh()->vat_status)->toBe('unknown')->and($org->fresh()->vat_checked_at)->toBeNull()->and(VatValidation::query()->count())->toBe(0);
    Http::assertNothingSent();
});
