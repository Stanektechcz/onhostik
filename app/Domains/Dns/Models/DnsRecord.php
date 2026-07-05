<?php

declare(strict_types=1);

namespace App\Domains\Dns\Models;

use App\Domains\Dns\Enums\DnsRecordType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $dns_zone_id
 * @property DnsRecordType $type
 * @property string $name
 * @property string $content
 * @property int $ttl
 * @property int|null $priority
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class DnsRecord extends Model
{
    protected $fillable = [
        'dns_zone_id',
        'type',
        'name',
        'content',
        'ttl',
        'priority',
    ];

    protected function casts(): array
    {
        return [
            'type'     => DnsRecordType::class,
            'ttl'      => 'integer',
            'priority' => 'integer',
        ];
    }

    /** @return BelongsTo<DnsZone, $this> */
    public function dnsZone(): BelongsTo
    {
        return $this->belongsTo(DnsZone::class);
    }
}
