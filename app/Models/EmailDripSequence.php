<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property bool $is_active
 * @property string $trigger_event
 */
class EmailDripSequence extends Model
{
    protected $fillable = [
        'name',
        'trigger_event',
        'is_active',
        'description',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /** @return HasMany<EmailDripStep, $this> */
    public function steps(): HasMany
    {
        return $this->hasMany(EmailDripStep::class, 'drip_sequence_id')->orderBy('sort_order');
    }

    /** @return HasMany<EmailDripEnrollment, $this> */
    public function enrollments(): HasMany
    {
        return $this->hasMany(EmailDripEnrollment::class, 'drip_sequence_id');
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function triggerLabel(): string
    {
        return match ($this->trigger_event) {
            'signup'           => 'Registrace zákazníka',
            'service_created'  => 'Vytvoření služby',
            default            => 'Manuální',
        };
    }
}
