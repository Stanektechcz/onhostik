<?php

declare(strict_types=1);

namespace App\Domains\Customer\Actions;

use App\Domains\Customer\Models\Customer;
use App\Models\EmailDripEnrollment;
use App\Models\EmailDripSequence;

/**
 * Enrolls a customer into all active drip sequences for a given trigger event.
 * Enrollment is idempotent — calling twice for the same customer+sequence is a no-op.
 */
final class EnrollInDripSequenceAction
{
    public function execute(Customer $customer, string $triggerEvent): void
    {
        $sequences = EmailDripSequence::query()
            ->where('trigger_event', $triggerEvent)
            ->where('is_active', true)
            ->with('steps')
            ->get();

        foreach ($sequences as $sequence) {
            $alreadyEnrolled = EmailDripEnrollment::query()
                ->where('drip_sequence_id', $sequence->id)
                ->where('customer_id', $customer->id)
                ->exists();

            if ($alreadyEnrolled) {
                continue;
            }

            $firstStep = $sequence->steps->first();

            EmailDripEnrollment::create([
                'drip_sequence_id' => $sequence->id,
                'customer_id'      => $customer->id,
                'email'            => $customer->email,
                'name'             => $customer->user->name,
                'next_step_index'  => 0,
                'next_send_at'     => $firstStep !== null
                    ? now()->addDays((int) $firstStep->delay_days)
                    : null,
            ]);
        }
    }
}
