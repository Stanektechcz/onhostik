<?php

declare(strict_types=1);

namespace Onhost\Domain\Services;

use Onhost\Domain\Catalog\Models\Product;
use Onhost\Domain\Services\Limits\LimitRaises;
use Onhost\Domain\Services\Models\BackupPolicy;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Web\BackupScheduler;
use Onhost\Domain\Services\Web\ServiceSites;
use Onhost\Platform\Errors\DomainError;

/**
 * What a paid add-on does to the service it was bought for (Brain card H21, audit §5ac).
 *
 * An add-on has no resource of its own: it is a billing row that changes its parent. Until this class existed only two
 * of the seven add-ons on sale changed anything — and one of those two wrote a backup policy in a shape the scheduler
 * cannot read. Hourly backups, mailboxes and the CDN were charged every month and delivered nothing; the add-on could
 * not even be cancelled, because a service without a provider binding fails the identity check that guards deletion.
 *
 * The rules here:
 *  • an add-on product on sale that this class does not know cannot be ordered — the order fails loudly instead of
 *    billing for nothing (`assertSellable`, a guard test and a doctor control point hold the other direction too),
 *  • what an add-on changes on the parent is written down with the value it replaced (`tags.addon.patch` / `.before`),
 *    so a cancellation gives back exactly what was taken and never overwrites a value somebody changed meanwhile,
 *  • the backup add-ons write the policy in the shape `BackupScheduler` reads (`schedule.frequency`,
 *    `retention.days`, `retention.generations`), not in the words the price list uses.
 */
final class Addons
{
    /** A backup kept every hour for a month is 720 copies; a plan that asks for more than this is a mistake, not a sale. */
    public const MAX_GENERATIONS = 2000;

    public function __construct(private readonly ServiceFeatures $features) {}

    /** Add-on products the platform delivers. Anything else is not sellable — see the class comment. */
    public static function handled(): array
    {
        return ['ipv4', 'backup-plus', 'backup-hourly', 'mail-hosting', 'cdn', LimitRaises::PRODUCT];
    }

    public static function sellable(string $productKey): bool
    {
        return in_array($productKey, self::handled(), true);
    }

    /** Add-on products on sale that nothing here delivers (doctor + guard test: the list must stay empty). */
    public static function unsellable(): array
    {
        return Product::query()->where('family', 'addon')->where('state', 'active')
            ->pluck('key')->reject(fn (string $key) => self::sellable($key))->values()->all();
    }

    public static function assertSellable(string $productKey): void
    {
        if (! self::sellable($productKey)) {
            throw new DomainError('addon_not_delivered', "Doplněk {$productKey} zatím platforma neumí dodat; objednávka by jej jen účtovala.", 422, ['addon' => $productKey]);
        }
    }

    /**
     * Applies the add-on to its parent and records what it changed. Returns the patch for the audit.
     *
     * @return array{patch:array<string,mixed>, before:array<string,mixed>, backup_policy:bool}
     */
    public function apply(Service $parent, Service $addon): array
    {
        self::assertSellable((string) $addon->product_key);
        $entitlements = (array) $addon->entitlements;
        $patch = $this->patch($parent, (string) $addon->product_key, $entitlements);
        $before = [];
        foreach ($patch as $key => $value) {
            $before[$key] = ((array) $parent->entitlements)[$key] ?? null;
        }
        if ($patch !== []) {
            $parent->forceFill(['entitlements' => array_replace((array) $parent->entitlements, $patch)])->save();
            ServiceSites::spread($parent); // what the add-on adds is the plan's: every site the plan carries gets it too
        }
        $policy = self::backupPolicy((string) $addon->product_key, $entitlements);
        if ($policy !== null) {
            BackupPolicy::query()->updateOrCreate(['service_id' => $parent->id], $policy + ['product_key' => $addon->product_key, 'state' => 'active']);
        }
        $applied = ['patch' => $patch, 'before' => $before, 'backup_policy' => $policy !== null];
        if ($addon->product_key === LimitRaises::PRODUCT) { // a raise adds to whatever the number is; its cancellation takes back exactly this much
            $applied['delta'] = self::raiseDelta($entitlements);
        }
        $addon->forceFill(['tags' => array_replace((array) $addon->tags, ['addon' => $applied])])->save();
        $this->features->forget($parent);

        return ['patch' => $patch, 'before' => $before, 'backup_policy' => $policy !== null];
    }

