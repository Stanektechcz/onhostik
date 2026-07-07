<?php

declare(strict_types=1);

namespace App\Models;

use App\Domains\Provisioning\Models\Service;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int         $id
 * @property int         $service_id
 * @property int|null    $user_id
 * @property string      $reason
 * @property string|null $feedback
 */
class ServiceCancellation extends Model
{
    public const REASONS = [
        'too_expensive'      => 'Příliš drahé',
        'not_needed'         => 'Službu již nepotřebuji',
        'switching_provider' => 'Přecházím ke konkurenci',
        'technical_issues'   => 'Technické problémy',
        'poor_support'       => 'Nespokojenost s podporou',
        'other'              => 'Jiný důvod',
    ];

    protected $fillable = [
        'service_id',
        'user_id',
        'reason',
        'feedback',
    ];

    public function reasonLabel(): string
    {
        return self::REASONS[$this->reason] ?? $this->reason;
    }

    /** @return BelongsTo<Service, $this> */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
