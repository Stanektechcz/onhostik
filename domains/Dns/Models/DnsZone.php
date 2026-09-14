<?php

declare(strict_types=1);

namespace Onhost\Domain\Dns\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Onhost\Platform\Eloquent\Model;

/** Canonical DNS zone; PowerDNS is the executor, this table is the truth (blueprint §48). */
final class DnsZone extends Model
{
    use SoftDeletes;

    protected static string $idPrefix = 'zone';

    protected $table = 'dns_zones';

    protected function casts(): array
    {
        return ['serial' => 'integer', 'version' => 'integer', 'dnssec' => 'boolean', 'dnssec_ds' => 'array', 'nameservers' => 'array', 'secondary_providers' => 'array', 'committed_at' => 'datetime', 'last_verified_at' => 'datetime'];
    }

    public function records(): HasMany
    {
        return $this->hasMany(DnsRecord::class, 'zone_id')->orderBy('name')->orderBy('type');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(DnsZoneVersion::class, 'zone_id')->orderByDesc('version');
    }

    public function pendingChanges(): HasMany
    {
        return $this->hasMany(DnsChange::class, 'zone_id')->where('state', 'pending')->orderBy('created_at');
    }

    /** Next SOA serial in YYYYMMDDnn form, monotonic even after >99 commits a day. */
    public function nextSerial(): int
    {
        $today = (int) now()->format('Ymd00');
        $current = (int) $this->serial;

        return $current >= $today ? $current + 1 : $today + 1;
    }
}
