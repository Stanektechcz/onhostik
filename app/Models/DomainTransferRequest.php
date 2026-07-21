<?php

declare(strict_types=1);

namespace App\Models;

use App\Domains\Customer\Models\Customer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A customer's request to transfer a domain in to us.
 *
 * `auth_code` is the EPP/transfer authorisation code — a credential that lets
 * whoever holds it move the domain to another registrar. It is encrypted at
 * rest and hidden from serialization, matching DomainRegistration::auth_code.
 * It was previously stored in plain text.
 *
 * @property string|null $auth_code
 */
class DomainTransferRequest extends Model
{
    protected $fillable = [
        'customer_id',
        'domain_name',
        'auth_code',
        'status',
        'admin_note',
        'handled_by',
    ];

    /** Never let the transfer code leak into JSON, logs or API responses. */
    protected $hidden = ['auth_code'];

    protected function casts(): array
    {
        return [
            'auth_code' => 'encrypted',
        ];
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
