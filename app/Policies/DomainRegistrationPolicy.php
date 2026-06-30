<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domains\Provisioning\Models\DomainRegistration;
use App\Models\User;

class DomainRegistrationPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->hasRole('admin') ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->customer !== null;
    }

    /** Ownership is derived through the parent service. */
    public function view(User $user, DomainRegistration $domain): bool
    {
        return $user->customer !== null
            && $user->customer->id === $domain->service?->customer_id;
    }

    public function update(User $user, DomainRegistration $domain): bool
    {
        return $this->view($user, $domain);
    }
}
