<?php

declare(strict_types=1);

namespace Onhost\Domain\Platform;

use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Onhost\Domain\Identity\Authorization\ApprovalService;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Provisioning\Models\ProviderInstance;

/**
 * Doctor rows that turn the staging runbook's isolation text into code (TASK-0045, staging pre-mortem 2026-09-27;
 * onboarding audit F2, F5): the panels the owner contained, Redis and cache keys another installation could share,
 * Turnstile enforced without keys (which is off), and who decides approvals. Read-only: config and the database, no
 * outbound call.
 *
 * A row's `blocking` means FAIL in production and WARN elsewhere (Doctor::add); `fail_everywhere` means FAIL wherever it is
 * not OK — a staging sharing production's Redis keys is exactly the failure a staging must not pass.
 */
final class IsolationChecks
{
    /** The prefix row's name: deploy-gate.php and the staging runbook's expected-nonok list refer to it. */
    public const PREFIX_ROW = 'Redis and cache key prefixes are this installation\'s own';

    /** Cache stores that keep their keys where another installation can read them. */
    private const SHARED_CACHE_DRIVERS = ['redis', 'memcached', 'database', 'dynamodb'];

    /** A run parked longer than this is named in the doctor (a standing WARN). */
    public const PARKED_DAYS = 7;

    /** @return list<array{area:string, check:string, ok:bool, detail:string, blocking:bool, fail_everywhere?:bool}> */
    public function rows(): array
    {
        return [$this->contained(), $this->longParked(), $this->prefixes(), $this->turnstile(), $this->deciders()];
    }

    /** @return array{area:string, check:string, ok:bool, detail:string, blocking:bool} */
    private function contained(): array
    {
        $kept = ProviderInstance::query()->where('state', ProviderInstance::CONTAINED)->orderBy('key')->get(['key', 'provider', 'state_reason']);
        $detail = $kept->isEmpty() ? 'none — the registry refuses a contained or disabled instance to every caller'
            : $kept->count().' contained, nothing is sent to them: '.$kept->take(10)->map(fn (ProviderInstance $i) => $i->key.' ('.$i->provider.($i->state_reason ? ': '.mb_substr((string) $i->state_reason, 0, 80) : '').')')->implode(', ')
                .' — lifted only with POST /v1/staff/integrations/{instance}/state';

        // a containment is the owner's decision (staging Path A): always a standing WARN, never a deploy stop by itself
        return ['area' => 'providers', 'check' => 'no provider instance is contained', 'ok' => $kept->isEmpty(), 'detail' => $detail, 'blocking' => false];
    }

    /**
     * Runs parked behind a containment for more than a week (review MEDIUM-F): a parked run is asked less and less often
     * (OperationRunner::PARK_MAX_MINUTES) and never gives up by itself — somebody decides whether it still should run.
     *
     * @return array{area:string, check:string, ok:bool, detail:string, blocking:bool}
     */
    private function longParked(): array
    {
        $limit = now()->subDays(self::PARKED_DAYS);
        $old = Operation::query()->whereIn('state', [Operation::PENDING, Operation::WAITING])->whereNotNull('context->_parked->since')->orderBy('queued_at')->limit(500)->get(['id', 'kind', 'context'])
            ->filter(fn (Operation $o) => CarbonImmutable::parse((string) data_get($o->context, '_parked.since'))->lt($limit))->values();
        $detail = $old->isEmpty() ? 'none' : $old->count().' run(s) waiting for a contained or disabled panel since more than '.self::PARKED_DAYS.' days: '
            .$old->take(5)->map(fn (Operation $o) => $o->id.' ('.$o->kind.')')->implode(', ').' — lift the containment, or cancel them (provisioning.operation.cancel)';

        return ['area' => 'automation', 'check' => 'no operation parked for more than '.self::PARKED_DAYS.' days', 'ok' => $old->isEmpty(), 'detail' => $detail, 'blocking' => false];
    }

    /**
     * Laravel derives both prefixes from APP_NAME when REDIS_PREFIX / CACHE_PREFIX are unset: two installations of the same
     * application on one Redis (or one cache database) then read and overwrite each other's keys — locks, the settings cache,
     * heartbeats, rate limits, queued jobs. Judged only where the keys can be shared: Redis when something uses it, the cache
     * prefix when the cache store is not local to this host.
     *
     * @return array{area:string, check:string, ok:bool, detail:string, blocking:bool, fail_everywhere:bool}
     */
    private function prefixes(): array
    {
        $row = ['area' => 'storage', 'check' => self::PREFIX_ROW, 'blocking' => true, 'fail_everywhere' => true];
        if (app()->environment('local')) {
            return $row + ['ok' => true, 'detail' => 'APP_ENV=local: a developer machine shares its keys with nobody'];
        }
        $redisUsers = $this->redisUsers();
        $cacheStore = (string) config('cache.default');
        $cacheDriver = (string) config("cache.stores.{$cacheStore}.driver", $cacheStore);
        $problems = [];
        $redisPrefix = (string) config('database.redis.options.prefix', '');
        if ($redisUsers !== [] && $this->isDefault($redisPrefix, 'database')) {
            $problems[] = 'REDIS_PREFIX '.($redisPrefix === '' ? 'is empty' : "is Laravel's default '{$redisPrefix}'").' (Redis carries '.implode(', ', $redisUsers).')';
        }
        $cachePrefix = (string) config('cache.prefix', '');
        $cacheShared = in_array($cacheDriver, self::SHARED_CACHE_DRIVERS, true);
        if ($cacheShared && $this->isDefault($cachePrefix, 'cache')) {
            $problems[] = 'CACHE_PREFIX '.($cachePrefix === '' ? 'is empty' : "is Laravel's default '{$cachePrefix}'")." (cache store {$cacheStore})";
        }
        if ($problems !== []) {
            return $row + ['ok' => false, 'detail' => implode(' · ', $problems).' — set REDIS_PREFIX and CACHE_PREFIX to values of this installation alone (staging: onhost_staging_…, never production\'s); staging-launch.md S3'];
        }
        $said = [$redisUsers === [] ? 'Redis unused' : "Redis prefix '{$redisPrefix}'", $cacheShared ? "cache prefix '{$cachePrefix}'" : "cache store {$cacheStore} is local to this host"];

        return $row + ['ok' => true, 'detail' => implode(' · ', $said)];
    }

