<?php

declare(strict_types=1);

namespace Onhost\Domain\Provisioning\Scheduling;

use Onhost\Domain\Provisioning\Models\ProviderInstance;

/**
 * What a node's room is judged by, per dimension (owner decision 19, TASK-0023): disk by what was SOLD on it, RAM and
 * CPU by what is MEASURED. There used to be one word per panel (`options.capacity_basis: sold`) that moved RAM and disk
 * together; it keeps working exactly as before (both sold) and the doctor names the panels that still carry it.
 *
 * The platform default comes from `onhost.provisioning.capacity_basis` — the disk switch stays `measured` until an
 * operator has read `onhost:capacity:basis` (a node that is already oversold stops taking orders the moment it flips).
 * A panel may override single dimensions with `options.capacity_basis: {disk: sold}`. CPU is always measured: nothing
 * sells a share of a web or game node's CPU.
 */
final class CapacityBasis
{
    public const SOLD = 'sold';

    public const MEASURED = 'measured';

    public const DIMENSIONS = ['disk', 'ram', 'cpu'];

    public const DEFAULT_DISK_SELL_RATIO = 0.85;

    /** @return array{disk:string, ram:string, cpu:string} */
    public static function defaults(): array
    {
        $configured = (array) config('onhost.provisioning.capacity_basis', []);
        $out = [];
        foreach (self::DIMENSIONS as $dimension) {
            $out[$dimension] = self::word($configured[$dimension] ?? null) ?? self::MEASURED;
        }
        $out['cpu'] = self::MEASURED;

        return $out;
    }

    /** @return array{disk:string, ram:string, cpu:string} */
    public static function for(?ProviderInstance $instance): array
    {
        $basis = self::defaults();
        $option = $instance?->option('capacity_basis');
        if (is_string($option) && self::word($option) !== null) {
            $basis['ram'] = $basis['disk'] = (string) self::word($option); // the old one-word switch moved both
        } elseif (is_array($option)) {
            foreach (['disk', 'ram'] as $dimension) {
                $basis[$dimension] = self::word($option[$dimension] ?? null) ?? $basis[$dimension];
            }
        }
        $basis['cpu'] = self::MEASURED;

        return $basis;
    }

    /** A panel that still carries the old one-word option (both RAM and disk). */
    public static function isLegacy(ProviderInstance $instance): bool
    {
        return is_string($instance->option('capacity_basis'));
    }

    /** How much of a node's disk may be sold: the panel's `disk_sell_ratio`, else the platform's. */
    public static function diskSellRatio(?ProviderInstance $instance): float
    {
        $ratio = $instance?->option('disk_sell_ratio') ?? config('onhost.provisioning.disk_sell_ratio', self::DEFAULT_DISK_SELL_RATIO);

        return is_numeric($ratio) && (float) $ratio > 0 ? (float) $ratio : self::DEFAULT_DISK_SELL_RATIO;
    }

    /**
     * The platform default and every platform panel's own basis, for the staff capacity view (read only).
     *
     * @return array{defaults:array{disk:string, ram:string, cpu:string}, disk_sell_ratio:float, instances:array<string, array{provider:string, disk:string, ram:string, cpu:string, legacy:bool, disk_sell_ratio:float}>}
     */
    public static function overview(): array
    {
        $instances = [];
        foreach (ProviderInstance::query()->platform()->whereIn('provider', ['ispconfig', 'aapanel', 'proxmox', 'pterodactyl'])->orderBy('key')->get() as $instance) {
            $instances[(string) $instance->key] = ['provider' => (string) $instance->provider] + self::for($instance) + ['legacy' => self::isLegacy($instance), 'disk_sell_ratio' => self::diskSellRatio($instance)];
        }

        return ['defaults' => self::defaults(), 'disk_sell_ratio' => self::diskSellRatio(null), 'instances' => $instances];
    }

    private static function word(mixed $value): ?string
    {
        $value = is_string($value) ? strtolower(trim($value)) : null;

        return in_array($value, [self::SOLD, self::MEASURED], true) ? $value : null;
    }
}
