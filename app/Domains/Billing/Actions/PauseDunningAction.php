<?php

declare(strict_types=1);

namespace App\Domains\Billing\Actions;

use App\Domains\Billing\Models\Invoice;

final class PauseDunningAction
{
    public function pause(Invoice $invoice, int $days): void
    {
        $invoice->update(['dunning_paused_until' => now()->addDays($days)]);

        activity('invoice')
            ->performedOn($invoice)
            ->causedBy(auth()->user())
            ->withProperties(['paused_until' => now()->addDays($days)->toDateTimeString(), 'days' => $days])
            ->log('invoice.dunning_paused');
    }

    public function resume(Invoice $invoice): void
    {
        $invoice->update(['dunning_paused_until' => null]);

        activity('invoice')
            ->performedOn($invoice)
            ->causedBy(auth()->user())
            ->log('invoice.dunning_resumed');
    }
}
