<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * @use HasFactory<\Database\Factories\SubscriberFactory>
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $confirmed_at
 * @property \Illuminate\Support\Carbon|null $unsubscribed_at
 */
class Subscriber extends Model
{
    /** @use HasFactory<\Database\Factories\SubscriberFactory> */
    use HasFactory;
    protected $fillable = [
        'email', 'name', 'locale', 'is_active', 'source',
        'confirmed_at', 'unsubscribed_at', 'unsubscribe_token',
    ];

    protected $casts = [
        'is_active'        => 'boolean',
        'confirmed_at'     => 'datetime',
        'unsubscribed_at'  => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (Subscriber $subscriber): void {
            if ($subscriber->unsubscribe_token === null) {
                $subscriber->unsubscribe_token = Str::random(64);
            }
        });
    }

    /**
     * @param  Builder<Subscriber>  $query
     * @return Builder<Subscriber>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->whereNull('unsubscribed_at');
    }

    /**
     * @param  Builder<Subscriber>  $query
     * @return Builder<Subscriber>
     */
    public function scopeConfirmed(Builder $query): Builder
    {
        return $query->whereNotNull('confirmed_at');
    }
}
