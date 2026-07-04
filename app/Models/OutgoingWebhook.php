<?php

declare(strict_types=1);

namespace App\Models;

use App\Domains\Customer\Models\Customer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int|null $customer_id
 * @property list<string> $events
 * @property bool $is_active
 */
class OutgoingWebhook extends Model
{
    protected $fillable = [
        'customer_id',
        'name',
        'url',
        'secret',
        'events',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'events'    => 'array',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return HasMany<WebhookDelivery, $this> */
    public function deliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class);
    }

    /** Returns true if this webhook subscribes to the given event. */
    public function subscribesTo(string $event): bool
    {
        $events = $this->events;

        return in_array('*', $events, true) || in_array($event, $events, true);
    }
}
