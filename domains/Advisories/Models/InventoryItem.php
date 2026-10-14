<?php

declare(strict_types=1);

namespace Onhost\Domain\Advisories\Models;

use Illuminate\Support\Carbon;
use Onhost\Platform\Eloquent\Model;

/**
 * One piece of software the platform runs or hosts, where and in which version: a WordPress plugin on a customer's site
 * (`subject_type = service`), the version a panel reports (`provider_instance`), a library of the control plane (`platform`).
 * Only services the platform provisioned are ever listed — a historical site on a shared panel is not a service.
 *
 * @property array<string,mixed>|null $meta
 * @property Carbon $seen_at
 */
final class InventoryItem extends Model
{
    public const SERVICE = 'service';

    public const INSTANCE = 'provider_instance';

    public const PLATFORM = 'platform';

    protected static string $idPrefix = 'inv';

    protected $table = 'software_inventory';

    protected function casts(): array
    {
        return ['meta' => 'array', 'seen_at' => 'datetime'];
    }
}
