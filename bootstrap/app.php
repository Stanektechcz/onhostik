<?php

use App\Http\Middleware\DetectResellerDomain;
use App\Http\Middleware\HandleReferralCookie;
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
        $middleware->web(append: [
            SetLocale::class,
            HandleReferralCookie::class,
            SecurityHeaders::class,
            DetectResellerDomain::class,
        ]);

        $middleware->alias([
            'require-admin-2fa' => RequireAdminTwoFactor::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();

// Translations live in the base lang/ directory. Pin it explicitly: an
// (empty) legacy resources/lang directory would otherwise silently win
// the lang_path() resolution and break every __() lookup.
$app->useLangPath(dirname(__DIR__) . DIRECTORY_SEPARATOR . 'lang');

return $app;
