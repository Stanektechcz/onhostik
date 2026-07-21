<?php

use App\Http\Middleware\DetectResellerDomain;
use App\Http\Middleware\HandleReferralCookie;
use App\Http\Middleware\LogApiUsage;
use App\Http\Middleware\RequireAdminTwoFactor;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        channels: __DIR__ . '/../routes/channels.php',
        health: '/up',
        then: function (): void {
            // Customer portal + admin routes (auth-gated, see routes/panel.php).
            Route::middleware('web')->group(base_path('routes/panel.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Prepended, not appended: an id is only useful if it exists before
        // anything downstream has a chance to log or fail.
        $middleware->web(prepend: [\App\Http\Middleware\AssignRequestId::class]);
        $middleware->api(prepend: [\App\Http\Middleware\AssignRequestId::class]);

        $middleware->web(append: [
            SetLocale::class,
            HandleReferralCookie::class,
            SecurityHeaders::class,
            DetectResellerDomain::class,
            // Time-box "log in as customer" so it cannot sit open indefinitely.
            \App\Http\Middleware\StopExpiredImpersonation::class,
        ]);

        $middleware->alias([
            'require-admin-2fa'    => RequireAdminTwoFactor::class,
            'idempotency'          => \App\Http\Middleware\EnforceIdempotency::class,
            'require-customer-2fa' => \App\Http\Middleware\RequireCustomerTwoFactor::class,
            'log-api-usage'        => LogApiUsage::class,
            'admin-ip-allowlist'   => \App\Http\Middleware\CheckAdminIpAllowlist::class,
            'api-lifecycle'        => \App\Http\Middleware\AnnounceApiLifecycle::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        /*
         | Audit J141 — error tracking.
         |
         | No third-party tracker is wired: sentry/sentry-laravel is a composer
         | dependency plus an account and a DSN, which is an operator decision,
         | not something to slip in. What IS done here is the part that has to
         | be right BEFORE a tracker is added — the context each report carries,
         | and the guarantee that it carries no secrets. Bolting a tracker onto
         | unredacted context ships credentials to a third party on the first
         | exception.
         |
         | See config/error-tracking.php for the wiring point.
         */
        $exceptions->context(fn (): array => \App\Domains\Shared\Support\ErrorContext::current());

        // Expected, high-volume, and not a defect: logging them buries the
        // reports that do matter.
        $exceptions->dontReport([
            \Illuminate\Auth\AuthenticationException::class,
            \Illuminate\Auth\Access\AuthorizationException::class,
            \Illuminate\Validation\ValidationException::class,
            \Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class,
            \Illuminate\Http\Exceptions\ThrottleRequestsException::class,
        ]);
    })->create();

// Translations live in the base lang/ directory. Pin it explicitly: an
// (empty) legacy resources/lang directory would otherwise silently win
// the lang_path() resolution and break every __() lookup.
$app->useLangPath(dirname(__DIR__) . DIRECTORY_SEPARATOR . 'lang');

return $app;
