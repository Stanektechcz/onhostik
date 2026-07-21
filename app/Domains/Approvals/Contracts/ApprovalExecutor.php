<?php

declare(strict_types=1);

namespace App\Domains\Approvals\Contracts;

use App\Domains\Approvals\Models\ApprovalRequest;

/**
 * Performs the real work of an approved request (audit 74).
 *
 * The executor is the ONLY place the risky action actually happens. Staging a
 * request writes nothing but the request row; execution runs here, once, after
 * a second admin has approved. Implementations must be idempotent-safe and
 * throw on failure so the service can record it.
 */
interface ApprovalExecutor
{
    public function execute(ApprovalRequest $request): void;
}
