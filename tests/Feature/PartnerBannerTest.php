<?php

declare(strict_types=1);

use App\Domains\Partner\Enums\PartnerStatus;
use App\Domains\Partner\Models\PartnerProfile;
use App\Domains\Partner\Services\PartnerBannerService;
use Spatie\Permission\Models\Permission;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

/**
 * Audit 112 — the partner "Propagační materiály" placeholder is now a real
 * referral-banner generator.
 */

function bannerPartner(string $code = 'BAN01'): \App\Models\User
{
    Permission::findOrCreate('access-partner', 'web');
    $user = customerUser();
    $user->givePermissionTo('access-partner');

    PartnerProfile::create([
        'user_id'       => $user->id,
        'referral_code' => $code,
        'status'        => PartnerStatus::Active->value,
        'rate'          => '10.00',
        'currency'      => 'CZK',
    ]);

    return $user;
}

// ── the service ─────────────────────────────────────────────────────────────────

it('generates a banner for each standard size', function (): void {
    $banners = app(PartnerBannerService::class)->generate('https://onhost.cz/?ref=BAN01');

    expect($banners)->toHaveCount(5)
        ->and(collect($banners)->pluck('size')->all())
        ->toContain('728×90', '300×250', '160×600');
});

it('embeds the referral link in every banner snippet', function (): void {
    $banners = app(PartnerBannerService::class)->generate('https://onhost.cz/?ref=BAN01');

    foreach ($banners as $banner) {
        expect($banner['embed'])
            ->toContain('href="https://onhost.cz/?ref=BAN01"')
            // A paid referral link must disclose rel="sponsored".
            ->toContain('rel="noopener sponsored"')
            ->toContain('<svg');
    }
});

it('escapes the referral URL in the href so it cannot break out of the attribute', function (): void {
    // A crafted code must not inject an attribute or a tag.
    $banners = app(PartnerBannerService::class)->generate('https://onhost.cz/?ref="><script>x');

    foreach ($banners as $banner) {
        expect($banner['embed'])->not->toContain('"><script>');
    }
});

it('offers each banner as a downloadable SVG data URI', function (): void {
    $banners = app(PartnerBannerService::class)->generate('https://onhost.cz/?ref=BAN01');

    expect($banners[0]['download'])->toStartWith('data:image/svg+xml;base64,');

    $svg = base64_decode(substr($banners[0]['download'], strlen('data:image/svg+xml;base64,')));
    expect($svg)->toContain('<svg')->toContain('OnHost.cz');
});

// ── the panel page ──────────────────────────────────────────────────────────────

it('renders the banners on the partner materials page', function (): void {
    $user = bannerPartner('SHOW01');

    $this->actingAs($user)
        ->get(route('partner.assets'))
        ->assertOk()
        ->assertSee('Propagační bannery')
        ->assertSee('Kód pro vložení')
        // The referral link is baked into the embed code shown on the page.
        ->assertSee('?ref=SHOW01', false);
});

it('shows an activation notice instead of banners for an inactive profile', function (): void {
    // A customer with the permission but no active profile gets no banners.
    Permission::findOrCreate('access-partner', 'web');
    $user = customerUser();
    $user->givePermissionTo('access-partner');

    $this->actingAs($user)
        ->get(route('partner.assets'))
        ->assertOk()
        ->assertDontSee('Kód pro vložení');
});
