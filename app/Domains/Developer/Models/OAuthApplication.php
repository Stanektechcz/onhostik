<?php

declare(strict_types=1);

namespace App\Domains\Developer\Models;

use App\Domains\Customer\Models\Customer;
use App\Domains\Shared\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $uuid
 * @property int $customer_id
 * @property string $name
 * @property string $client_id
 * @property string $client_secret_hash
 * @property list<string>|null $redirect_uris
 * @property list<string>|null $scopes
 * @property bool $is_active
 * @property Carbon|null $last_used_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class OAuthApplication extends Model
{
    use HasUuid;

    protected $table = 'oauth_applications';

    protected $fillable = [
        'customer_id',
        'name',
        'client_id',
        'client_secret_hash',
        'redirect_uris',
        'scopes',
        'is_active',
        'last_used_at',
    ];

    protected function casts(): array
    {
        return [
            'redirect_uris' => 'array',
            'scopes'        => 'array',
            'is_active'     => 'boolean',
            'last_used_at'  => 'datetime',
        ];
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
