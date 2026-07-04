<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Mail\DripStepMail;
use App\Models\EmailDripEnrollment;
use App\Models\EmailDripSequence;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class ProcessDripSequencesCommand extends Command
{
    protected $signature = 'drip:process';
    protected $description = 'Send pending drip sequence emails for enrollments whose next_send_at is due.';

    public function handle(): int
    {
        $due = EmailDripEnrollment::query()
            ->whereNull('completed_at')
            ->whereNull('unsubscribed_at')
            ->where('next_send_at', '<=', now())
            ->with(['sequence.steps'])
            ->get();

        $sent = 0;

        foreach ($due as $enrollment) {
            $sequence = $enrollment->sequence;

            if ($sequence === null || ! $sequence->is_active) {
                continue;
            }

            $steps = $sequence->steps;
            $stepIndex = $enrollment->next_step_index;

            if ($stepIndex >= $steps->count()) {
                // All steps sent — mark complete
                $enrollment->update(['completed_at' => now(), 'next_send_at' => null]);
                continue;
            }

            $step = $steps->get($stepIndex);

            if ($step === null) {
                $enrollment->update(['completed_at' => now(), 'next_send_at' => null]);
                continue;
            }

            Mail::to($enrollment->email)->send(
                new DripStepMail($step, $enrollment->email, (string) $enrollment->name)
            );

            $sent++;
            $nextIndex = $stepIndex + 1;
            $nextStep  = $steps->get($nextIndex);

            $enrollment->update([
                'next_step_index' => $nextIndex,
                'next_send_at'    => $nextStep !== null
                    ? now()->addDays((int) $nextStep->delay_days)
                    : null,
                'completed_at' => $nextStep !== null ? null : now(),
            ]);
        }

        $this->info("Drip sequences: {$sent} email(s) sent, {$due->count()} enrollment(s) processed.");

        return self::SUCCESS;
    }
}
