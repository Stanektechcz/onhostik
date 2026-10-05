<?php

declare(strict_types=1);

namespace Onhost\Domain\Platform;

use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Support\Carbon;
use Onhost\Domain\Catalog\CatalogRevisions;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Tax\Models\ExchangeRate;

/**
 * Doctor rows an operator meets on the way to go-live (E11, go-live checklist §0): each says what is wrong in `detail` and
 * what fixes it in `remedy` (a command or a setting; empty when the row is OK). Read-only: config, the database and the
 * running PHP — no outbound call, no secret ever printed (counts and presence only).
 *
 * `blocking` follows Doctor::add: FAIL in production and WARN elsewhere. Only a risk the platform cannot tolerate is
 * blocking; everything else is a standing WARN with the remedy next to it.
 */
final class GoLiveChecks
{
    public const REVISION = '2026-10-deliverable-web-plans';

    public const MIN_PHP = '8.3';

    /** Cache drivers whose keys live and die with one process or one host: no shared limiter state between workers/nodes. */
    private const LOCAL_CACHE_DRIVERS = ['array', 'file', 'null'];

    public function __construct(
        private readonly ?string $phpBinary = null,
        private readonly ?string $phpVersion = null,
        private readonly ?bool $production = null,
    ) {}

    /** @return list<array{area:string, check:string, ok:bool, detail:string, remedy:string, blocking:bool}> */
    public function rows(): array
    {
        return [
            $this->totp(), $this->phpBinary(), $this->revision(), $this->fx(), $this->trustedProxies(),
            $this->apiBaseUrl(), $this->cacheStore(), $this->tokenOrganization(),
        ];
    }

    private function isProduction(): bool
    {
        return $this->production ?? app()->environment('production');
    }

    /** @return array{area:string, check:string, ok:bool, detail:string, remedy:string, blocking:bool} */
    private function row(string $area, string $check, bool $ok, string $detail, string $remedy, bool $blocking = false): array
    {
        return ['area' => $area, 'check' => $check, 'ok' => $ok, 'detail' => $detail, 'remedy' => $ok ? '' : $remedy, 'blocking' => $blocking];
    }

    /** Staff (the demo staff accounts included) who cannot sign in under staff MFA because no authenticator is confirmed. Counts only. */
    private function totp(): array
    {
        $staff = User::query()->where('is_staff', true)->get(['id', 'email', 'totp_secret', 'totp_confirmed_at']);
        $without = $staff->filter(fn (User $u) => ! $u->hasTotp());
        $demo = $without->filter(fn (User $u) => str_ends_with(strtolower((string) $u->email), '@demo.onhost.cz'))->count();
        $required = (bool) config('onhost.identity.staff_mfa_required', true);
        $detail = $without->isEmpty() ? $staff->count().' staff account(s), all with a confirmed authenticator'
            : $without->count().' of '.$staff->count().' staff account(s) without a confirmed authenticator'.($demo > 0 ? " ({$demo} demo)" : '')
                .($required ? ' — they cannot sign in' : ' — staff MFA is not required');

        return $this->row('identity', 'staff and demo accounts have an authenticator', $without->isEmpty(), $detail,
            'on the server, as the owner: php artisan onhost:staff:totp <email> (type the secret into the authenticator), then onhost:staff:totp <email> --code=<6 digits>; the owner types the code, nobody else sees it');
    }

    /** The binary that runs this process, named so the deploy and the queue units can be given the same one (PHP=…). */
    private function phpBinary(): array
    {
        $version = $this->phpVersion ?? PHP_VERSION;
        $binary = $this->phpBinary ?? PHP_BINARY;
        $ok = version_compare($version, self::MIN_PHP, '>=');
        $named = $binary === '' ? 'binary unknown (FPM)' : $binary;

        return $this->row('platform', 'PHP binary is the one the deploy and the workers must use', $ok, "PHP {$version} · {$named}",
            'PHP '.self::MIN_PHP.'+ is required: run the deploy with PHP=<path of a PHP '.self::MIN_PHP.'+ CLI> (staging-launch.md S7: PHP=/www/server/php/83/bin/php onhost-deploy) and use the same path in the queue/scheduler units', true);
    }

    /** The catalogue revision that withdrew what the web plans cannot deliver (R4, TASK-0068). */
    private function revision(): array
    {
        $pending = app(CatalogRevisions::class)->pending(self::REVISION);

        return $this->row('catalog', 'catalogue revision '.self::REVISION.' applied', $pending === [],
            $pending === [] ? 'applied (or nothing to withdraw)' : CatalogRevisions::summary($pending),
            'php artisan onhost:catalog:revise '.self::REVISION.' (dry run, read it), then php artisan onhost:catalog:revise '.self::REVISION.' --apply');
    }

