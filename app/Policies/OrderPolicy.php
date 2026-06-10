<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domains\Billing\Models\Order;
use App\Models\User;

class OrderPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->hasRole('admin') ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->customer !== null;
    }

    public function view(User $user, Order $order): bool
    {
        return $user->customer !== null
            && $user->customer->id === $order->customer_id;
    }

    public function create(User $user): bool
    {
        return $user->customer !== null;
    }
}
