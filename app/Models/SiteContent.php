<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class SiteContent extends Model
{
    protected $fillable = ['key', 'label', 'type', 'group', 'value', 'sort_order'];

    /** Return value by key with optional fallback. */
    public static function get(string $key, string $default = ''): string
    {
        return Cache::rememberForever("site_content:{$key}", function () use ($key, $default) {
            return static::where('key', $key)->value('value') ?? $default;
        });
    }

    /** Flush cache for a key after save. */
    protected static function booted(): void
    {
        static::saved(fn (self $m) => Cache::forget("site_content:{$m->key}"));
        static::deleted(fn (self $m) => Cache::forget("site_content:{$m->key}"));
    }
}
