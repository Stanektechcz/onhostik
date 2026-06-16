<?php

declare(strict_types=1);

namespace App\Domains\Backups\Models;

use App\Domains\Provisioning\Models\Service;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BackupRestore extends Model
{
    protected $fillable = [
        'service_id',
        'backup_file_id',
        'status',
        'requested_by',
        'notes',
    ];

    /** @return BelongsTo<Service, $this> */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /** @return BelongsTo<BackupFile, $this> */
    public function file(): BelongsTo
    {
        return $this->belongsTo(BackupFile::class, 'backup_file_id');
    }

    /** @return BelongsTo<User, $this> */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
