<?php

declare(strict_types=1);

namespace Onhost\Domain\Provisioning\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Onhost\Platform\Eloquent\Model;

final class Node extends Model
{
    protected static string $idPrefix = 'node';

    protected $table = 'nodes';

    protected function casts(): array
    {
        return ['capacity' => 'array', 'usage' => 'array', 'tags' => 'array', 'last_seen_at' => 'datetime', 'remote_id' => 'integer'];
    }

    public function providerInstance(): BelongsTo
    {
        return $this->belongsTo(ProviderInstance::class, 'provider_instance_id');
    }

    public function isSchedulable(): bool
    {
        return $this->state === 'active';
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
