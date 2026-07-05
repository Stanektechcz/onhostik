<?php

declare(strict_types=1);

namespace App\Domains\Security\Models;

use App\Domains\Provisioning\Models\Service;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $service_id
 * @property int|null $waf_rule_id
 * @property string $ip_address
 * @property string|null $country_code
 * @property string|null $request_uri
 * @property string|null $method
 * @property string $action_taken
 * @property Carbon $blocked_at
 */
class WafEvent extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'service_id',
        'waf_rule_id',
        'ip_address',
        'country_code',
        'request_uri',
        'method',
        'action_taken',
        'blocked_at',
    ];

    protected function casts(): array
    {
        return [
            'blocked_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Service, $this> */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /** @return BelongsTo<WafRule, $this> */
    public function rule(): BelongsTo
    {
        return $this->belongsTo(WafRule::class, 'waf_rule_id');
    }
}
