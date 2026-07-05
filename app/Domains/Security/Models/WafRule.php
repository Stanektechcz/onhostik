<?php

declare(strict_types=1);

namespace App\Domains\Security\Models;

use App\Domains\Provisioning\Models\Service;
use App\Domains\Security\Enums\WafRuleType;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int|null $service_id
 * @property int|null $created_by
 * @property WafRuleType $type
 * @property string $value
 * @property string $action
 * @property string|null $notes
 * @property bool $is_active
 */
class WafRule extends Model
{
    protected $fillable = [
        'service_id',
        'created_by',
        'type',
        'value',
        'action',
        'notes',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'type'      => WafRuleType::class,
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<Service, $this> */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<WafEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(WafEvent::class, 'waf_rule_id');
    }

    public function isGlobal(): bool
    {
        return $this->service_id === null;
    }
}
