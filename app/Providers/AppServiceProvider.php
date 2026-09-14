<?php

declare(strict_types=1);

namespace App\Providers;

use App\Http\Support\SurfaceRenderer;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Onhost\Domain\Identity\Models\PersonalAccessToken;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SurfaceRenderer::class, fn () => new SurfaceRenderer((string) config('onhost.ui.surfaces_path', base_path('apps/surfaces'))));
    }

    public function boot(): void
    {
        // API limits (blueprint §17.4): per token/user, public endpoints per IP, brute-force protection on auth.
        RateLimiter::for('api', function (Request $request) {
            $user = $request->user();
            $token = $user !== null && method_exists($user, 'currentAccessToken') ? $user->currentAccessToken() : null;
            $persistent = $token instanceof PersonalAccessToken ? $token : null;
            $perMinute = $persistent !== null && $persistent->rate_limit_per_minute ? (int) $persistent->rate_limit_per_minute : (int) config('onhost.api.default_rate_limit_per_minute', 120);

            return Limit::perMinute($perMinute)->by($persistent !== null ? 'token:'.$persistent->getKey() : ($user ? 'user:'.$user->getAuthIdentifier() : 'ip:'.$request->ip()));
        });
        RateLimiter::for('public', fn (Request $request) => Limit::perMinute((int) config('onhost.api.public_rate_limit_per_minute', 600))->by('ip:'.$request->ip()));
        RateLimiter::for('auth', fn (Request $request) => [Limit::perMinute(10)->by('ip:'.$request->ip()), Limit::perMinute(5)->by('email:'.strtolower((string) $request->input('email')))]);
        RateLimiter::for('probes', fn (Request $request) => Limit::perMinute(600)->by('probe:'.substr((string) $request->bearerToken(), 0, 16).':'.$request->ip()));
        RateLimiter::for('domain-check', fn (Request $request) => Limit::perMinute(30)->by(($request->user() ? 'user:'.$request->user()->getAuthIdentifier() : 'ip:'.$request->ip())));
    }
}
