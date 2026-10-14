<?php

declare(strict_types=1);

namespace Onhost\Domain\Advisories;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Onhost\Domain\Advisories\Models\Advisory;
use Onhost\Domain\Advisories\Models\AdvisoryCase;
use Onhost\Domain\Advisories\Models\AdvisoryMatch;
use Onhost\Domain\Content\Commands\ContentCommand;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\ServiceService;
use Onhost\Platform\Commands\CommandBus;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Events\GenericEvent;
use Onhost\Platform\Outbox\OutboxPublisher;
use Throwable;

/**
 * What staff decide about an advisory that hits us, and the run of that decision (SN-3 … SN-6).
 *
 *  - **triage**: relevant (waits for approval) or dismissed with a note.
 *  - **approve**: the plan runs. Class A fixes (and B when staff include them) are queued; the customer of every B and D place is
 *    told at once what, where and why. Fixes run in waves through the ordinary service actions (`ServiceService::requestAction`,
 *    audited operations with their own staging and health steps): a canary, then `wave_percent` %, then the rest. The first failed
 *    operation halts the case: nothing more is started and the remaining customers are told instead. When the waves are done the
 *    customers whose sites were fixed get one notice each.
 *  - **notify**: tell the affected customers without running anything.
 *
 * Once customers were told (`announced_at`) a draft blog post is written through the content command (never published by itself).
 */
final class AdvisoryCases
{
    public function __construct(private readonly ServiceService $services, private readonly OutboxPublisher $outbox, private readonly CommandBus $bus) {}

    /** How many customer places the approval would change (A, plus B when staff include it): over the limit, a second person signs. */
    public static function targets(AdvisoryCase $case, bool $includeRisky): int
    {
        return AdvisoryMatch::query()->where('case_id', $case->id)->where('state', AdvisoryMatch::OPEN)->whereIn('class', $includeRisky ? ['A', 'B'] : ['A'])->count();
    }

    public function triage(AdvisoryCase $case, string $decision, ?string $note, CommandContext $context): AdvisoryCase
    {
        if (! in_array($case->state, [AdvisoryCase::NEW, AdvisoryCase::AWAITING], true)) {
            throw new DomainError('advisory_case_state', "The case is {$case->state}; only a new or waiting case can be triaged.", 409, ['state' => $case->state]);
        }
        if ($decision === 'dismissed' && trim((string) $note) === '') {
            throw new DomainError('advisory_note_required', 'Dismissing a case needs a note saying why.', 422, ['field' => 'note']);
        }
        $case->forceFill([
            'state' => $decision === 'dismissed' ? AdvisoryCase::DISMISSED : AdvisoryCase::AWAITING, 'decided_by' => $context->actorId,
            'decided_at' => now(), 'decision_note' => $note === null ? null : mb_substr($note, 0, 1000), 'resolved_at' => $decision === 'dismissed' ? now() : null,
        ])->save();
        if ($decision === 'dismissed') {
            AdvisoryMatch::query()->where('case_id', $case->id)->where('state', AdvisoryMatch::OPEN)->update(['state' => AdvisoryMatch::SKIPPED]);
        }

        return $case;
    }

    public function approve(AdvisoryCase $case, bool $includeRisky, int $planRevision, ?string $note, CommandContext $context): AdvisoryCase
    {
        if (! in_array($case->state, [AdvisoryCase::NEW, AdvisoryCase::AWAITING], true)) {
            throw new DomainError('advisory_case_state', "The case is {$case->state}; only a new or waiting case can be approved.", 409, ['state' => $case->state]);
        }
        if ($planRevision !== $case->plan_revision) {
            throw new DomainError('advisory_plan_changed', 'The plan changed since you looked at it (new places were found). Reload the case and approve what it says now.', 409, ['plan_revision' => $case->plan_revision]);
        }
        $case->forceFill(['state' => AdvisoryCase::APPROVED, 'include_risky' => $includeRisky, 'decided_by' => $context->actorId, 'decided_at' => now(), 'decision_note' => $note === null ? null : mb_substr($note, 0, 1000), 'wave' => 0])->save();
        $open = AdvisoryMatch::query()->where('case_id', $case->id)->where('state', AdvisoryMatch::OPEN)->get();
        $runs = $open->filter(fn (AdvisoryMatch $m) => $m->class === 'A' || ($includeRisky && $m->class === 'B'));
        AdvisoryMatch::query()->whereIn('id', $runs->pluck('id'))->update(['state' => AdvisoryMatch::QUEUED]);
        // B places that will be updated are told first; B places that will not, and D places, are told what to do themselves
        $this->tell($case, $runs->where('class', 'B'), 'scheduled');
        $this->tell($case, $open->filter(fn (AdvisoryMatch $m) => $m->class === 'D' || (! $includeRisky && $m->class === 'B')), 'manual');

        return $this->advance($case->refresh());
    }