    /** The newest list of the national bank: EUR documents wait for a list younger than `max_age_days`. */
    private function fx(): array
    {
        $newest = ExchangeRate::query()->max('valid_on');
        $maxAge = (int) config('onhost.billing.fx.max_age_days', 7);
        if ($newest === null) {
            return $this->row('billing', 'exchange rates are fresh', false, 'no list stored: documents in a foreign currency wait for the bank', 'php artisan onhost:fx:sync (the scheduler runs it on weekdays at 14:40 and daily at 06:10: check the scheduler unit too)');
        }
        $valid = Carbon::parse((string) $newest)->startOfDay();
        $age = (int) $valid->diffInDays(now()->startOfDay(), false);
        $ok = $age <= $maxAge;

        return $this->row('billing', 'exchange rates are fresh', $ok, 'list of '.$valid->toDateString()." ({$age} day(s) old, limit {$maxAge})",
            'php artisan onhost:fx:sync; if it fails the bank is unreachable from this host or the scheduler is not running (systemctl status onhost-scheduler)');
    }

    /** Behind a proxy every client is the proxy unless its exact address is trusted; `*` trusts anybody who sends the header. */
    private function trustedProxies(): array
    {
        $configured = $this->configuredProxies();
        $list = is_array($configured) ? $configured : array_values(array_filter(array_map('trim', explode(',', (string) $configured)), fn (string $p) => $p !== ''));
        if (in_array('*', $list, true) || in_array('**', $list, true)) {
            return $this->row('security', 'trusted proxies are exact addresses', false, 'TRUSTED_PROXIES trusts every sender (*): any client can claim any IP and dodge rate limits and the audit trail',
                'set TRUSTED_PROXIES to the exact address(es) of the reverse proxy (comma separated) as a real process environment variable (systemd unit / FPM pool env, not .env), then php artisan config:cache', true);
        }
        if ($list === []) {
            return $this->row('security', 'trusted proxies are exact addresses', ! $this->isProduction(), 'TRUSTED_PROXIES is empty'.($this->isProduction() ? ': behind a proxy every client appears as the proxy' : ' (not production: nothing to judge)'),
                'set TRUSTED_PROXIES to the exact address(es) of the reverse proxy (comma separated) as a real process environment variable (systemd unit / FPM pool env, not .env: bootstrap reads env() and config:cache skips .env), then php artisan config:cache; no proxy in front: leave it empty and accept this row');
        }

        return $this->row('security', 'trusted proxies are exact addresses', true, count($list).' address(es): '.implode(', ', array_slice($list, 0, 5)), '');
    }

    /** What the HTTP kernel was actually told, falling back to the environment. @return array<int,string>|string|null */
    private function configuredProxies(): array|string|null
    {
        try {
            $value = (new \ReflectionProperty(TrustProxies::class, 'alwaysTrustProxies'))->getValue();
        } catch (\Throwable) {
            $value = null;
        }

        return $value ?: (env('TRUSTED_PROXIES') ?: null);
    }

    /** Information: where the API documentation says the API answers. */
    private function apiBaseUrl(): array
    {
        $url = (string) config('onhost.api.base_url', '');

        return $this->row('platform', 'API base URL for the documentation', true,
            $url !== '' ? $url : 'ONHOST_API_BASE_URL not set: the documentation pages use the portal URL + /v1', '');
    }

    /** RateLimiter state (webhooks, API, login) must be shared by every worker and node. */
    private function cacheStore(): array
    {
        $store = (string) config('cache.default');
        $driver = (string) config("cache.stores.{$store}.driver", $store);
        $local = in_array($driver, self::LOCAL_CACHE_DRIVERS, true);
        $ok = ! $local || ! $this->isProduction();

        return $this->row('platform', 'cache store is shared (rate limits)', $ok,
            "store {$store} (driver {$driver})".($local ? ($this->isProduction() ? ': every worker counts requests alone, so webhook and API limits do not hold' : ': fine outside production') : ': shared'),
            'CACHE_STORE=redis (the same Redis as the queue, with its own CACHE_PREFIX), then php artisan config:cache and restart the queue workers');
    }

    /** R9: tokens without an organisation are refused only once the operator has looked at who still holds one. */
    private function tokenOrganization(): array
    {
        $on = (bool) config('onhost.token_organization_required', false);

        return $this->row('security', 'API tokens must name an organisation (R9)', $on,
            $on ? 'ONHOST_TOKEN_ORGANIZATION_REQUIRED=true' : 'off: a token without an organisation still works across organisations',
            'php artisan operator:tokens:unbound --dry-run (tell the holders), then ONHOST_TOKEN_ORGANIZATION_REQUIRED=true and php artisan config:cache');
    }
}
