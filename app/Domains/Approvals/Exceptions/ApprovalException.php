<?php

declare(strict_types=1);

namespace App\Domains\Approvals\Exceptions;

use RuntimeException;

/**
 * A four-eyes approval could not proceed (audit 74) — e.g. self-approval,
 * a non-pending request, or a failed execution.
 */
final class ApprovalException extends RuntimeException
{
}
