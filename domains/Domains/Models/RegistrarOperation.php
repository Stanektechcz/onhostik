<?php

declare(strict_types=1);

namespace Onhost\Domain\Domains\Models;

use Onhost\Platform\Eloquent\Model;

/** Every registrar command with its clTRID, redacted request/response and normalised outcome (blueprint §45.6). */
final class RegistrarOperation extends Model
{
    protected static string $idPrefix = 'rop';

    protected $table = 'registrar_operations';

    public const SENT = 'SENT';

    public const SUCCEEDED = 'SUCCEEDED';

    public const PENDING_REGISTRY = 'PENDING_REGISTRY';

    public const FAILED = 'FAILED';

    public const UNKNOWN = 'UNKNOWN';

    protected function casts(): array
    {
        return ['request' => 'array', 'response' => 'array', 'test_mode' => 'boolean', 'sent_at' => 'datetime', 'completed_at' => 'datetime'];
    }
}
