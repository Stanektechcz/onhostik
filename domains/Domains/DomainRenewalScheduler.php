<?php

declare(strict_types=1);

namespace Onhost\Domain\Domains;

use Onhost\Domain\Domains\Models\Domain;
use Onhost\Domain\Domains\Models\DomainRenewalJob;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Events\GenericEvent;
use Onhost\Platform\Outbox\OutboxPublisher;
use Throwable;

/**
 * Renewal orchestration (blueprint §46.3): notices at 60/30/14/7/3/1 days,
 * domain-priority holds `renew_lead_days` before expiry, daily retries while the
 * wallet is short — and on through the protective period after the expiry
 * (`onhost.domains.grace_retry_days`): the registry still renews at the ordinary price for weeks, and a renewal used to
 * be abandoned the day before the expiry, so a customer who topped up the next morning lost the domain anyway.
 */
final class DomainRenewalScheduler
{
    public function __construct(private readonly DomainService $domains, private readonly OutboxPublisher $outbox) {}

    /** @return array{scheduled:int, notices:int, started:int, retried:int, failed:int} */
    public function tick(?CommandContext $context = null): array
    {
        $context ??= CommandContext::system('domain renewal scheduler');
        $scheduled = $this->schedule();
        $notices = $this->sendNotices();
        [$started, $retried, $failed] = $this->execute($context);

        return ['scheduled' => $scheduled, 'notices' => $notices, 'started' => $started, 'retried' => $retried, 'failed' => $failed];
    }

    /** One open job per domain whose expiry is inside the notice window. */
    public function schedule(): int
    {
        $window = max((array) config('onhost.domains.notice_days', [60, 30, 14, 7, 3, 1]));
        $lead = (int) config('onhost.domains.renew_lead_days', 14);
        $count = 0;
        $grace = self::graceDays();
        $candidates = Domain::query()->whereNotNull('expires_at')
            ->where(fn ($q) => $q->where(fn ($q) => $q->where('state', DomainStateMachine::ACTIVE)->where('expires_at', '<=', now()->addDays($window)))
                ->orWhere(fn ($q) => $q->whereIn('state', [DomainStateMachine::EXPIRED, DomainStateMachine::GRACE])->where('auto_renew', true)->where('expires_at', '>=', now()->subDays($grace)))) // expired, still renewable at the ordinary price
            ->whereDoesntHave('renewalJobs', fn ($q) => $q->whereIn('state', [DomainRenewalJob::SCHEDULED, DomainRenewalJob::HOLD_PLACED, DomainRenewalJob::SENT, DomainRenewalJob::PENDING_REGISTRY]))->get();
        foreach ($candidates as $domain) {
            $alreadyDone = DomainRenewalJob::query()->where('domain_id', $domain->id)->where('due_at', $domain->expires_at)->where('state', DomainRenewalJob::SUCCEEDED)->exists();
            if ($alreadyDone) {
                continue;
            }
            DomainRenewalJob::query()->create([
                'domain_id' => $domain->id, 'organization_id' => $domain->organization_id, 'period_years' => $domain->renewal_period ?: 1,
                'state' => DomainRenewalJob::SCHEDULED, 'due_at' => $domain->expires_at, 'scheduled_for' => $domain->expires_at->isPast() ? now() : $domain->expires_at->copy()->subDays($lead), 'notices_sent' => [],
            ]);
            $count++;
        }

        return $count;
    }

    public function sendNotices(): int
    {
        $thresholds = array_map('intval', (array) config('onhost.domains.notice_days', [60, 30, 14, 7, 3, 1]));
        sort($thresholds);
        $sent = 0;
        $jobs = DomainRenewalJob::query()->whereIn('state', [DomainRenewalJob::SCHEDULED, DomainRenewalJob::HOLD_PLACED, DomainRenewalJob::SENT, DomainRenewalJob::PENDING_REGISTRY, DomainRenewalJob::FAILED])->where('due_at', '>=', now()->subDay())->get();
        foreach ($jobs as $job) {
            $domain = Domain::query()->find($job->domain_id);
            if ($domain === null || $domain->expires_at === null) {
                continue;
            }
            $days = $domain->daysToExpiry();
            if ($days === null) {
                continue;
            }
            $already = array_map('intval', (array) ($job->notices_sent ?? []));
            // The most urgent applicable notice (smallest threshold >= days left); coarser ones are implied and never sent afterwards.
            $applicable = array_values(array_filter($thresholds, fn (int $t) => $days <= $t));
            $current = $applicable[0] ?? null;
            if ($current !== null && ! in_array($current, $already, true)) {
                $this->outbox->publish(GenericEvent::of('domain.renewal_notice', 'domain', $domain->id, [
                    'fqdn' => $domain->fqdn_ascii, 'days' => $current, 'days_left' => $days, 'expires_at' => $domain->expires_at->toIso8601String(), 'auto_renew' => $domain->auto_renew, 'job_id' => $job->id, 'job_state' => $job->state, 'last_error' => $job->last_error,
                ], $domain->organization_id));
                $already = array_values(array_unique(array_merge($already, $applicable)));
                sort($already);
                $sent++;
                $job->forceFill(['notices_sent' => $already])->save();
            }
        }

        return $sent;
    }

