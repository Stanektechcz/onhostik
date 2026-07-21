<?php

declare(strict_types=1);

use App\Domains\Partner\Enums\PartnerStatus;
use App\Domains\Partner\Enums\ReferralStatus;
use App\Domains\Partner\Models\PartnerProfile;
use App\Domains\Partner\Models\PartnerReferral;
use Illuminate\Support\Facades\Crypt;
use Spatie\Permission\Models\Permission;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

/**
 * The partner "Stav programu" card and payout settings showed MANUAL /
 * PŘIPRAVUJEME placeholders even though the data and encrypted store already
 * existed. These finish the wiring.
 */

function detailsPartner(float $rate = 15.0): array
{
    Permission::findOrCreate('access-partner', 'web');
    $user = customerUser();
    $user->givePermissionTo('access-partner');

    $profile = PartnerProfile::create([
        'user_id'                 => $user->id,
        'referral_code'           => 'DET01',
        'status'                  => PartnerStatus::Active->value,
        'commission_rate_percent' => $rate,
    ]);

    return [$user, $profile];
}

// ── Stav programu card: real values, not placeholders ────────────────────────────

it('shows the real commission rate instead of a MANUAL placeholder', function (): void {
    [$user] = detailsPartner(15.0);

    $this->actingAs($user)
        ->get(route('partner.assets'))
        ->assertOk()
        ->assertSee('15 %')
        ->assertDontSee('Kontaktujte nás <span', false);
});

it('shows the tracked referral and conversion counts', function (): void {
    [$user, $profile] = detailsPartner();

    PartnerReferral::create(['partner_profile_id' => $profile->id, 'referral_code' => 'DET01', 'status' => ReferralStatus::Visitor->value, 'first_seen_at' => now()]);
    PartnerReferral::create(['partner_profile_id' => $profile->id, 'referral_code' => 'DET01', 'status' => ReferralStatus::Customer->value, 'first_seen_at' => now()]);

    $this->actingAs($user)
        ->get(route('partner.assets'))
        ->assertOk()
        ->assertSee('Sledované referraly')
        ->assertDontSee('Sledování konverzí')   // the old PŘIPRAVUJEME row
        ->assertSee('1 konverzí');
});

// ── Payout details form ─────────────────────────────────────────────────────────

it('saves payout details encrypted and never logs the account number', function (): void {
    [$user, $profile] = detailsPartner();

    $this->actingAs($user)
        ->post(route('partner.profile.payout'), [
            'payout_method'  => 'bank_transfer',
            'account_holder' => 'Jan Novák',
            'account_number' => 'CZ6508000000192000145399',
        ])
        ->assertRedirect();

    $fresh = $profile->fresh();
    expect($fresh->payout_method)->toBe('bank_transfer')
        ->and($fresh->getPayoutDetails()['account_number'])->toBe('CZ6508000000192000145399')
        // Stored ciphertext must not contain the plaintext account.
        ->and($fresh->payout_details_encrypted)->not->toContain('CZ6508000000192000145399');

    // The activity log records the change but NOT the account number.
    $activity = \Spatie\Activitylog\Models\Activity::where('description', 'partner.payout_details_updated')->firstOrFail();
    expect(json_encode($activity->properties))->not->toContain('192000145399');
});

it('shows the saved account masked on the profile page', function (): void {
    [$user, $profile] = detailsPartner();
    $profile->setPayoutDetails(['account_holder' => 'Jan Novák', 'account_number' => 'CZ6508000000192000145399']);
    $profile->save();

    $this->actingAs($user)
        ->get(route('partner.profile'))
        ->assertOk()
        // last 4 shown, the rest masked — full number never rendered.
        ->assertSee('5399')
        ->assertDontSee('CZ6508000000192000145399');
});

it('validates that an account number is provided', function (): void {
    [$user] = detailsPartner();

    $this->actingAs($user)
        ->from(route('partner.profile'))
        ->post(route('partner.profile.payout'), ['payout_method' => 'bank_transfer', 'account_holder' => 'Jan Novák'])
        ->assertSessionHasErrors('account_number');
});

it('forbids a non-partner from saving payout details', function (): void {
    $this->actingAs(customerUser())
        ->post(route('partner.profile.payout'), [
            'payout_method' => 'bank_transfer', 'account_holder' => 'X', 'account_number' => 'CZ00',
        ])
        ->assertForbidden();
});
