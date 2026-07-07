<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int         $id
 * @property string      $metric         Key identifying which business metric to watch
 * @property string      $operator       'gte' or 'lte'
 * @property float       $threshold
 * @property bool        $is_active
 * @property float|null  $last_value
 * @property Carbon|null $triggered_at
 * @property Carbon|null $last_checked_at
 */
class KpiAlert extends Model
{
    protected $fillable = [
        'metric',
        'operator',
        'threshold',
        'is_active',
        'triggered_at',
        'last_value',
        'last_checked_at',
    ];

    protected function casts(): array
    {
        return [
            'threshold'       => 'float',
            'last_value'      => 'float',
            'is_active'       => 'boolean',
            'triggered_at'    => 'datetime',
            'last_checked_at' => 'datetime',
        ];
    }

    public function isTriggered(): bool
    {
        return $this->triggered_at !== null;
    }

    /** Whether the given current value breaches the configured threshold. */
    public function breaches(float $value): bool
    {
        return match ($this->operator) {
            'lte'   => $value <= $this->threshold,
            default => $value >= $this->threshold, // gte
        };
    }

    /** Human-readable metric label (Czech). */
    public function metricLabel(): string
    {
        return match ($this->metric) {
            'overdue_invoices_count'    => 'Počet po splatnosti',
            'failed_backups_24h'        => 'Neúspěšné zálohy (24 h)',
            'suspended_services_count'  => 'Pozastavené služby',
            'open_tickets_count'        => 'Otevřené tikety',
            'monthly_revenue_czk'       => 'Měsíční příjem (CZK)',
            default                     => $this->metric,
        };
    }

    /** Human-readable operator label. */
    public function operatorLabel(): string
    {
        return $this->operator === 'lte' ? '≤' : '≥';
    }
}
