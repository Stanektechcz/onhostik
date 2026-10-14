<?php

declare(strict_types=1);

use App\Http\Middleware\RememberReferral;
use App\Http\Support\CookieInventory;
use Database\Seeders\LegalEntitySeeder;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Loyalty\Models\Referral;
use Onhost\Domain\Loyalty\ReferralService;
use Onhost\Domain\Organizations\Models\Organization;

/*
 * P20 (phase Q, P-R75 / RF-1, variant C until the lawyer answers LG-4): the referral cookie `onhost_ref` (30 days, set after a
 * `?ref=` link) is probably not strictly necessary, so it would need consent — and there is no consent banner. It is off by default
 * (`onhost.partners.referral_cookie`); the code of the link travels in the sign-up request instead (the session bridge sends the
 * `?ref=` of the address as both the partner and the invite code), so attribution survives without anything stored in the
 * browser. With the switch on (variant A after the lawyer), the cookie, its use at sign-up and its row on the privacy page return.
 */

beforeEach(function () {
    Http::preventStrayRequests();
    Http::fake(['https://api.pwnedpasswords.com/*' => Http::response('', 200)]);
});

/** @return array{0: Organization, 1: string} the referrer and its invite code */
function p20Referrer(Organization $org): array
{
    auth()->forgetGuards();

    return [$org, app(ReferralService::class)->code($org)];
}

function p20Register(object $test, string $email, array $extra = [], ?string $cookie = null): Organization
{
    $request = $cookie === null ? $test->withCredentials() : $test->withCredentials()->withUnencryptedCookie(RememberReferral::COOKIE, $cookie);
    $request->postJson('/v1/auth/register', ['name' => 'Nový Zákazník', 'email' => $email, 'password' => 'Velmi-Silne-Heslo-2026', 'terms' => true] + $extra)->assertCreated();
    auth()->forgetGuards();

    return Organization::query()->where('owner_user_id', User::query()->where('email', $email)->value('id'))->firstOrFail();
}

it('sets no referral cookie, lists none and ignores an old one by default (variant C)', function () {
    $this->seed([LegalEntitySeeder::class]);
    expect(config('onhost.partners.referral_cookie'))->toBeFalse();
    [, $code] = p20Referrer($this->customerWithOrganization(['email' => 'doporucitel@firma.cz'], ['name' => 'Doporučitel s.r.o.'])[1]);
    expect($this->get('/registrace?ref='.strtolower($code))->assertOk()->getCookie(RememberReferral::COOKIE, false))->toBeNull();
    expect(array_column(CookieInventory::rows(), 'name'))->not->toContain(RememberReferral::COOKIE);
    expect((string) $this->get('/dokumenty/ochrana-osobnich-udaju')->assertOk()->getContent())->not->toContain('<code>onhost_ref</code>');

    $organization = p20Register($this, 'stary-cookie@example.cz', [], $code); // a cookie left from before the switch is not read
    expect(Referral::query()->where('referred_organization_id', $organization->id)->exists())->toBeFalse();
});

it('keeps the attribution in variant C: the code of the link arrives with the sign-up request', function () {
    [$referrer, $code] = p20Referrer($this->customerWithOrganization(['email' => 'doporucitel@firma.cz'], ['name' => 'Doporučitel s.r.o.'])[1]);
    $organization = p20Register($this, 'z-odkazu@example.cz', ['ref' => strtolower($code), 'partner_code' => strtolower($code)]);
    $referral = Referral::query()->where('referred_organization_id', $organization->id)->firstOrFail();
    expect($referral->referrer_organization_id)->toBe($referrer->id)->and($referral->code)->toBe($code);

    // the session bridge sends the address's ?ref= as both codes (the bridge is served as is)
    $bridge = (string) file_get_contents(base_path('apps/surfaces/api/onhost-session-bridge.js'));
    expect($bridge)->toContain('partner_code: ref || undefined')->toContain('ref: ref || undefined');
});

it('brings the cookie, its use at sign-up and its privacy row back with the switch on (variant A)', function () {
    config()->set('onhost.partners.referral_cookie', true);
    [$referrer, $code] = p20Referrer($this->customerWithOrganization(['email' => 'doporucitel@firma.cz'], ['name' => 'Doporučitel s.r.o.'])[1]);
    $cookie = $this->get('/registrace?ref='.strtolower($code))->assertOk()->getCookie(RememberReferral::COOKIE, false);
    expect($cookie)->not->toBeNull()->and($cookie->getValue())->toBe($code)->and($cookie->isHttpOnly())->toBeTrue();
    expect(array_column(CookieInventory::rows(), 'name'))->toContain(RememberReferral::COOKIE);

    $organization = p20Register($this, 'z-cookie@example.cz', [], $code);
    expect(Referral::query()->where('referred_organization_id', $organization->id)->value('referrer_organization_id'))->toBe($referrer->id);
});
