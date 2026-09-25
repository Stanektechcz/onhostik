<?php

declare(strict_types=1);

namespace Onhost\Domain\Tax\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Tax\VatNumberChecks;
use Onhost\Domain\Tax\VatStanding;
use Throwable;

/**
 * Checks the VAT number an organization has just given or changed (TASK-0031, D31.3a): queued after the commit of the
 * registration, the guest checkout or the organization update, so the customer never waits for VIES. An outage is asked
 * again after a minute, ten minutes and an hour; after that the number stays unknown (destination VAT, review flag) until
 * the next checkout, the operator's command or a new number. A number checked within the last hour is not asked again —
 * a customer saving the same form twice is one question.
 */
final class CheckVatNumber implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 3;

    public int $timeout = 30;

    /** @var list<int> */
    public array $backoff = [60, 600, 3600];

    public function __construct(public readonly string $organizationId) {}

    public function handle(VatNumberChecks $checks): void
    {
        $organization = Organization::query()->find($this->organizationId);
        if ($organization === null || $organization->state === 'closed' || $organization->closed_at !== null) {
            return;
        }
        $subject = VatStanding::subject($organization);
        if ($subject === null) {
            return;
        }
        if ((string) $organization->vat_checked_number === $subject->value && $organization->vat_checked_at !== null && $organization->vat_checked_at->greaterThan(now()->subHour())) {
            return;
        }
        try {
            $outcome = $checks->check($organization, 'vat_id_changed', (int) config('onhost.vies.timeout_seconds', 8));
        } catch (Throwable $e) {
            // the job runs synchronously after a registration in some environments: a refused record must not fail the request
            report($e);

            return;
        }
        if ($outcome === 'unknown' && $this->job !== null && $this->attempts() < $this->tries) {
            $this->release($this->backoff[min($this->attempts() - 1, count($this->backoff) - 1)] ?? 60);
        }
    }
}