    public function notify(AdvisoryCase $case, ?string $note, CommandContext $context): AdvisoryCase
    {
        if (! in_array($case->state, [AdvisoryCase::NEW, AdvisoryCase::AWAITING], true)) {
            throw new DomainError('advisory_case_state', "The case is {$case->state}; customers of a running or closed case were already told.", 409, ['state' => $case->state]);
        }
        $open = AdvisoryMatch::query()->where('case_id', $case->id)->where('state', AdvisoryMatch::OPEN)->where('subject_type', 'service')->get();
        $this->tell($case, $open, 'manual');
        $case->forceFill(['state' => AdvisoryCase::PARTIAL, 'decided_by' => $context->actorId, 'decided_at' => now(), 'decision_note' => $note === null ? null : mb_substr($note, 0, 1000), 'resolved_at' => now()])->save();

        return $case;
    }

    /** One step of an approved case: settle the running wave, halt on a failure, start the next wave or finish. */
    public function advance(AdvisoryCase $case): AdvisoryCase
    {
        if ($case->state !== AdvisoryCase::APPROVED) {
            return $case;
        }
        $failed = null;
        foreach (AdvisoryMatch::query()->where('case_id', $case->id)->where('state', AdvisoryMatch::RUNNING)->get() as $match) {
            $operation = $match->operation_id ? Operation::query()->find($match->operation_id) : null;
            if ($operation?->state === Operation::SUCCEEDED) {
                $match->forceFill(['state' => AdvisoryMatch::FIXED])->save();
            } elseif ($operation === null || in_array($operation->state, [Operation::FAILED, Operation::CANCELLED], true)) {
                $match->forceFill(['state' => AdvisoryMatch::FAILED])->save();
                $failed ??= $match;
            }
        }
        if ($failed !== null) {
            return $this->halt($case, 'Oprava na '.$this->label($failed).' selhala; další vlny jsme zastavili.');
        }
        if (AdvisoryMatch::query()->where('case_id', $case->id)->where('state', AdvisoryMatch::RUNNING)->exists()) {
            return $case;
        }
        $queued = AdvisoryMatch::query()->where('case_id', $case->id)->where('state', AdvisoryMatch::QUEUED)->orderBy('created_at')->orderBy('id')->get();
        if ($queued->isEmpty()) {
            return $this->finish($case);
        }
        $wave = $case->wave + 1;
        $total = AdvisoryMatch::query()->where('case_id', $case->id)->whereIn('state', [AdvisoryMatch::QUEUED, AdvisoryMatch::RUNNING, AdvisoryMatch::FIXED, AdvisoryMatch::FAILED])->count();
        $size = match ($wave) {
            1 => max(1, (int) config('onhost.advisories.canary', 1)),
            2 => max(1, (int) ceil($total * max(1, (int) config('onhost.advisories.wave_percent', 10)) / 100)),
            default => $queued->count(),
        };
        $started = 0;
        $busy = [];
        foreach ($queued as $match) {
            if ($started >= $size || isset($busy[$match->subject_id])) {
                continue;
            }
            $service = Service::query()->find($match->subject_id);
            if ($service === null) {
                $match->forceFill(['state' => AdvisoryMatch::GONE])->save();

                continue;
            }
            try {
                $operation = $this->services->requestAction($service, (string) data_get($match->action, 'action'), CommandContext::system("security advisory case {$case->id}"), "advisory:{$case->id}:{$match->id}", (array) data_get($match->action, 'params', []));
                $match->forceFill(['state' => AdvisoryMatch::RUNNING, 'operation_id' => $operation->id])->save();
                $busy[$match->subject_id] = true;
                $started++;
            } catch (DomainError $e) {
                if (in_array($e->error, ['operation_in_progress', 'provisioning_frozen'], true)) {
                    continue; // tried again on the next step
                }
                $match->forceFill(['state' => AdvisoryMatch::SKIPPED])->save();
                $this->tell($case, collect([$match]), 'manual');
            }
        }
        if ($started > 0) {
            $case->forceFill(['wave' => $wave])->save();
        }

        return $case;
    }

    /** Draft blog post about a case whose customers were told; written once, never published by itself. */
    public function draftPost(AdvisoryCase $case, bool $again = false): ?string
    {
        if ($case->post_id !== null && ! $again) {
            return $case->post_id;
        }
        $advisory = Advisory::query()->findOrFail($case->advisory_id);
        $input = BlogDraft::for($advisory, AdvisoryMatch::query()->where('case_id', $case->id)->get());
        $input['slug'] = 'bezpecnost-'.Str::slug(mb_substr($advisory->key, 0, 60)).'-'.strtolower(Str::random(5));
        try {
            $post = $this->bus->dispatch(new ContentCommand("advisory-post:{$case->id}:".($again ? Str::ulid() : 'first'), ['op' => 'post.create', 'input' => $input]), CommandContext::system("security advisory case {$case->id}"));
        } catch (Throwable) {
            return null; // the case does not fail for a draft; staff can ask again
        }
        $case->forceFill(['post_id' => (string) ($post['id'] ?? '')])->save();

        return $case->post_id;
    }

