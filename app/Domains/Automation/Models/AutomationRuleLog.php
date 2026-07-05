<?php

declare(strict_types=1);

namespace App\Domains\Automation\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AutomationRuleLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'automation_rule_id',
        'entity_type',
        'entity_id',
        'outcome',
        'message',
    ];

    /** @return BelongsTo<AutomationRule, $this> */
    public function rule(): BelongsTo
    {
        return $this->belongsTo(AutomationRule::class, 'automation_rule_id');
    }
}
