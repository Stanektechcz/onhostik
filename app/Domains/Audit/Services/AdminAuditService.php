<?php

declare(strict_types=1);

namespace App\Domains\Audit\Services;

use App\Models\AdminActionLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

final class AdminAuditService
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function log(
        User $admin,
        string $action,
        ?Model $target = null,
        array $metadata = [],
        ?Request $request = null,
    ): AdminActionLog {
        $req = $request ?? request();

        return AdminActionLog::create([
            'admin_user_id' => $admin->id,
            'action'        => $action,
            'target_type'   => $target ? $target::class : null,
            'target_id'     => $target?->getKey(),
            'metadata'      => $metadata ?: null,
            'ip_address'    => $req->ip(),
            'user_agent'    => mb_substr((string) $req->userAgent(), 0, 500),
            'created_at'    => now(),
        ]);
    }
}
