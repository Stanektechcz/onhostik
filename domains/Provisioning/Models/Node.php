<?php

declare(strict_types=1);

namespace Onhost\Domain\Provisioning\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Onhost\Platform\Eloquent\Model;

final class Node extends Model
{
    protected static string $idPrefix = 'node';

    protected $table = 'nodes';

    /** Discovered but not yet qualified: it exists, it is listed, and nothing may be sold on it (H471). */
    public const QUALIFYING = 'qualifying';

    public const ACTIVE = 'active';

    protected function casts(): array
    {
        return ['capacity' => 'array', 'usage' => 'array', 'tags' => 'array', 'last_seen_at' => 'datetime', 'qualified_at' => 'datetime', 'qualification' => 'array', 'remote_id' => 'integer'];
    }

    public function providerInstance(): BelongsTo
    {
        return $this->belongsTo(ProviderInstance::class, 'provider_instance_id');
    }

    public function isSchedulable(): bool
    {
        return $this->state === self::ACTIVE;
    }

    /** Whether this node has ever been through the qualification it is supposed to pass before it carries anybody. */
    public function isQualified(): bool
    {
        return $this->qualified_at !== null;
    }

    public function cap(string $key, float $default = 0.0): float
    {
        return (float) data_get($this->capacity, $key, $default);
    }

    public function use(string $key, float $default = 0.0): float
    {
        return (float) data_get($this->usage, $key, $default);
    }
}
