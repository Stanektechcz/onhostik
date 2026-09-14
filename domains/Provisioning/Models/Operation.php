<?php

declare(strict_types=1);

namespace Onhost\Domain\Provisioning\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Onhost\Platform\Eloquent\Model;
use Onhost\Platform\StateMachine\StateMachine;

/**
 * Durable, idempotent, resumable unit of provisioning work (blueprint §5.2):
 * operation id, actor, idempotency key, correlation, desired state, attempts.
 */
final class Operation extends Model
{
    protected static string $idPrefix = 'op';

    protected $table = 'operations';

    public const PENDING = 'PENDING';

    public const RUNNING = 'RUNNING';

    public const WAITING = 'WAITING';

    public const SUCCEEDED = 'SUCCEEDED';

    public const FAILED = 'FAILED';

    public const CANCELLED = 'CANCELLED';

    public const COMPENSATED = 'COMPENSATED';

    protected function casts(): array
    {
        return [
            'desired' => 'array', 'context' => 'array', 'result' => 'array', 'error' => 'array', 'external_handle' => 'array',
            'step' => 'integer', 'steps_total' => 'integer', 'attempts' => 'integer',
            'queued_at' => 'datetime', 'started_at' => 'datetime', 'finished_at' => 'datetime', 'next_run_at' => 'datetime', 'retry_until' => 'datetime',
        ];
    }

    public static function machine(): StateMachine
    {
        return new StateMachine('operation', [
            self::PENDING => ['label' => 'Ve frontě', 'next' => [self::RUNNING, self::CANCELLED], 'tone' => 'warn', 'ui' => 'queued'],
            self::RUNNING => ['label' => 'Běží', 'next' => [self::WAITING, self::SUCCEEDED, self::FAILED, self::PENDING, self::CANCELLED], 'tone' => 'warn', 'ui' => 'running'],
            self::WAITING => ['label' => 'Čeká na provider', 'next' => [self::RUNNING, self::FAILED, self::CANCELLED], 'tone' => 'warn', 'ui' => 'running'],
            self::SUCCEEDED => ['label' => 'Hotovo', 'next' => [], 'tone' => 'ok', 'ui' => 'done'],
            self::FAILED => ['label' => 'Selhalo', 'next' => [self::PENDING, self::COMPENSATED, self::CANCELLED], 'tone' => 'hot', 'ui' => 'failed'],
            self::CANCELLED => ['label' => 'Zrušeno', 'next' => [], 'tone' => 'off', 'ui' => 'failed'],
            self::COMPENSATED => ['label' => 'Kompenzováno', 'next' => [], 'tone' => 'off', 'ui' => 'failed'],
        ]);
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(OperationAttempt::class, 'operation_id');
    }

    public function isTerminal(): bool
    {
        return in_array($this->state, [self::SUCCEEDED, self::CANCELLED, self::COMPENSATED], true);
    }

    public function ctx(string $key, mixed $default = null): mixed
    {
        return data_get($this->context, $key, $default);
    }

    public function withContext(array $patch): self
    {
        $this->context = array_replace($this->context ?? [], $patch);

        return $this;
    }
}
