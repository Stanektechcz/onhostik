<?php

declare(strict_types=1);

namespace Onhost\Domain\Advisories\Models;

use Illuminate\Support\Carbon;
use Onhost\Platform\Eloquent\Model;

/**
 * One inventory item an advisory hits, with what the plan does about it (`class`, `action`) and how far that got (`state`).
 *
 * Classes: **A** a fix the platform runs (a plugin update, a core security release through staging) · **B** a fix that may change
 * how the site behaves (a major version, a theme) — run only when staff include it, the customer is told first · **D** nothing the
 * platform can fix (no fixed version, a probable match) — the customer is told · **P** the platform's own software (panels, the
 * control plane) — operations, never the customer.
 *
 * @property array<string,mixed>|null $action
 * @property ?Carbon $notified_at
 */
final class AdvisoryMatch extends Model
{
    public const CLASSES = ['A', 'B', 'D', 'P'];

    public const OPEN = 'open';

    public const QUEUED = 'queued';

    public const RUNNING = 'running';

    public const FIXED = 'fixed';

    public const FAILED = 'failed';

    public const NOTIFIED = 'notified';

    public const SKIPPED = 'skipped';

    /** The software is no longer there in the affected version (updated by someone, uninstalled, the service ended). */
    public const GONE = 'gone';

    protected static string $idPrefix = 'avm';

    protected $table = 'security_advisory_matches';

    protected function casts(): array
    {
        return ['action' => 'array', 'notified_at' => 'datetime'];
    }

    /** @return array<string,mixed> */
    public function present(?string $label = null): array
    {
        return [
            'id' => $this->id, 'subject_type' => $this->subject_type, 'subject_id' => $this->subject_id, 'organization_id' => $this->organization_id, 'label' => $label,
            'ecosystem' => $this->ecosystem, 'product' => $this->product, 'version' => $this->version, 'fixed_in' => $this->fixed_in, 'confidence' => $this->confidence,
            'class' => $this->class, 'action' => $this->action, 'state' => $this->state, 'operation_id' => $this->operation_id, 'notified_at' => $this->notified_at?->toIso8601String(),
        ];
    }
}