    /** Days after the expiry during which a renewal is still attempted (the registry renews at the ordinary price; afterwards it is a restore). */
    public static function graceDays(): int
    {
        return max(0, (int) config('onhost.domains.grace_retry_days', 20));
    }

    /**
     * Money arrived: the organization's renewals that waited for it are tried on the next pass, not tomorrow.
     */
    public function wake(string $organizationId): int
    {
        return DomainRenewalJob::query()->where('organization_id', $organizationId)->where('state', DomainRenewalJob::SCHEDULED)->where('attempts', '>', 0)->where('scheduled_for', '>', now())->update(['scheduled_for' => now()]);
    }

    /** @return array{0:int,1:int,2:int} started, retried, failed */
    public function execute(CommandContext $context): array
    {
        $started = 0;
        $retried = 0;
        $failed = 0;
        $due = DomainRenewalJob::query()->where('state', DomainRenewalJob::SCHEDULED)->where('scheduled_for', '<=', now())->orderBy('due_at')->limit(200)->get();
        foreach ($due as $job) {
            $domain = Domain::query()->find($job->domain_id);
            if ($domain === null || ! $domain->auto_renew || ! in_array($domain->state, [DomainStateMachine::ACTIVE, DomainStateMachine::EXPIRED, DomainStateMachine::GRACE], true)) {
                $job->forceFill(['state' => DomainRenewalJob::SKIPPED, 'last_error' => $domain === null ? 'domain missing' : ($domain->auto_renew ? "domain state {$domain->state}" : 'auto-renew disabled')])->save();

                continue;
            }
            try {
                $this->domains->renew($domain, (int) $job->period_years, $context->withScope($domain->organization_id), "renewal_job:{$job->id}:{$job->attempts}", $job);
                $started++;
            } catch (DomainError $e) {
                $job->refresh(); // whatever the attempt wrote was rolled back with it
                if ($e->error === 'domain_renewal_in_progress') { // the customer got there first: that renewal is this one
                    $job->forceFill(['scheduled_for' => now()->addHours(6), 'last_error' => 'renewal already in progress'])->save();

                    continue;
                }
                $job->forceFill(['state' => DomainRenewalJob::SCHEDULED, 'attempts' => $job->attempts + 1, 'last_error' => mb_substr($e->getMessage(), 0, 250)]);
                $daysLeft = $domain->daysToExpiry() ?? 0;
                $sinceExpiry = $domain->expires_at !== null && $domain->expires_at->isPast() ? (int) floor($domain->expires_at->diffInDays(now(), true)) : 0;
                if ($daysLeft >= 1 || $sinceExpiry <= self::graceDays()) {
                    $job->forceFill(['scheduled_for' => now()->addDay()])->save(); // retry daily — up to the expiry and on through the protective period
                    $retried++;
                    $this->outbox->publish(GenericEvent::of('domain.renewal_payment_failed', 'domain', $domain->id, ['fqdn' => $domain->fqdn_ascii, 'error' => $e->error, 'days_left' => $daysLeft, 'in_grace' => $daysLeft < 1, 'grace_days_left' => $daysLeft < 1 ? max(0, self::graceDays() - $sinceExpiry) : null, 'job_id' => $job->id], $domain->organization_id));
                } else {
                    $job->forceFill(['state' => DomainRenewalJob::FAILED])->save();
                    $failed++;
                    $this->outbox->publish(GenericEvent::of('domain.renewal_abandoned', 'domain', $domain->id, ['fqdn' => $domain->fqdn_ascii, 'error' => $e->error, 'job_id' => $job->id], $domain->organization_id));
                }
            } catch (Throwable $e) {
                $job->forceFill(['attempts' => $job->attempts + 1, 'last_error' => mb_substr(get_class($e).': '.$e->getMessage(), 0, 250), 'scheduled_for' => now()->addHours(6)])->save();
                $retried++;
            }
        }

        return [$started, $retried, $failed];
    }
}