    private function finish(AdvisoryCase $case): AdvisoryCase
    {
        $fixed = AdvisoryMatch::query()->where('case_id', $case->id)->where('state', AdvisoryMatch::FIXED)->whereNull('notified_at')->get();
        $this->tell($case, $fixed, 'fixed');
        $leftover = AdvisoryMatch::query()->where('case_id', $case->id)->where('subject_type', 'service')->whereIn('state', [AdvisoryMatch::NOTIFIED, AdvisoryMatch::FAILED, AdvisoryMatch::SKIPPED, AdvisoryMatch::OPEN])->exists();
        $case->forceFill(['state' => $leftover ? AdvisoryCase::PARTIAL : AdvisoryCase::RESOLVED, 'resolved_at' => now()])->save();
        $this->outbox->publish(GenericEvent::of('security.remediation.finished', 'advisory_case', $case->id, ['cve' => $case->advisory?->key, 'title' => $case->advisory?->title, 'state' => $case->state, 'fixed' => $fixed->count()]));

        return $case;
    }

    private function halt(AdvisoryCase $case, string $reason): AdvisoryCase
    {
        $rest = AdvisoryMatch::query()->where('case_id', $case->id)->whereIn('state', [AdvisoryMatch::QUEUED, AdvisoryMatch::FAILED])->get();
        $this->tell($case, $rest, 'halted');
        $fixed = AdvisoryMatch::query()->where('case_id', $case->id)->where('state', AdvisoryMatch::FIXED)->whereNull('notified_at')->get();
        $this->tell($case, $fixed, 'fixed');
        $case->forceFill(['state' => AdvisoryCase::HALTED, 'halted_reason' => mb_substr($reason, 0, 300), 'resolved_at' => now()])->save();
        $this->outbox->publish(GenericEvent::of('security.remediation.halted', 'advisory_case', $case->id, ['cve' => $case->advisory?->key, 'title' => $case->advisory?->title, 'reason' => $reason, 'told' => $rest->pluck('subject_id')->unique()->count()]));

        return $case;
    }

    /**
     * One customer notice per service: `scheduled` (we will update it, it may behave differently), `manual` (we cannot fix it for
     * you: what, where, what to do), `halted` (our update stopped, please check), `fixed` (updated for you).
     *
     * @param  Collection<int, AdvisoryMatch>  $matches
     */
    private function tell(AdvisoryCase $case, Collection $matches, string $outcome): void
    {
        $matches = $matches->filter(fn (AdvisoryMatch $m) => $m->subject_type === 'service' && $m->organization_id !== null);
        if ($matches->isEmpty()) {
            return;
        }
        $advisory = Advisory::query()->findOrFail($case->advisory_id);
        foreach ($matches->groupBy('subject_id') as $serviceId => $group) {
            $service = Service::query()->find((string) $serviceId);
            if ($service === null) {
                continue;
            }
            $this->outbox->publish(GenericEvent::of($outcome === 'fixed' ? 'security.advisory.service_fixed' : 'security.advisory.service_affected', 'service', $service->id, [
                'case_id' => $case->id, 'cve' => $advisory->key, 'title' => $advisory->title, 'severity' => $advisory->severity, 'exploited' => $advisory->kev, 'outcome' => $outcome,
                'site' => (string) ($service->hostname ?: $service->label ?: $service->name),
                'items' => $group->map(fn (AdvisoryMatch $m) => ['software' => self::software($m), 'version' => $m->version, 'fixed_in' => $m->fixed_in])->values()->all(),
                'link' => $advisory->present()['references'][0] ?? null,
            ], $service->organization_id));
            foreach ($group as $match) {
                $match->forceFill(['notified_at' => now(), 'state' => match (true) {
                    $outcome === 'fixed' || $outcome === 'scheduled', $match->state === AdvisoryMatch::FAILED => $match->state,
                    default => AdvisoryMatch::NOTIFIED,
                }])->save();
            }
        }
        if ($case->announced_at === null) {
            $case->forceFill(['announced_at' => now()])->save();
        }
        $this->draftPost($case);
    }

    public static function software(AdvisoryMatch $match): string
    {
        return match ($match->ecosystem) {
            'wordpress-core' => 'WordPress',
            'wordpress-plugin' => 'plugin '.$match->product,
            'wordpress-theme' => 'šablona '.$match->product,
            default => $match->product,
        };
    }

    private function label(AdvisoryMatch $match): string
    {
        $service = Service::query()->find($match->subject_id);

        return (string) ($service?->hostname ?: $service?->label ?: $match->subject_id);
    }
}
