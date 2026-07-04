<?php

declare(strict_types=1);

namespace App\Domains\Provisioning\Models;

use App\Domains\Customer\Models\Customer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $customer_id
 * @property string $name
 * @property string $public_key
 * @property string|null $fingerprint
 */
class SshKey extends Model
{
    protected $fillable = [
        'customer_id',
        'name',
        'public_key',
        'fingerprint',
    ];

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** Derive a short fingerprint from the key (MD5 of base64 payload). */
    public static function computeFingerprint(string $publicKey): ?string
    {
        $parts = preg_split('/\s+/', trim($publicKey));

        if ($parts === false || count($parts) < 2) {
            return null;
        }

        $decoded = base64_decode($parts[1], strict: true);

        if ($decoded === false) {
            return null;
        }

        return 'MD5:' . implode(':', str_split(md5($decoded), 2));
    }
}
