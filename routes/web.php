<?php

declare(strict_types=1);

use App\Http\Controllers\Web\AffiliateController;
use App\Http\Controllers\Web\BlogController;
use App\Http\Controllers\Web\DomainController;
use App\Http\Controllers\Web\HomeController;
use App\Http\Controllers\Web\HostingController;
use App\Http\Controllers\Web\KbController;
use App\Http\Controllers\Web\LocaleController;
use App\Http\Controllers\Web\OrderController;
use App\Http\Controllers\Web\PageController;
use App\Http\Controllers\Web\StatusController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public website (Antler template, layouts.front)
|--------------------------------------------------------------------------
| Auth routes (login, register, logout, password reset) are registered by
| Laravel Fortify — see App\Providers\FortifyServiceProvider for the views.
*/

Route::get('/', [HomeController::class, 'index'])->name('front.home');

Route::get('/webhosting', [HostingController::class, 'webhosting'])->name('front.webhosting');
Route::get('/wordpress-hosting', [HostingController::class, 'wordpress'])->name('front.wordpress');
Route::get('/managed-hosting', [HostingController::class, 'managed'])->name('front.managed');
Route::get('/gamehosting', [HostingController::class, 'gamehosting'])->name('front.gamehosting');
Route::get('/vps', [HostingController::class, 'vps'])->name('front.vps');
Route::get('/mailhosting', [HostingController::class, 'mailhosting'])->name('front.mailhosting');
Route::get('/dedikovane-servery', [HostingController::class, 'dedicated'])->name('front.dedicated');

Route::get('/funkce/ai-asistent', [PageController::class, 'aiFeature'])->name('front.features.ai');
Route::get('/funkce/monitoring', [PageController::class, 'monitoringFeature'])->name('front.features.monitoring');
Route::get('/funkce/zalohy', [PageController::class, 'backupsFeature'])->name('front.features.backups');
Route::get('/website-builder', [PageController::class, 'builder'])->name('front.builder');
Route::post('/website-builder/waitlist', [PageController::class, 'builderWaitlist'])
    ->middleware('throttle:10,1')
    ->name('front.builder.waitlist');
Route::get('/podpora', [PageController::class, 'support'])->name('front.support');
Route::get('/znalostni-baze', [KbController::class, 'index'])->name('front.kb');

Route::get('/domeny', [DomainController::class, 'index'])->name('front.domains');
Route::post('/domeny/overit', [DomainController::class, 'check'])
    ->middleware('throttle:domain-check')
    ->name('front.domains.check');
Route::post('/domeny/bulk', [DomainController::class, 'bulkCheck'])
    ->middleware('throttle:domain-check')
    ->name('front.domains.bulk');

Route::get('/objednavka/{plan}', [OrderController::class, 'start'])->name('front.order');

Route::get('/kontakt', [PageController::class, 'contact'])->name('front.contact');
Route::post('/kontakt', [PageController::class, 'contactSend'])->name('front.contact.send');
Route::post('/newsletter/prihlasku', [PageController::class, 'newsletterSubscribe'])->name('front.newsletter.subscribe')->middleware('throttle:5,1');
Route::get('/o-nas', [PageController::class, 'about'])->name('front.about');
Route::get('/faq', [PageController::class, 'faq'])->name('front.faq');
Route::get('/obchodni-podminky', [PageController::class, 'legal'])->name('front.legal');
Route::get('/gdpr', [PageController::class, 'gdpr'])->name('front.gdpr');
Route::get('/cookies', [PageController::class, 'cookies'])->name('front.cookies');
Route::get('/sla', [PageController::class, 'sla'])->name('front.sla');
Route::get('/refundace', [PageController::class, 'refundPolicy'])->name('front.refund-policy');

