<?php

declare(strict_types=1);

namespace Onhost\Platform\Settings;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Key/value settings staff edit at runtime (pricing rules, discounts, add-on mappings). Values are JSON, cached for
 * a few minutes and every write also drops the generated public data script so the web reflects the change.
 */
final class SettingsStore
{
    private const CACHE = 'onhost:settings';

    /** @return array<string,mixed> */
    public function all(): array
    {
        return Cache::remember(self::CACHE, 300, function (): array {
            $out = [];
            foreach (DB::table('system_settings')->get() as $row) {
                $out[(string) $row->key] = json_decode((string) $row->value, true);
            }

            return $out;
        });
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $all = $this->all();

        return array_key_exists($key, $all) ? $all[$key] : $default;
    }

    public function set(string $key, mixed $value, ?string $updatedBy = null): void
    {
        DB::table('system_settings')->upsert(
            [['key' => $key, 'value' => json_encode($value, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), 'updated_by' => $updatedBy, 'created_at' => now(), 'updated_at' => now()]],
            ['key'],
            ['value', 'updated_by', 'updated_at'],
        );
        $this->flush();
    }

    public function forget(string $key): void
    {
        DB::table('system_settings')->where('key', $key)->delete();
        $this->flush();
    }

    public function flush(): void
    {
        Cache::forget(self::CACHE);
        Cache::forget('surfaces:onhost-data.js');
    }
}
