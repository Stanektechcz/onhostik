<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domains\Support\Models\SupportTicket;
use App\Models\User;

class SupportTicketPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->hasRole('admin') || $user->hasRole('support') ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->customer !== null;
    }

    public function view(User $user, SupportTicket $ticket): bool
    {
        return $user->customer !== null
            && $user->customer->id === $ticket->customer_id;
    }

    public function reply(User $user, SupportTicket $ticket): bool
    {
        return $this->view($user, $ticket);
    }
}
