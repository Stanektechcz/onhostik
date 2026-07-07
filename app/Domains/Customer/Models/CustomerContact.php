<?php

declare(strict_types=1);

namespace App\Domains\Customer\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerContact extends Model
{
    public const ROLES = [
        'general'   => 'Obecný',
        'billing'   => 'Fakturační',
        'technical' => 'Technický',
        'manager'   => 'Manažer',
    ];

    protected $fillable = [
        'customer_id',
        'name',
        'email',
        'phone',
        'role',
        'receives_invoices',
        'receives_notifications',
        'is_primary',
        'note',
    ];

    protected $casts = [
        'receives_invoices'      => 'boolean',
        'receives_notifications' => 'boolean',
        'is_primary'             => 'boolean',
    ];

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function roleLabel(): string
    {
        return self::ROLES[$this->role] ?? $this->role;
    }
}
