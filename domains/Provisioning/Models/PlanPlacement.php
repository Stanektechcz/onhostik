<?php

declare(strict_types=1);

namespace Onhost\Domain\Provisioning\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Onhost\Platform\Eloquent\Model;

/** Operator decision: this product/plan (optionally in this region) is provisioned on this provider instance / node. */
final class PlanPlacement extends Model
{
    protected static string $idPrefix = 'plc';

    protected $table = 'plan_placements';

    protected function casts(): array
    {
        return ['priority' => 'integer'];
    }

    public function providerInstance(): BelongsTo
    {
        return $this->belongsTo(ProviderInstance::class, 'provider_instance_id');
    }

    public function node(): BelongsTo
    {
        return $this->belongsTo(Node::class, 'node_id');
    }

    /** Specificity: plan + region > plan > product + region > product. */
    public function specificity(): int
    {
        return ($this->plan_key !== null ? 2 : 0) + ($this->region_code !== null ? 1 : 0);
    }
}
