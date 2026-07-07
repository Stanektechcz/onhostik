<?php

declare(strict_types=1);

namespace App\Models;

use App\Domains\Provisioning\Models\Service;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SnapshotRestoreRequest extends Model
{
    protected $fillable = [
        'service_id',
        'user_id',
        'snapshot_id',
        'restore_point',
        'status',
        'customer_note',
        'admin_note',
        'handled_by',
    ];

    protected function casts(): array
    {
        return [];
    }

    /** @return BelongsTo<Service, $this> */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
