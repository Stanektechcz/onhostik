<?php

declare(strict_types=1);

use App\Http\Middleware\RememberReferral;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Loyalty\Models\Referral;
use Onhost\Domain\Loyalty\ReferralService;
use Onhost\Domain\Organizations\Models\Organization;

/*
 * Referral landing (audit §5k-4): a visit with `?ref=` remembers the code for 30 days; a sign-up without the parameter
 * is still attributed from the cookie; malformed codes are never stored.
 */

beforeEach(fn () => Http::preventStrayRequests());

it('remembers the invite code from a landing visit and attributes a later sign-up without the parameter', function () {
    [, $org] = $this->customerWithOrganization(['email' => 'jan@firma.cz'], ['name' => 'Firma Jan s.r.o.']);
    $code = app(ReferralService::class)->code($org);
    $cookie = $this->get('/registrace?ref='.strtolower($code))->assertOk()->getCookie(RememberReferral::COOKIE, false);
    expect($cookie)->not->toBeNull()->and($cookie->getValue())->toBe($code)->and($cookie->getExpiresTime())->toBeGreaterThan(now()->addDays(29)->getTimestamp())->and($cookie->isHttpOnly())->toBeTrue();
    expect($this->get('/?ref=<script>')->assertOk()->getCookie(RememberReferral::COOKIE, false))->toBeNull();
    expect($this->get('/sluzby')->assertOk()->getCookie(RememberReferral::COOKIE, false))->toBeNull();

    Http::fake(['https://api.pwnedpasswords.com/*' => Http::response('', 200)]);
    $this->withCredentials()->withUnencryptedCookie(RememberReferral::COOKIE, $code)->postJson('/v1/auth/register', ['name' => 'Petra Nová', 'email' => 'petra@novy-eshop.cz', 'password' => 'Velmi-Silne-Heslo-2026', 'terms' => true])->assertCreated();
    $referred = Organization::query()->where('owner_user_id', User::query()->where('email', 'petra@novy-eshop.cz')->value('id'))->firstOrFail();
    expect(Referral::query()->where('referred_organization_id', $referred->id)->value('referrer_organization_id'))->toBe($org->id);

    // the form's own code wins over the cookie
    auth()->forgetGuards();
    [, $otherOrg] = $this->customerWithOrganization(['email' => 'eva@seznam.cz'], ['name' => 'Eva']);
    $otherCode = app(ReferralService::class)->code($otherOrg);
    $this->withCredentials()->withUnencryptedCookie(RememberReferral::COOKIE, $code)->postJson('/v1/auth/register', ['name' => 'Karel', 'email' => 'karel@centrum.cz', 'password' => 'Velmi-Silne-Heslo-2026', 'terms' => true, 'ref' => $otherCode])->assertCreated();
    $karel = Organization::query()->where('owner_user_id', User::query()->where('email', 'karel@centrum.cz')->value('id'))->firstOrFail();
    expect(Referral::query()->where('referred_organization_id', $karel->id)->value('referrer_organization_id'))->toBe($otherOrg->id);
});
