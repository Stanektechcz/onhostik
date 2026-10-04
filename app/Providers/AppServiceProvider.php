<?php

declare(strict_types=1);

namespace App\Providers;

use App\Http\Support\SurfaceRenderer;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Queue\Events\Looping;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Onhost\Domain\Identity\Models\PersonalAccessToken;
use Onhost\Domain\Platform\QueueLaneHeartbeat;
use Onhost\Platform\Dns\RecordResolver;
use Onhost\Platform\Dns\SystemRecordResolver;
use Onhost\Platform\Http\DnsHostResolver;
use Onhost\Platform\Http\HostResolver;
use Onhost\Platform\Tls\CertificateReader;
use Onhost\Platform\Tls\PeerCertificateReader;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(HostResolver::class, DnsHostResolver::class); // what a customer-named destination resolves to (EgressGuard)
        $this->app->bind(RecordResolver::class, SystemRecordResolver::class); // what the internet answers for a customer's domain (PublicDnsCheck)
        $this->app->bind(CertificateReader::class, PeerCertificateReader::class); // what a node really serves for a site (CertificateWatch)
        $this->app->singleton(SurfaceRenderer::class, fn () => new SurfaceRenderer((string) config('onhost.ui.surfaces_path', base_path('apps/surfaces'))));
        $this->app->singleton(QueueLaneHeartbeat::class); // one per worker process: it remembers when it last stamped each lane (TASK-0045)
    }

    public function boot(): void
    {
        Event::listen(Looping::class, [QueueLaneHeartbeat::class, 'handle']); // every worker loop stamps its own lane (TASK-0045)
        // API limits (blueprint §17.4): per token/user, public endpoints per IP, brute-force protection on auth.
        RateLimiter::for('api', function (Request $request) {
            $user = $request->user();
            $token = $user !== null && method_exists($user, 'currentAccessToken') ? $user->currentAccessToken() : null;
            $persistent = $token instanceof PersonalAccessToken ? $token : null;
            $perMinute = $persistent !== null && $persistent->rate_limit_per_minute ? (int) $persistent->rate_limit_per_minute : (int) config('onhost.api.default_rate_limit_per_minute', 120);

            return Limit::perMinute($perMinute)->by($persistent !== null ? 'token:'.$persistent->getKey() : ($user ? 'user:'.$user->getAuthIdentifier() : 'ip:'.$request->ip()));
        });
        RateLimiter::for('public', fn (Request $request) => Limit::perMinute((int) config('onhost.api.public_rate_limit_per_minute', 600))->by('ip:'.$request->ip()));
        // per address and per e-mail. A request without an e-mail (guest checkout carries it as customer.email, the public forms
        // elsewhere or not at all) used to share ONE bucket keyed `email:` — five lead forms a minute from anywhere shut guest
        // checkout down for everybody. No e-mail, no e-mail bucket.
        RateLimiter::for('auth', function (Request $request) {
            $email = strtolower(trim((string) ($request->input('email') ?? $request->input('customer.email') ?? '')));

            return array_values(array_filter([Limit::perMinute(10)->by('ip:'.$request->ip()), $email === '' ? null : Limit::perMinute(5)->by('email:'.$email)]));
        });
        RateLimiter::for('payment-callbacks', fn (Request $request) => Limit::perMinute(120)->by('pay:'.$request->ip()));
        RateLimiter::for('probes', fn (Request $request) => Limit::perMinute(600)->by('probe:'.substr((string) $request->bearerToken(), 0, 16).':'.$request->ip()));
        RateLimiter::for('domain-check', fn (Request $request) => Limit::perMinute(30)->by(($request->user() ? 'user:'.$request->user()->getAuthIdentifier() : 'ip:'.$request->ip())));
        // TASK-0067: the relay asks whether a console may stay open every ONHOST_CONSOLE_ALIVE_SECONDS (15, never below 5 = 12 a
        // minute); 30 a minute per console is room for that and nothing for a loop. A 429 counts as one failed check in the relay
        RateLimiter::for('console-alive', fn (Request $request) => Limit::perMinute(30)->by('console-alive:'.(string) $request->route('token')));
    }
}