Route::get('/ssl-certifikaty', [PageController::class, 'ssl'])->name('front.ssl');
Route::get('/reseller-hosting', [PageController::class, 'reseller'])->name('front.reseller');
Route::get('/developer', [PageController::class, 'developer'])->name('front.developer');
Route::get('/ddos-ochrana', [PageController::class, 'ddos'])->name('front.ddos');
Route::get('/kolokace', [PageController::class, 'colocation'])->name('front.colocation');
Route::get('/email-security', [PageController::class, 'emailSecurity'])->name('front.email-security');
Route::get('/databaze', [PageController::class, 'database'])->name('front.database');
Route::get('/datacenter', [PageController::class, 'datacenter'])->name('front.datacenter');

Route::get('/blog', [BlogController::class, 'index'])->name('front.blog.index');
Route::get('/blog/{slug}', [BlogController::class, 'show'])->name('front.blog.show');

Route::get('/znalostni-baze/{slug}', [KbController::class, 'show'])->name('front.kb.show');

Route::get('/lang/{locale}', LocaleController::class)->name('locale.switch');

Route::get('/sitemap.xml', [PageController::class, 'sitemap'])->name('front.sitemap');

/* PWA offline fallback — served by the service worker when the network is down. */
Route::view('/offline', 'offline')->name('offline');

/* Security disclosure policy (RFC 9116). */
Route::get('/.well-known/security.txt', function () {
    $lines = [
        'Contact: mailto:' . config('security.disclosure_email', 'security@onhost.cz'),
        'Expires: ' . now()->addYear()->startOfDay()->toIso8601ZuluString(),
        'Preferred-Languages: cs, en',
        'Canonical: ' . url('/.well-known/security.txt'),
    ];

    return response(implode("\n", $lines) . "\n", 200, ['Content-Type' => 'text/plain; charset=utf-8']);
})->name('security-txt');
Route::get('/stav', StatusController::class)->name('front.status');

Route::get('/ref/{code}', AffiliateController::class)->name('front.affiliate');

/* ── Phase 157: Magic Link Login ── */
Route::middleware('throttle:10,1')->group(function (): void {
    Route::get('/magic-link', [\App\Http\Controllers\Auth\MagicLinkController::class, 'showRequestForm'])->name('magic-link.form');
    Route::post('/magic-link', [\App\Http\Controllers\Auth\MagicLinkController::class, 'sendLink'])->name('magic-link.send');
    Route::get('/magic-link/{token}', [\App\Http\Controllers\Auth\MagicLinkController::class, 'login'])->name('magic-link.login');
});

/*
 | OAuth2 authorization-code grant (+ PKCE). Clients are registered in the
 | developer portal; the consent screen runs behind the session guard, the
 | token endpoint is CSRF-exempt (see bootstrap/app.php) and client-authenticated.
 */
Route::middleware('auth')->group(function (): void {
    Route::get('/oauth/authorize', [\App\Http\Controllers\OAuth\AuthorizationController::class, 'show'])->name('oauth.authorize');
    Route::post('/oauth/authorize', [\App\Http\Controllers\OAuth\AuthorizationController::class, 'approve'])->name('oauth.authorize.approve');
    Route::post('/oauth/authorize/deny', [\App\Http\Controllers\OAuth\AuthorizationController::class, 'deny'])->name('oauth.authorize.deny');
});
Route::post('/oauth/token', [\App\Http\Controllers\OAuth\TokenController::class, 'issue'])
    ->middleware('throttle:api')
    ->name('oauth.token');

/*
 | Customer sub-account invitations — accepting a link. Guest-accessible (the
 | invitee may not have an account yet); the token is the credential. Throttled
 | against token guessing.
 */
Route::middleware('throttle:20,1')->group(function (): void {
    Route::get('/pozvanka/{token}', [\App\Http\Controllers\CustomerInvitationController::class, 'show'])->name('invitation.accept');
    Route::post('/pozvanka/{token}', [\App\Http\Controllers\CustomerInvitationController::class, 'accept'])->name('invitation.accept.store');
});
