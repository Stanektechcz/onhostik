<?php

declare(strict_types=1);

namespace App\Domains\Compliance\Models;

use App\Domains\Compliance\Enums\GdprRequestStatus;
use App\Domains\Compliance\Enums\GdprRequestType;
use App\Domains\Customer\Models\Customer;
use App\Domains\Shared\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $uuid
 * @property int $customer_id
 * @property GdprRequestType $type
 * @property GdprRequestStatus $status
 * @property string|null $admin_note
 * @property string|null $file_path
 * @property Carbon|null $completed_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class GdprRequest extends Model
{
    use HasUuid;

    protected $fillable = [
        'customer_id',
        'type',
        'status',
        'admin_note',
        'file_path',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'type'         => GdprRequestType::class,
            'status'       => GdprRequestStatus::class,
            'completed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function isPending(): bool
    {
        return $this->status === GdprRequestStatus::Pending;
    }

    public function isCompleted(): bool
    {
        return $this->status === GdprRequestStatus::Completed;
    }
}
