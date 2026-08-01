<?php

declare(strict_types=1);

namespace App\Domains\Loyalty\Listeners;

use App\Domains\Billing\Events\InvoicePaid;
use App\Domains\Loyalty\Services\LoyaltyPointsService;

/**
 * Awards loyalty points when an invoice is paid. Best-effort — a points-ledger
 * hiccup must never break the payment flow that fires InvoicePaid.
 */
final class AwardLoyaltyPointsOnInvoicePaid
{
    public function __construct(private readonly LoyaltyPointsService $points) {}

    public function handle(InvoicePaid $event): void
    {
        $customer = $event->invoice->customer;

        if ($customer === null) {
            return;
        }

        $earned = $this->points->pointsForAmount($event->invoice->total);

        if ($earned <= 0) {
            return;
        }

        $this->points->award(
            $customer,
            $earned,
            'Body za zaplacenou fakturu ' . $event->invoice->number,
            $event->invoice,
        );
    }
}
