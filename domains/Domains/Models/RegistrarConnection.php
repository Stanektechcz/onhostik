<?php

declare(strict_types=1);

namespace Onhost\Domain\Domains\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Platform\Eloquent\Model;
use Onhost\Platform\Secrets\SecretRef;

/** A customer's own registrar account connected to the platform (blueprint: bring your own WEDOS API). */
final class RegistrarConnection extends Model
{
    protected static string $idPrefix = 'rcon';

    protected $table = 'registrar_connections';

    public const STATES = ['pending', 'active', 'error', 'disabled'];

    public const DEFAULT_SETTINGS = ['auto_sync' => true, 'sync_dns' => true, 'notices' => true, 'credit_threshold_minor' => 20000, 'pair_service_id' => null];

    protected function casts(): array
    {
        return ['settings' => 'array', 'stats' => 'array', 'runs' => 'array', 'last_probed_at' => 'datetime', 'last_synced_at' => 'datetime'];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }

    public function secretRef(): SecretRef
    {
        return SecretRef::parse($this->secret_ref);
    }

    public function setting(string $key, mixed $default = null): mixed
    {
        return data_get(array_merge(self::DEFAULT_SETTINGS, (array) $this->settings), $key, $default);
    }

    public function isActive(): bool
    {
        return $this->state === 'active';
    }
}
