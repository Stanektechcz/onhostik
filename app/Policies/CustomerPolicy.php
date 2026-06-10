<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domains\Customer\Models\Customer;
use App\Models\User;

class CustomerPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->hasRole('admin') ? true : null;
    }

    /** A user may only ever see/update their own customer profile. */
    public function view(User $user, Customer $customer): bool
    {
        return $user->id === $customer->user_id;
    }

    public function update(User $user, Customer $customer): bool
    {
        return $user->id === $customer->user_id;
    }
}