    /** @return list<string> what this installation keeps in Redis (cache, queue, session) */
    private function redisUsers(): array
    {
        $cacheStore = (string) config('cache.default');
        $queue = (string) config('queue.default');
        $users = [
            'the cache' => (string) config("cache.stores.{$cacheStore}.driver", $cacheStore) === 'redis',
            'the queue' => (string) config("queue.connections.{$queue}.driver", $queue) === 'redis',
            'sessions' => (string) config('session.driver') === 'redis',
        ];

        return array_keys(array_filter($users));
    }

    /** Empty, or what Laravel's config derives from an app name (this one's, or the framework's `laravel`) in either style. */
    private function isDefault(string $prefix, string $kind): bool
    {
        if (trim($prefix) === '') {
            return true;
        }
        $defaults = [];
        foreach (array_unique([(string) config('app.name', 'laravel'), 'laravel']) as $name) {
            $defaults[] = Str::slug($name).'-'.$kind.'-';           // Laravel 11+ config (this repository's config/*.php)
            $defaults[] = Str::slug($name, '_').'_'.$kind.'_';      // the older skeletons
        }

        return in_array(strtolower($prefix), array_map('strtolower', $defaults), true);
    }

    /**
     * Turnstile is on only with both keys (domains/Risk/Turnstile.php): enforcement without keys refuses nothing (audit F5).
     *
     * @return array{area:string, check:string, ok:bool, detail:string, blocking:bool}
     */
    private function turnstile(): array
    {
        $check = ['area' => 'security', 'check' => 'Turnstile protects registration and public forms'];
        $keys = (string) config('onhost.turnstile.site_key', '') !== '' && (string) config('onhost.turnstile.secret', '') !== '';
        $enforced = array_keys(array_filter(['registration' => (bool) config('onhost.turnstile.enforce_register', true), 'public forms' => (bool) config('onhost.turnstile.enforce_forms', true)]));
        if ($keys && $enforced !== []) {
            return $check + ['ok' => true, 'detail' => 'keys set · enforced on '.implode(' and ', $enforced), 'blocking' => true];
        }
        if ($keys) {
            return $check + ['ok' => false, 'detail' => 'keys set, but enforcement is off (ONHOST_TURNSTILE_ENFORCE_REGISTER / _FORMS=false): a failed check only scores an order', 'blocking' => false];
        }
        if ($enforced === []) {
            return $check + ['ok' => false, 'detail' => 'off: no keys and enforcement switched off deliberately', 'blocking' => false];
        }

        return $check + ['ok' => false, 'detail' => 'OFF although enforced on '.implode(' and ', $enforced).': TURNSTILE_SITE_KEY / TURNSTILE_SECRET_KEY are empty, so no check runs and bots register freely', 'blocking' => true];
    }

    /**
     * Who may decide approvals, and whether the solo operator's waiver is in effect (audit F2; TASK-0037: the waiver spares
     * only the sole approver, whose own critical actions wait a time lock).
     *
     * @return array{area:string, check:string, ok:bool, detail:string, blocking:bool}
     */
    private function deciders(): array
    {
        $check = ['area' => 'identity', 'check' => 'who decides approvals'];
        $people = ApprovalService::deciders();
        $names = $people->take(6)->map(fn (User $u) => (string) ($u->name ?: $u->id))->implode(', ').($people->count() > 6 ? ', …' : '');
        $hours = ApprovalService::timeLockHours();
        if (ApprovalService::enabled()) {
            return $people->count() >= 2
                ? $check + ['ok' => true, 'detail' => $people->count().' may decide: '.$names.' · four eyes on', 'blocking' => true]
                : $check + ['ok' => false, 'detail' => ($people->count() === 0 ? 'nobody may decide approvals' : 'only '.$names.' may decide').' while four eyes are on: a critical action of theirs can never be approved — grant iam.approval.decide to a second person, or ONHOST_FOUR_EYES=false deliberately (docs/runbooks/approvals.md)', 'blocking' => true];
        }
        if ($people->count() === 1) {
            return $check + ['ok' => false, 'detail' => "ONHOST_FOUR_EYES=false: {$names} is the sole approver — their own critical actions and price changes wait a {$hours} h time lock (cancellable on the approvals page); anybody else still asks them", 'blocking' => false];
        }
        if ($people->count() === 0) {
            return $check + ['ok' => false, 'detail' => 'ONHOST_FOUR_EYES=false and nobody may decide approvals: every critical action waits for a decider who does not exist — grant iam.approval.decide', 'blocking' => true];
        }

        return $check + ['ok' => false, 'detail' => "ONHOST_FOUR_EYES=false has no effect: {$people->count()} may decide ({$names}), so every critical action still asks a second person — remove the switch", 'blocking' => false];
    }
}
