<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int         $id
 * @property string      $cidr       IPv4/IPv6 CIDR notation, e.g. "192.168.1.0/24"
 * @property string|null $label
 * @property bool        $is_active
 */
class AdminIpAllowlist extends Model
{
    protected $table = 'admin_ip_allowlist';

    protected $fillable = [
        'cidr',
        'label',
        'is_active',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Check whether an IPv4 address falls within this CIDR range. */
    public function containsIp(string $ip): bool
    {
        [$network, $mask] = array_pad(explode('/', $this->cidr, 2), 2, '32');

        if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)
            || ! filter_var($network, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)
        ) {
            return false;
        }

        $ipLong      = ip2long($ip);
        $networkLong = ip2long($network);
        $maskLong    = ~((1 << (32 - (int) $mask)) - 1);

        return ($ipLong & $maskLong) === ($networkLong & $maskLong);
    }
}
