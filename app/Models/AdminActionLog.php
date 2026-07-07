<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AdminActionLog extends Model
{
    public const UPDATED_AT = null;

    public const ACTIONS = [
        'impersonate_start'   => 'Zahájení impersonace',
        'impersonate_stop'    => 'Ukončení impersonace',
        'credit_adjustment'   => 'Ruční úprava kreditu',
        'manual_payment'      => 'Ruční platba',
        'price_override'      => 'Přepsání ceny',
        'service_suspend'     => 'Ruční pozastavení služby',
        'service_terminate'   => 'Ruční ukončení služby',
        'invoice_void'        => 'Storno faktury',
    ];

    protected $fillable = [
        'admin_user_id',
        'action',
        'target_type',
        'target_id',
        'metadata',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata'   => 'array',
            'created_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_user_id');
    }

    /** @return MorphTo<\Illuminate\Database\Eloquent\Model, $this> */
    public function target(): MorphTo
    {
        return $this->morphTo();
    }

    public function actionLabel(): string
    {
        return self::ACTIONS[$this->action] ?? $this->action;
    }
}
