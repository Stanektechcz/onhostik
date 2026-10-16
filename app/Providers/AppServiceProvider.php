<?php

declare(strict_types=1);

namespace App\Providers;

use App\Http\Support\SurfaceRenderer;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Events\ScheduledTaskSkipped;
use Illuminate\Console\Events\ScheduledTaskStarting;
use Illuminate\Foundation\Events\LocaleUpdated;
use Illuminate\Http\Request;
use Illuminate\Queue\Events\Looping;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Onhost\Domain\Catalog\CatalogService;
use Onhost\Domain\Catalog\Models\Plan;
use Onhost\Domain\Catalog\Models\PlanVersion;
use Onhost\Domain\Catalog\Models\Price;
use Onhost\Domain\Catalog\Models\Product;
use Onhost\Domain\Catalog\Models\ProductOption;
use Onhost\Domain\Identity\Models\PersonalAccessToken;
use Onhost\Domain\Platform\QueueLaneHeartbeat;
use Onhost\Domain\Platform\ScheduleHealth;
use Onhost\Domain\Provisioning\GameInstanceMemo;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Platform\Dns\Dnsbl\DnsblLookup;
use Onhost\Platform\Dns\Dnsbl\ResolverDnsblLookup;
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
        $this->app->bind(DnsblLookup::class, ResolverDnsblLookup::class); // SP1a: blocklist questions through the operator's own resolver
        $this->app->bind(CertificateReader::class, PeerCertificateReader::class); // what a node really serves for a site (CertificateWatch)
        $this->app->singleton(SurfaceRenderer::class, fn () => new SurfaceRenderer((string) config('onhost.ui.surfaces_path', base_path('apps/surfaces'))));
        // W7-0b (MERENI P2 + P6): request memos; scoped instances are dropped between requests and jobs
        $this->app->scoped(CatalogService::class);
        $this->app->scoped(GameInstanceMemo::class);
        $this->app->singleton(QueueLaneHeartbeat::class); // one per worker process: it remembers when it last stamped each lane (TASK-0045)
    }

    public function boot(): void
    {
        Event::listen(Looping::class, [QueueLaneHeartbeat::class, 'handle']); // every worker loop stamps its own lane (TASK-0045)
        // W7-0b: any write to the catalog or the game panels drops the request memo, whoever writes (command, seeder, test)
        foreach ([Product::class, Plan::class, PlanVersion::class, Price::class, ProductOption::class] as $model) {
            Event::listen(["eloquent.saved: {$model}", "eloquent.deleted: {$model}"], function (): void {
                if ($this->app->resolved(CatalogService::class)) {
                    $this->app->make(CatalogService::class)->forgetMemo();
                }
            });
        }
        Event::listen(['eloquent.saved: '.ProviderInstance::class, 'eloquent.deleted: '.ProviderInstance::class], function (): void {
            if ($this->app->resolved(GameInstanceMemo::class)) {
                $this->app->make(GameInstanceMemo::class)->forget();
            }
        });
        // C2 (panel 2026-10-15): a stored 'sk' preference reads Czech strings before English; every other locale keeps config('app.fallback_locale')
        Event::listen(LocaleUpdated::class, function (LocaleUpdated $event): void {
            $this->app['translator']->setFallback($event->locale === 'sk' ? 'cs' : (string) config('app.fallback_locale', 'en'));
        });
        // P14: every scheduled job records its last start, success, failure and skip (ScheduleHealth, doctor row and /metrics)
        Event::listen(ScheduledTaskStarting::class, [ScheduleHealth::class, 'starting']);
        Event::listen(ScheduledTaskFinished::class, [ScheduleHealth::class, 'finished']);
        Event::listen(ScheduledTaskFailed::class, [ScheduleHealth::class, 'failed']);
        Event::listen(ScheduledTaskSkipped::class, [ScheduleHealth::class, 'skipped']);
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
        // E2: the waitlist mails a stranger's address — 10 an hour per address, 3 an hour per e-mail (no e-mail, no e-mail bucket)
        RateLimiter::for('waitlist', function (Request $request) {
            $email = strtolower(trim((string) $request->input('email', '')));

            return array_values(array_filter([Limit::perHour(10)->by('waitlist-ip:'.$request->ip()), $email === '' ? null : Limit::perHour(3)->by('waitlist-email:'.hash('sha256', $email))]));
        });
        RateLimiter::for('payment-callbacks', fn (Request $request) => Limit::perMinute(120)->by('pay:'.$request->ip()));
        // keyed by ADDRESS only: the callers are unauthenticated, so a key that includes the presented bearer gave every rotated value a fresh bucket (D7)
        RateLimiter::for('probes', fn (Request $request) => Limit::perMinute((int) config('onhost.api.probes_rate_limit_per_minute', 600))->by('probe:'.$request->ip()));
        RateLimiter::for('callbacks', fn (Request $request) => Limit::perMinute((int) config('onhost.api.callbacks_rate_limit_per_minute', 120))->by('callback:'.$request->ip())); // signed third-party callbacks (Discord interactions)
        // TASK-0110 review H1: an image upload is gigabytes of disk and a long virus scan — its own, small budget per person
        RateLimiter::for('custom-iso-upload', fn (Request $request) => Limit::perHour(max(1, (int) config('onhost.custom_iso.uploads_per_hour', 10)))->by('iso:'.($request->user() ? 'user:'.$request->user()->getAuthIdentifier() : 'ip:'.$request->ip())));
        RateLimiter::for('domain-check', fn (Request $request) => Limit::perMinute(30)->by(($request->user() ? 'user:'.$request->user()->getAuthIdentifier() : 'ip:'.$request->ip())));
        // TASK-0067: the relay asks whether a console may stay open every ONHOST_CONSOLE_ALIVE_SECONDS (15, never below 5 = 12 a
        // minute); 30 a minute per console is room for that and nothing for a loop. A 429 counts as one failed check in the relay
        RateLimiter::for('console-alive', fn (Request $request) => Limit::perMinute(30)->by('console-alive:'.(string) $request->route('token')));
    }
}
