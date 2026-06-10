<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domains\Billing\Models\Invoice;
use App\Models\User;

class InvoicePolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->hasRole('admin') ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->customer !== null;
    }

    public function view(User $user, Invoice $invoice): bool
    {
        return $user->customer !== null
            && $user->customer->id === $invoice->customer_id;
    }
}
