<?php

declare(strict_types=1);

namespace Onhost\Domain\Platform;

use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Onhost\Domain\Catalog\CatalogRevisions;
use Onhost\Domain\Catalog\Models\Plan;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Loyalty\LoyaltyExpiry;
use Onhost\Domain\Loyalty\LoyaltyService;
use Onhost\Domain\Loyalty\Models\LoyaltyPoint;
use Onhost\Domain\Notifications\Models\WebhookEndpoint;
use Onhost\Domain\Services\CustomIso\CustomIsoLibrary;
use Onhost\Domain\Services\CustomIso\CustomIsoPolicy;
use Onhost\Domain\Services\CustomIso\IsoScanner;
use Onhost\Domain\Services\Models\CustomIso;
use Onhost\Domain\Tax\Models\ExchangeRate;
use Onhost\Domain\Tax\VatPayerMode;
use Onhost\Platform\Errors\DomainError;

/**
 * Doctor rows an operator meets on the way to go-live (E11, go-live checklist §0): each says what is wrong in `detail` and
 * what fixes it in `remedy` (a command or a setting; empty when the row is OK). Read-only: config, the database and the
 * running PHP — no outbound call except the custom ISO scanner's own self-test (a local clamd, cached for minutes, 15 s timeout, only asked once custom ISO is on sale), no secret ever printed (counts and presence only).
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
        private readonly ?bool $customIsoSold = null,
        private readonly ?int $customIsoFreeBytes = null,
    ) {}

    /** @return list<array{area:string, check:string, ok:bool, detail:string, remedy:string, blocking:bool}> */
    public function rows(): array
    {
        return [
            $this->totp(), $this->phpBinary(), $this->revision(), $this->fx(), $this->trustedProxies(),
            $this->apiBaseUrl(), $this->cacheStore(), $this->tokenOrganization(),
            app(OutboxDeadLetters::class)->row(), // G7 (TASK-0115): events the relay gave up on
            // G10 (TASK-0119): what phase G added
            $this->vatMode(), $this->customIsoScanner(), $this->customIsoStorage(), $this->loyalty(), $this->webhookSecretOverlap(),
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
        // H0, owner decision H-R2 (2026-10-06): no proxy and no CDN in front of the origin. The only proxy is aaPanel's own nginx on
        // the same host, so the default trusts the loopback addresses alone (bootstrap/app.php) — and nothing at all is fine too
        // while nginx hands requests to PHP-FPM over FastCGI (the client's address is REMOTE_ADDR itself)
        if ($list === []) {
            return $this->row('security', 'trusted proxies are exact addresses', true, 'TRUSTED_PROXIES is set empty: nothing is trusted to name the client — right while nginx talks FastCGI to PHP-FPM; behind a local proxy_pass every client would be 127.0.0.1 (unset it to trust the local nginx, owner decision H-R2)', '');
        }
        $foreign = array_values(array_filter($list, fn (string $p) => ! in_array($p, self::LOOPBACK, true)));
        if ($foreign === []) {
            return $this->row('security', 'trusted proxies are exact addresses', true, 'only the local aaPanel nginx reverse proxy ('.implode(', ', $list).'); no proxy or CDN in front of the origin (owner decision H-R2)', '');
        }

        return $this->row('security', 'trusted proxies are exact addresses', ! $this->isProduction(), count($foreign).' address(es) beyond the local nginx: '.implode(', ', array_slice($foreign, 0, 5)).' — owner decision H-R2 says no proxy or CDN stands in front of the origin, so these may name any client\'s address',
            'unset TRUSTED_PROXIES (the default trusts only 127.0.0.1 and ::1, the local aaPanel nginx) or set it to exactly those, as a real process environment variable (systemd unit / FPM pool env, not .env: bootstrap reads env() and config:cache skips .env), then php artisan config:cache; a CDN or proxy in front is a new owner decision recorded in docs/audit/2026-10-full-readiness/ROZHODNUTI.md first');
    }

    /** H0 (H-R2): the addresses of the local aaPanel nginx — the only proxy the origin has. */
    public const LOOPBACK = ['127.0.0.1', '::1', '127.0.0.0/8', '::1/128'];

    /** What the HTTP kernel was actually told, (set from TRUSTED_PROXIES in bootstrap/app.php). @return array<int,string>|string|null */
    private function configuredProxies(): array|string|null
    {
        try {
            $value = (new \ReflectionProperty(TrustProxies::class, 'alwaysTrustProxies'))->getValue();
        } catch (\Throwable) {
            $value = null;
        }

        return $value ?: null; // bootstrap/app.php hands TRUSTED_PROXIES to TrustProxies::at(), so this is what the kernel really trusts
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

    // ── G10 (TASK-0119): the rows of phase G ──

    /** G2: the VAT mode documents are issued in is a decision, and the legal entity that issues them must carry a real VAT number if it is a payer. */
    private function vatMode(): array
    {
        $report = app(VatPayerMode::class)->report();
        $entity = VatPayerMode::legalEntity();
        $detail = $report['detail'];
        $remedy = $report['remedy'];
        if ($report['consistent'] && $entity !== null && (bool) $entity->vat_payer) {
            $number = strtoupper(trim((string) ($entity->dic ?: $entity->vat_id)));
            if (preg_match('/^[A-Z]{2}\d{8,10}$/', $number) !== 1 || preg_match('/^[A-Z]{2}0+$/', $number) === 1) {
                $detail .= ' · the legal entity has no real VAT number (empty or the seeded placeholder)';
                $remedy = 'a payer issues tax documents under its VAT number: set the real DIČ in the production legal entity values and run php artisan onhost:production:prepare --legal; not a payer yet: decide the mode first (docs/runbooks/vat-payer-mode.md)';
            }
        }

        return $this->row('documents', 'VAT payer mode agrees with the legal entity', $remedy === '', $detail, $remedy, true);
    }

    /** Whether any plan sells a customer's own ISO image, or an image already exists (then the way out must keep working). */
    private function customIsoSold(): bool
    {
        if ($this->customIsoSold !== null) {
            return $this->customIsoSold;
        }
        if (CustomIso::query()->where('state', '!=', CustomIso::DELETED)->exists()) {
            return true;
        }
        foreach (Plan::query()->get() as $plan) {
            $entitlements = (array) ($plan->currentVersion()->entitlements ?? []);
            if (filter_var($entitlements[CustomIsoPolicy::FEATURE] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                return true;
            }
        }

        return false;
    }

    /** G5: the antivirus finds EICAR and reports a file it could not read in full; only asked once custom ISO is on sale (clamd is cached for minutes). */
    private function customIsoScanner(): array
    {
        $check = 'custom ISO virus scan passes its self-test';
        if (! $this->customIsoSold()) {
            return $this->row('security', $check, true, 'no plan sells custom ISO and no image exists: nothing to scan yet', '');
        }
        try {
            $test = app(IsoScanner::class)->selfTest();
        } catch (\Throwable $e) {
            $test = ['ok' => false, 'detail' => 'the self-test could not run ('.class_basename($e).')'];
        }

        return $this->row('security', $check, (bool) $test['ok'], (string) $test['detail'],
            'every upload is refused (503 iso_scanner_untrusted) until this passes: clamd reachable at ONHOST_CLAMAV_HOST/ONHOST_CLAMAV_PORT, StreamMaxLength/MaxScanSize/MaxFileSize >= ONHOST_CUSTOM_ISO_SCAN_MAX_MB and AlertExceedsMax yes, then php artisan onhost:isos:scanner-check (docs/runbooks/custom-iso.md, step 2)', true);
    }

    /** G5: the disk of the customers' images is outside the web root and has room for at least one organization filling its quota. */
    private function customIsoStorage(): array
    {
        $check = 'custom ISO storage has room';
        if (! $this->customIsoSold()) {
            return $this->row('platform', $check, true, 'no plan sells custom ISO and no image exists: no storage needed yet', '');
        }
        try {
            app(CustomIsoLibrary::class)->disk();
        } catch (DomainError) {
            return $this->row('platform', $check, false, 'ONHOST_CUSTOM_ISO_ROOT is empty or inside the web root: uploads are switched off (503 custom_iso_storage_unsafe)',
                'mount a dedicated volume outside the web root and set ONHOST_CUSTOM_ISO_ROOT to it, owned by the PHP/queue user, mode 0750, then php artisan config:cache (docs/runbooks/custom-iso.md, step 1)', true);
        }
        $free = $this->customIsoFreeBytes ?? $this->freeBytesOf((string) config('filesystems.disks.'.config('onhost.custom_iso.disk', 'custom_isos').'.root', ''));
        $quota = CustomIsoPolicy::quotaBytes();
        $held = (int) CustomIso::query()->where('state', CustomIso::READY)->sum('size_bytes');
        $gib = static fn (int $bytes): string => number_format($bytes / 1073741824, 1, '.', '').' GiB';
        $remedy = 'grow the volume mounted at ONHOST_CUSTOM_ISO_ROOT (docs/runbooks/custom-iso.md, step 1) or lower ONHOST_CUSTOM_ISO_ORG_QUOTA_MB, then php artisan config:cache; php artisan onhost:isos:sweep clears dead uploads';
        if ($free === null) {
            return $this->row('platform', $check, false, 'the free space of ONHOST_CUSTOM_ISO_ROOT cannot be read', $remedy);
        }

        return $this->row('platform', $check, $free >= $quota, $gib($free).' free · one organization may hold '.$gib($quota).' · '.$gib($held).' held by ready images', $remedy);
    }

    /** Free bytes on the volume that holds `$path` (its nearest existing parent when the directory is not created yet); null when unknown. */
    private function freeBytesOf(string $path): ?int
    {
        while ($path !== '' && ! is_dir($path)) {
            $parent = dirname($path);
            if ($parent === $path) {
                return null;
            }
            $path = $parent;
        }
        $free = $path === '' ? false : @disk_free_space($path);

        return $free === false ? null : (int) $free;
    }

    /** G3: the daily expiry is scheduled and has run (nothing past 24 months stays on a balance), and no balance or debt is below zero. */
    private function loyalty(): array
    {
        $check = 'loyalty expiry runs and balances are sane';
        $remedy = 'php artisan onhost:loyalty:expire runs the expiry by hand; it is scheduled daily at 05:35 (php artisan schedule:list, systemctl status onhost-scheduler); a negative balance or debt is a loyalty bug: open an incident (docs/runbooks/incident-response.md), correct it with a staff award or clawback that carries a reason, never edit rows';
        Artisan::all(); // loads routes/console.php, where the schedule is declared
        $scheduled = collect(app(Schedule::class)->events())->contains(fn ($event) => str_contains((string) ($event->command ?? ''), 'onhost:loyalty:expire'));
        $negative = count(LoyaltyPoint::query()->groupBy('organization_id')->havingRaw('sum(points) < 0')->pluck('organization_id')->all());
        $overRepaid = count(LoyaltyPoint::query()->whereIn('rule', [LoyaltyService::CARRY_RULE, LoyaltyService::DEBT_RULE])
            ->groupBy('organization_id')->havingRaw('sum(points) < 0')->pluck('organization_id')->all());
        [$late, $latePoints] = $this->expiryOverdue();
        $debt = (int) LoyaltyPoint::query()->where('rule', LoyaltyService::CARRY_RULE)->sum('points') + (int) LoyaltyPoint::query()->where('rule', LoyaltyService::DEBT_RULE)->sum('points');
        $problems = array_values(array_filter([
            $scheduled ? null : 'onhost:loyalty:expire is not scheduled',
            $late === 0 ? null : "{$late} organization(s) hold {$latePoints} point(s) past their ".LoyaltyExpiry::months().' months',
            $negative === 0 ? null : "{$negative} organization(s) with a negative balance",
            $overRepaid === 0 ? null : "{$overRepaid} organization(s) with a debt repaid beyond what was owed",
        ]));

        return $this->row('billing', $check, $problems === [], $problems === [] ? 'expiry scheduled and up to date · points owed by organizations in total: '.max(0, $debt) : implode(' · ', $problems), $remedy);
    }

    /**
     * Organizations whose points credited on or before yesterday's cutoff are still on the balance: yesterday's run did not happen.
     * Bounded (500 candidates) so the doctor stays fast; the daily run itself never skips one.
     *
     * @return array{0:int, 1:int} organizations, points
     */
    private function expiryOverdue(): array
    {
        $cutoff = CarbonImmutable::now()->subDay()->subMonthsNoOverflow(LoyaltyExpiry::months());
        if (CarbonImmutable::parse((string) config('loyalty.expiry.counted_from', '2026-10-05'), 'UTC')->startOfDay()->greaterThan($cutoff)) {
            return [0, 0];
        }
        $expiry = app(LoyaltyExpiry::class);
        // one SQL aggregate finds the organizations whose old points are not used up (earned up to the cutoff less every debit); the
        // exact rule (reservations too) then confirms the few candidates — a healthy installation has none, so no id order can hide one
        $notEarned = LoyaltyExpiry::NOT_EARNED;
        $in = implode(',', array_fill(0, count($notEarned), '?'));
        $organizations = LoyaltyPoint::query()->groupBy('organization_id')->orderBy('organization_id')
            ->havingRaw("sum(case when points > 0 and rule not in ({$in}) and created_at <= ? then points else 0 end) + sum(case when points < 0 or rule in ({$in}) then points else 0 end) > 0", [...$notEarned, $cutoff->toDateTimeString(), ...$notEarned])
            ->limit(500)->pluck('organization_id');
        $count = 0;
        $points = 0;
        foreach ($organizations as $id) {
            $due = $expiry->dueBy((string) $id, $cutoff);
            if ($due > 0) {
                $count++;
                $points += $due;
            }
        }

        return [$count, $points];
    }

    /** G7: a rotated webhook secret signs alongside the new one for a short time only, and the minute pass clears it afterwards. */
    private function webhookSecretOverlap(): array
    {
        $maxMinutes = 1440; // the ceiling of ONHOST_WEBHOOK_SECRET_OVERLAP_MINUTES
        $signing = WebhookEndpoint::query()->whereNotNull('previous_secret')->where('previous_secret_expires_at', '>', now());
        $active = (clone $signing)->count();
        $tooLong = (clone $signing)->where('previous_secret_expires_at', '>', now()->addMinutes($maxMinutes + 5))->count();
        $stale = WebhookEndpoint::query()->whereNotNull('previous_secret')->where('previous_secret_expires_at', '<=', now()->subMinutes(5))->count();
        $problems = array_values(array_filter([
            $tooLong === 0 ? null : "{$tooLong} endpoint(s) keep an old secret signing for more than {$maxMinutes} minutes",
            $stale === 0 ? null : "{$stale} endpoint(s) still store a secret whose overlap ended (the minute pass that clears it is not running)",
        ]));

        return $this->row('security', 'rotated webhook secrets overlap only briefly', $problems === [],
            $problems === [] ? "{$active} endpoint(s) in a rotation overlap now (limit {$maxMinutes} min, ONHOST_WEBHOOK_SECRET_OVERLAP_MINUTES = ".(int) config('onhost.webhooks.secret_overlap_minutes', 60).')' : implode(' · ', $problems),
            'the overlap is ONHOST_WEBHOOK_SECRET_OVERLAP_MINUTES (at most 1440); rotate the endpoint again without overlap (POST /v1/webhooks/{endpoint}/rotate-secret with overlap=false) to end the old secret at once; check the scheduler and the webhooks queue worker (systemctl status onhost-scheduler onhost-queue@webhooks; docs/runbooks/webhooks.md)');
    }
}
