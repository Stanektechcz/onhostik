<?php

declare(strict_types=1);

namespace App\Domains\Ai\Models;

use App\Domains\Customer\Models\Customer;
use App\Domains\Shared\Traits\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiRun extends Model
{
    use HasUuid;

    protected $fillable = [
        'customer_id',
        'user_id',
        'feature',
        'provider',
        'status',
        'input',
        'tool_calls',
        'tokens_in',
        'tokens_out',
        'duration_ms',
    ];

    protected function casts(): array
    {
        return [
            'input'      => 'array',
            'tool_calls' => 'array',
        ];
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<AiMessage, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(AiMessage::class);
    }

    /** @return HasMany<AiActionApproval, $this> */
    public function approvals(): HasMany
    {
        return $this->hasMany(AiActionApproval::class);
    }
}