    /**
     * Gives back what the add-on took. A key whose value somebody changed meanwhile is left alone: the cancellation of
     * an add-on never undoes a later decision.
     *
     * @return array{restored:array<string,mixed>, kept:list<string>, backup_policy:bool}
     */
    public function revoke(Service $parent, Service $addon): array
    {
        $applied = (array) data_get($addon->tags, 'addon', []);
        $patch = (array) ($applied['patch'] ?? []);
        $before = (array) ($applied['before'] ?? []);
        $entitlements = (array) $parent->entitlements;
        $restored = [];
        $kept = [];
        if ($addon->product_key === LimitRaises::PRODUCT) {
            // raises stack: another raise or a plan change may have moved the number since, so the value from before is not
            // the one to go back to — exactly this raise's delta comes off (never below zero), and nothing else
            foreach ((array) ($applied['delta'] ?? []) as $key => $delta) {
                if (is_numeric($entitlements[$key] ?? null)) {
                    $entitlements[$key] = max(0, (int) $entitlements[$key] - (int) $delta);
                    $restored[$key] = $entitlements[$key];
                }
            }
            $patch = [];
        }
        foreach ($patch as $key => $value) {
            if (($entitlements[$key] ?? null) !== $value) {
                $kept[] = $key; // changed since: not ours to take back

                continue;
            }
            $was = $before[$key] ?? null;
            if ($was === null) {
                unset($entitlements[$key]);
            } else {
                $entitlements[$key] = $was;
            }
            $restored[$key] = $was;
        }
        $parent->forceFill(['entitlements' => $entitlements])->save();
        ServiceSites::spread($parent); // and what it took back leaves the carried sites with it
        $policy = false;
        if (($applied['backup_policy'] ?? false) === true) {
            // back to the schedule the parent's own plan sells (BackupScheduler falls back to the feature's options)
            $policy = (bool) BackupPolicy::query()->where('service_id', $parent->id)->where('product_key', $addon->product_key)->delete();
        }
        $addon->forceFill(['tags' => array_replace((array) $addon->tags, ['addon' => array_replace($applied, ['revoked_at' => now()->toIso8601String(), 'restored' => $restored, 'kept' => $kept])])])->save();
        $this->features->forget($parent);

        return ['restored' => $restored, 'kept' => $kept, 'backup_policy' => $policy];
    }

    /**
     * The backup policy an add-on sells, in the shape the scheduler reads — the price list says "hourly snapshots,
     * 30 days of history", the scheduler asks for a frequency, a number of days and a number of generations.
     *
     * @param  array<string,mixed>  $ent
     * @return array{schedule:array<string,mixed>, retention:array<string,int>, offsite:bool, restore_test:array<string,string>}|null
     */
    public static function backupPolicy(string $productKey, array $ent): ?array
    {
        if (! in_array($productKey, ['backup-plus', 'backup-hourly'], true)) {
            return null;
        }
        $hours = (int) ($ent['interval_hours'] ?? 0);
        $frequency = match (true) {
            $hours === 1 => 'hourly',
            $hours > 1 && $hours <= 6 => '6h',
            default => 'daily',
        };
        $copies = (int) ($ent['daily'] ?? 0) + (int) ($ent['weekly'] ?? 0) + (int) ($ent['monthly'] ?? 0);
        $days = (int) ($ent['retention_days'] ?? 0) ?: ((int) ($ent['daily'] ?? 0) ?: 7);
        $perDay = max(1, intdiv(1440, BackupScheduler::FREQUENCIES[$frequency]));
        $generations = $copies > 0 ? $copies : $days * $perDay;

        return [
            'schedule' => ['frequency' => $frequency],
            'retention' => ['days' => max(1, $days), 'generations' => max(1, min(self::MAX_GENERATIONS, $generations))],
            'offsite' => (bool) ($ent['offsite'] ?? false),
            'restore_test' => ['cadence' => (string) ($ent['restore_test'] ?? 'monthly')],
        ];
    }

    /**
     * What the add-on adds to the parent's entitlements.
     *
     * @param  array<string,mixed>  $ent
     * @return array<string,mixed>
     */
    private function patch(Service $parent, string $productKey, array $ent): array
    {
        $current = (array) $parent->entitlements;

        return match ($productKey) {
            'ipv4' => ['ipv4' => (int) ($ent['addresses'] ?? 1)],
            // mailboxes on top of what the plan already sells; the mail feature turns on the moment there is one
            'mail-hosting' => array_filter([
                'mailboxes' => (int) ($current['mailboxes'] ?? 0) + (int) ($ent['mailboxes'] ?? 0),
                'quota_mb' => (int) ($ent['quota_mb'] ?? 0) ?: null,
                'dkim' => ($ent['dkim'] ?? null) === true ? true : null,
            ], fn ($v) => $v !== null && $v !== 0),
            'cdn' => array_filter([
                'cdn' => true,
                'waf' => (string) ($ent['waf'] ?? '') !== '' ? (string) $ent['waf'] : null,
                'traffic_tb' => (int) ($ent['traffic_tb'] ?? 0) ?: null,
            ], fn ($v) => $v !== null),
            // one number of the parent, plus what was paid for (LimitRaiseLine computed the delta on the server)
            LimitRaises::PRODUCT => self::raised($current, self::raiseDelta($ent)),
            default => [], // the backup add-ons deliver through the policy, not through the parent's entitlements
        };
    }

    /**
     * The one number a raise adds to, and by how much (`{limit_raise: {metric, delta}}`, written by `LimitRaiseLine`).
     *
     * @param  array<string,mixed>  $ent
     * @return array<string,int>
     */
    private static function raiseDelta(array $ent): array
    {
        $metric = (string) data_get($ent, 'limit_raise.metric', '');
        $delta = (int) data_get($ent, 'limit_raise.delta', 0);

        return $metric === '' || $delta <= 0 ? [] : [$metric => $delta];
    }

    /**
     * @param  array<string,mixed>  $current
     * @param  array<string,int>  $delta
     * @return array<string,int>
     */
    private static function raised(array $current, array $delta): array
    {
        $out = [];
        foreach ($delta as $key => $by) {
            $out[$key] = (int) ($current[$key] ?? 0) + $by;
        }

        return $out;
    }
}
