<?php

declare(strict_types=1);

namespace App\Models;

use App\Domains\Customer\Models\Customer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerCommunicationLog extends Model
{
    protected $table = 'customer_communication_logs';

    protected $fillable = [
        'customer_id',
        'admin_user_id',
        'channel',
        'direction',
        'subject',
        'body',
        'created_by',
    ];

    protected function casts(): array
    {
        return [];
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<User, $this> */
    public function adminUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_user_id');
    }
}
