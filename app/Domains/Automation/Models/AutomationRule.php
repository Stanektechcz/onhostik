<?php

declare(strict_types=1);

namespace App\Domains\Automation\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property array<int, array<string, mixed>>|null $conditions
 * @property array<string, mixed>|null             $action_params
 */
class AutomationRule extends Model
{
    protected $fillable = [
        'name',
        'trigger',
        'conditions',
        'action',
        'action_params',
        'is_active',
        'run_count',
        'last_run_at',
    ];

    protected function casts(): array
    {
        return [
            'conditions'    => 'array',
            'action_params' => 'array',
            'is_active'     => 'boolean',
            'last_run_at'   => 'datetime',
        ];
    }

    /** @return HasMany<AutomationRuleLog, $this> */
    public function logs(): HasMany
    {
        return $this->hasMany(AutomationRuleLog::class);
    }

    /**
     * Check if this rule matches a given context payload.
     *
     * @param  array<string, mixed>  $context
     */
    public function matches(array $context): bool
    {
        $conditions = $this->conditions ?? [];

        foreach ($conditions as $condition) {
            $field    = $condition['field'] ?? '';
            $operator = $condition['operator'] ?? '=';
            $value    = $condition['value'] ?? '';
            $actual   = data_get($context, $field);

            $pass = match ($operator) {
                '='        => $actual == $value,
                '!='       => $actual != $value,
                '>'        => $actual > $value,
                '<'        => $actual < $value,
                'contains' => is_string($actual) && str_contains(strtolower($actual), strtolower((string) $value)),
                'in'       => in_array($actual, (array) $value, true),
                default    => false,
            };

            if (! $pass) {
                return false;
            }
        }

        return true;
    }
}
