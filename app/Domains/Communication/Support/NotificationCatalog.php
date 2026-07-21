<?php

declare(strict_types=1);

namespace App\Domains\Communication\Support;

/**
 * Typed access to config/notifications.php (audit I132).
 *
 * Everything that needs to know which notification types exist — the
 * preferences screen, the opt-out map, the notifications themselves — goes
 * through here, so the list cannot drift into two versions again.
 */
final class NotificationCatalog
{
    /** @return array<string, array{label: string, description: string, group: string, mandatory: bool, opt_in?: list<string>}> */
    public static function types(): array
    {
        /** @var array<string, array{label: string, description: string, group: string, mandatory: bool, opt_in?: list<string>}> $types */
        $types = config('notifications.types', []);

        return $types;
    }

    /** @return list<string> */
    public static function keys(): array
    {
        return array_keys(self::types());
    }

    /** @return list<string> */
    public static function channels(): array
    {
        /** @var list<string> $channels */
        $channels = config('notifications.channels', ['mail', 'database']);

        return $channels;
    }

    /**
     * Types the user is allowed to switch off.
     *
     * @return list<string>
     */
    public static function optionalKeys(): array
    {
        return array_keys(array_filter(
            self::types(),
            static fn (array $type): bool => $type['mandatory'] === false,
        ));
    }

    public static function isMandatory(string $key): bool
    {
        return self::types()[$key]['mandatory'] ?? false;
    }

    /**
     * Channels that stay off until the user explicitly asks for them, rather
     * than being on until they opt out.
     *
     * @return list<string>
     */
    public static function optInChannels(string $key): array
    {
        /** @var list<string> $channels */
        $channels = self::types()[$key]['opt_in'] ?? [];

        return $channels;
    }

    public static function isOptIn(string $key, string $channel): bool
    {
        return in_array($channel, self::optInChannels($key), true);
    }

    public static function exists(string $key): bool
    {
        return array_key_exists($key, self::types());
    }

    /**
     * Types arranged by their display group, preserving catalogue order.
     *
     * @return array<string, array<string, array{label: string, description: string, group: string, mandatory: bool, opt_in?: list<string>}>>
     */
    public static function grouped(): array
    {
        $grouped = [];

        foreach (self::types() as $key => $type) {
            $grouped[$type['group']][$key] = $type;
        }

        return $grouped;
    }
}
