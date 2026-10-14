<?php

declare(strict_types=1);

namespace Onhost\Domain\Services\Models;

use Illuminate\Support\Carbon;
use Onhost\Platform\Eloquent\Model;

/**
 * One verified restore of a web service and its protocol (W2-4, `VerifiedRestore`).
 *
 * @property string $id
 * @property string $organization_id
 * @property string $service_id
 * @property string|null $operation_id
 * @property string|null $backup_id
 * @property string $trigger
 * @property string $state
 * @property string|null $outcome
 * @property Carbon|null $backup_taken_at
 * @property Carbon|null $started_at
 * @property Carbon|null $checks_done_at
 * @property Carbon|null $finished_at
 * @property int|null $rpo_seconds
 * @property int|null $rto_seconds
 * @property array<int, array<string, mixed>>|null $steps
 * @property array<string, mixed>|null $suppression
 * @property array<string, mixed>|null $target
 * @property array<int, array<string, mixed>>|null $skipped_sets
 */
final class VerifiedRestoreRun extends Model
{
    public const RUNNING = 'running';

    public const FINISHED = 'finished';

    public const PASS = 'pass';

    public const FAIL = 'fail';

    public const NOT_VERIFIED = 'not_verified';

    protected static string $idPrefix = 'vrr';

    protected $table = 'verified_restore_runs';

    protected function casts(): array
    {
        return [
            'backup_taken_at' => 'datetime', 'started_at' => 'datetime', 'checks_done_at' => 'datetime', 'finished_at' => 'datetime',
            'rpo_seconds' => 'integer', 'rto_seconds' => 'integer',
            'steps' => 'array', 'suppression' => 'array', 'target' => 'array', 'skipped_sets' => 'array',
        ];
    }

    /** Records one step (a later record of the same key replaces the earlier one). */
    public function recordStep(string $key, string $status, string $detail = ''): void
    {
        $steps = array_values(array_filter((array) $this->steps, fn ($step) => is_array($step) && ($step['key'] ?? null) !== $key));
        $steps[] = ['key' => $key, 'status' => $status, 'at' => now()->toIso8601String(), 'detail' => mb_substr($detail, 0, 300)];
        $this->forceFill(['steps' => $steps])->save();
    }

    /** @return array<string, mixed>|null */
    public function step(string $key): ?array
    {
        foreach ((array) $this->steps as $step) {
            if (is_array($step) && ($step['key'] ?? null) === $key) {
                return $step;
            }
        }

        return null;
    }

    public function stepStatus(string $key): ?string
    {
        $step = $this->step($key);

        return $step === null ? null : (string) $step['status'];
    }

    /** @param array<string, mixed> $values */
    public function mergeTarget(array $values): void
    {
        $this->forceFill(['target' => array_replace((array) $this->target, $values)])->save();
    }
}
