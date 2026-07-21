<?php

declare(strict_types=1);

use App\Domains\Integrations\Models\IntegrationSetting;
use App\Models\DomainTransferRequest;
use Database\Seeders\IntegrationSeeder;
use Database\Seeders\ProductCatalogSeeder;
use Illuminate\Support\Facades\DB;

/**
 * Domain transfer-in flow (audit F86).
 *
 * The EPP auth code is a credential — whoever holds it can move the domain
 * to another registrar — so it must be encrypted at rest and never appear in
 * notes, logs or serialized output.
 */
beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, IntegrationSeeder::class]);
});

function transferRequest(string $status = 'pending', string $authCode = 'EPP-SECRET-1234'): DomainTransferRequest
{
    return DomainTransferRequest::create([
        'customer_id' => customerUser()->customer->id,
        'domain_name' => 'prenaseno.cz',
        'auth_code'   => $authCode,
        'status'      => $status,
    ]);
}

it('encrypts the transfer auth code at rest', function (): void {
    $transfer = transferRequest(authCode: 'EPP-SECRET-1234');

    $raw = DB::table('domain_transfer_requests')->where('id', $transfer->id)->value('auth_code');

    // Readable through the model, unreadable in the table.
    expect($transfer->fresh()->auth_code)->toBe('EPP-SECRET-1234')
        ->and((string) $raw)->not->toContain('EPP-SECRET-1234');
});

it('hides the auth code from serialization', function (): void {
    $transfer = transferRequest();

    expect(json_encode($transfer->toArray()) ?: '')->not->toContain('EPP-SECRET-1234');
});

it('lets a customer request a transfer', function (): void {
    $user = customerUser();

    $this->actingAs($user)
        ->post(route('panel.domain-transfer-requests.store'), [
            'domain_name' => 'novadomena.cz',
            'auth_code'   => 'ABC-123-XYZ',
        ])
        ->assertRedirect();

    expect(DomainTransferRequest::where('domain_name', 'novadomena.cz')->exists())->toBeTrue();
});

it('renders the admin transfer queue', function (): void {
    transferRequest();

    $this->actingAs(adminUser())
        ->get(route('admin.domain-transfer-requests.index'))
        ->assertOk()
        ->assertSee('prenaseno.cz');
});

it('initiates the transfer at the registrar when an admin approves it', function (): void {
    IntegrationSetting::query()->updateOrCreate(
        ['provider' => 'wedos'],
        ['label' => 'WEDOS', 'is_active' => true, 'mock_mode' => true, 'dry_run' => true],
    );

    $transfer = transferRequest();

    $this->actingAs(adminUser())
        ->patch(route('admin.domain-transfer-requests.update', $transfer), ['status' => 'processing'])
        ->assertRedirect();

    // Approval is the trigger — previously it only relabelled the row.
    expect($transfer->fresh()->status)->toBe('processing')
        ->and(\Spatie\Activitylog\Models\Activity::where('description', 'domain.transfer_status_changed')->exists())->toBeTrue();
});

it('notes that nothing was automated when no registrar is configured', function (): void {
    IntegrationSetting::query()->where('provider', 'wedos')->delete();

    $transfer = transferRequest();

    $this->actingAs(adminUser())
        ->patch(route('admin.domain-transfer-requests.update', $transfer), ['status' => 'processing'])
        ->assertRedirect();

    // The admin's chosen status stands — they may be running the transfer by
    // hand — but the note says no automatic transfer was started.
    expect($transfer->fresh()->status)->toBe('processing')
        ->and($transfer->fresh()->admin_note)->toContain('není nakonfigurován');
});

it('never writes the auth code into the admin note', function (): void {
    IntegrationSetting::query()->where('provider', 'wedos')->delete();

    $transfer = transferRequest(authCode: 'EPP-SECRET-1234');

    $this->actingAs(adminUser())
        ->patch(route('admin.domain-transfer-requests.update', $transfer), ['status' => 'processing']);

    expect((string) $transfer->fresh()->admin_note)->not->toContain('EPP-SECRET-1234');
});

it('does not re-initiate a transfer already in processing', function (): void {
    $transfer = transferRequest('processing');

    $this->actingAs(adminUser())
        ->patch(route('admin.domain-transfer-requests.update', $transfer), ['status' => 'processing'])
        ->assertRedirect();

    // Still processing — no failure from a duplicate registrar call.
    expect($transfer->fresh()->status)->toBe('processing');
});

it('forbids a customer from approving transfers', function (): void {
    $transfer = transferRequest();

    $this->actingAs(customerUser())
        ->patch(route('admin.domain-transfer-requests.update', $transfer), ['status' => 'completed'])
        ->assertForbidden();

    expect($transfer->fresh()->status)->toBe('pending');
});
