<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domains\Provisioning\Models\Service;
use App\Models\User;

class ServicePolicy
{
    /** Admins may do everything; everyone else falls through to per-ability checks. */
    public function before(User $user, string $ability): ?bool
    {
        return $user->hasRole('admin') ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->customer !== null;
    }

    public function view(User $user, Service $service): bool
    {
        return $this->owns($user, $service);
    }

    private function owns(User $user, Service $service): bool
    {
        return $user->customer !== null
            && $user->customer->id === $service->customer_id;
    }
}
