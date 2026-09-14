<?php

declare(strict_types=1);

namespace Onhost\Domain\Compliance;

use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Onhost\Domain\Compliance\Models\AbuseCase;
use Onhost\Domain\Compliance\Models\ComplianceTimer;
use Onhost\Domain\Compliance\Models\CyberIncident;
use Onhost\Domain\Compliance\Models\DataRequest;
use Onhost\Domain\Domains\Models\Domain;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Incidents\IncidentService;
use Onhost\Domain\Invoicing\Models\Invoice;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Organizations\Models\OrganizationMembership;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Domain\Services\ServiceService;
use Onhost\Domain\Support\Models\Ticket;
use Onhost\Domain\Support\TicketService;
use Onhost\Platform\Audit\AuditEvent;
use Onhost\Platform\Audit\AuditRecorder;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Events\GenericEvent;
use Onhost\Platform\Outbox\OutboxPublisher;

/**
 * Compliance-by-design (blueprint §23, §25):
 *  - cyber incidents start the regulatory clocks (NIS2 24 h / 72 h / 1 month, GDPR 72 h, DSA Art. 18) from config `onhost.compliance.timers`;
 *  - DSA notice-and-action for abuse reports with statement of reasons, action, and a 6-month appeal window;
 *  - GDPR export / deletion and Data Act switching requests, with legal hold as a hard stop on deletion.
 */
final class ComplianceService
{
    public function __construct(
        private readonly OutboxPublisher $outbox,
        private readonly AuditRecorder $audit,
        private readonly IncidentService $incidents,
        private readonly TicketService $tickets,
        private readonly ServiceService $services,
    ) {}

    // ── cyber incidents & regulatory timers ──────────────────────────────────

    /** @param array{title:string,severity?:string,detected_at?:string,affected_services?:list<string>,jurisdictions?:list<string>,personal_data_breach?:bool,life_safety_crime_suspicion?:bool,nis2_scope?:bool,summary?:string,open_status_incident?:bool,components?:list<string>} $input */
    public function openCyberIncident(array $input, CommandContext $context): CyberIncident
    {
        $title = trim((string) ($input['title'] ?? ''));
        if ($title === '') {
            throw new DomainError('cyber_incident_title_required', 'Title is required.', 422, ['field' => 'title']);
        }
        $detectedAt = isset($input['detected_at']) ? Carbon::parse((string) $input['detected_at']) : now();
        $flags = [
            'personal_data_breach' => (bool) ($input['personal_data_breach'] ?? false),
            'life_safety_crime_suspicion' => (bool) ($input['life_safety_crime_suspicion'] ?? false),
            'nis2_scope' => (bool) ($input['nis2_scope'] ?? $this->nis2InScope()),
        ];

        $case = DB::transaction(function () use ($input, $title, $detectedAt, $flags, $context) {
            $case = $this->withNumber('cyber_incidents', 'SEC', fn (string $number) => CyberIncident::query()->create([
                'number' => $number, 'title' => $title, 'severity' => strtolower((string) ($input['severity'] ?? 'p2')), 'detected_at' => $detectedAt,
                'affected_services' => array_values((array) ($input['affected_services'] ?? [])), 'jurisdictions' => array_values((array) ($input['jurisdictions'] ?? ['CZ'])),
                'state' => 'OPEN', 'owner_id' => $context->actorId, 'summary' => $input['summary'] ?? null, 'evidence' => [],
            ] + $flags));
            if ($flags['nis2_scope']) {
                foreach (['NIS2_EARLY_WARNING', 'NIS2_NOTIFICATION', 'NIS2_FINAL_REPORT'] as $timer) {
                    $this->startTimer('cyber_incident', $case->id, $timer, $detectedAt, $context->actorId);
                }
            }
            if ($flags['personal_data_breach']) {
                $this->startTimer('cyber_incident', $case->id, 'GDPR_72H', $detectedAt, $context->actorId);
            }
            if ($flags['life_safety_crime_suspicion']) {
                $this->startTimer('cyber_incident', $case->id, 'DSA_ART18_PROMPT', $detectedAt, $context->actorId);
            }
            if ($input['open_status_incident'] ?? false) {
                $incident = $this->incidents->open([
                    'title' => $title, 'severity' => $case->severity, 'components' => (array) ($input['components'] ?? ['portal']), 'security' => true,
                    'affected_services' => $case->affected_services, 'impact' => 'Bezpečnostní incident; detaily jsou interní.', 'source' => 'manual', 'started_at' => $detectedAt->toIso8601String(),
                ], $context);
                $case->forceFill(['incident_id' => $incident->id])->save();
            }
            $this->audit->record($context, 'security.incident.open', 'succeeded', ['number' => $case->number, 'flags' => $flags, 'jurisdictions' => $case->jurisdictions], 'cyber_incident', $case->id);

            return $case;
        });
        if ($case->incident_id === null) { // otherwise IncidentService already published security.incident.opened
            $this->outbox->publish(GenericEvent::of('security.incident.opened', 'cyber_incident', $case->id, ['number' => $case->number, 'title' => $case->title, 'severity' => $case->severity, 'timers' => $case->timers()->pluck('timer')->all()]));
        }

        return $case;
    }

    /** NIS2 scope heuristic from config: DNS/registrar volume thresholds (blueprint §25.2). */
    public function nis2InScope(): bool
    {
        $domains = Domain::query()->count();

        return $domains >= (int) config('onhost.compliance.nis2_dns_warning_domains', PHP_INT_MAX);
    }

    public function transitionCyberIncident(CyberIncident $case, string $state, CommandContext $context, ?string $summary = null): CyberIncident
    {
        $state = strtoupper($state);
        if (! in_array($state, CyberIncident::STATES, true)) {
            throw new DomainError('cyber_incident_state_invalid', 'Unknown state.', 422, ['field' => 'state']);
        }
        if ($state === 'CLOSED' && ComplianceTimer::query()->where('case_type', 'cyber_incident')->where('case_id', $case->id)->where('state', 'running')->exists()) {
            throw new DomainError('cyber_incident_timers_running', 'Regulatory timers are still running; submit or waive them before closing.', 409);
        }
        $case->forceFill(['state' => $state, 'summary' => $summary ?? $case->summary])->save();
        $this->audit->record($context, 'security.incident.transition', 'succeeded', ['number' => $case->number, 'state' => $state], 'cyber_incident', $case->id);

        return $case;
    }

    /** Evidence is stored by reference (path/name) with its hash; legal hold marks it immutable. */
    public function attachEvidence(CyberIncident $case, array $evidence, CommandContext $context): CyberIncident
    {
        $entry = ['name' => (string) ($evidence['name'] ?? 'evidence'), 'sha256' => (string) ($evidence['sha256'] ?? ''), 'path' => $evidence['path'] ?? null, 'stored_at' => now()->toIso8601String(), 'legal_hold' => (bool) ($evidence['legal_hold'] ?? true), 'by' => $context->actorId];
        if (! preg_match('/^[a-f0-9]{64}$/', $entry['sha256'])) {
            throw new DomainError('evidence_hash_required', 'Evidence needs a SHA-256 hash.', 422, ['field' => 'sha256']);
        }
        $case->forceFill(['evidence' => [...($case->evidence ?? []), $entry]])->save();
        $this->audit->record($context, 'security.incident.evidence', 'succeeded', ['number' => $case->number, 'sha256' => $entry['sha256']], 'cyber_incident', $case->id);

        return $case;
    }

    public function startTimer(string $caseType, string $caseId, string $timer, Carbon $from, ?string $ownerId = null): ComplianceTimer
    {
        $hours = config("onhost.compliance.timers.{$timer}");
        if ($hours === null) {
            throw new DomainError('timer_unknown', "Unknown regulatory timer {$timer}.", 422, ['field' => 'timer']);
        }

        return ComplianceTimer::query()->firstOrCreate(
            ['case_type' => $caseType, 'case_id' => $caseId, 'timer' => $timer],
            ['owner_id' => $ownerId, 'starts_at' => $from, 'deadline_at' => $from->copy()->addHours((int) $hours), 'state' => 'running'],
        );
    }

    /** Record the submission to the authority; late submissions are still `met` but flagged. */
    public function submitTimer(ComplianceTimer $timer, string $authorityReference, array $evidence, CommandContext $context): ComplianceTimer
    {
        if ($timer->state === 'met' || $timer->state === 'waived') {
            throw new DomainError('timer_already_closed', 'Timer is already closed.', 409);
        }
        if (trim($authorityReference) === '') {
            throw new DomainError('timer_reference_required', 'Authority reference (filing id) is required.', 422, ['field' => 'authority_reference']);
        }
        $late = now() > $timer->deadline_at;
        $timer->forceFill(['state' => 'met', 'submitted_at' => now(), 'authority_reference' => $authorityReference, 'evidence' => $evidence + ['late' => $late, 'submitted_by' => $context->actorId]])->save();
        $this->audit->record($context, 'compliance.timer.submit', 'succeeded', ['timer' => $timer->timer, 'case' => $timer->case_id, 'late' => $late, 'reference' => $authorityReference], 'compliance_timer', $timer->id);

        return $timer;
    }

    public function waiveTimer(ComplianceTimer $timer, string $reason, CommandContext $context): ComplianceTimer
    {
        if (strlen(trim($reason)) < 10) {
            throw new DomainError('timer_waiver_reason', 'A waiver needs a documented legal reason (min. 10 characters).', 422, ['field' => 'reason']);
        }
        $timer->forceFill(['state' => 'waived', 'evidence' => ($timer->evidence ?? []) + ['waived_reason' => $reason, 'waived_by' => $context->actorId, 'waived_at' => now()->toIso8601String()]])->save();
        $this->audit->record($context, 'compliance.timer.waive', 'succeeded', ['timer' => $timer->timer, 'case' => $timer->case_id, 'reason' => $reason], 'compliance_timer', $timer->id);

        return $timer;
    }

    /** Scheduler: warn once at 75 % of the window, mark missed deadlines (both go to the security inbox). */
    public function tickTimers(?Carbon $now = null): array
    {
        $now ??= now();
        $warned = 0;
        $missed = 0;
        foreach (ComplianceTimer::query()->where('state', 'running')->get() as $timer) {
            $total = max(1, (int) $timer->starts_at->diffInSeconds($timer->deadline_at));
            $elapsed = (int) $timer->starts_at->diffInSeconds($now);
            if ($now > $timer->deadline_at) {
                $timer->forceFill(['state' => 'missed'])->save();
                $this->outbox->publish(GenericEvent::of('compliance.timer.missed', 'compliance_timer', $timer->id, ['timer' => $timer->timer, 'label' => $timer->label(), 'case_type' => $timer->case_type, 'case_id' => $timer->case_id, 'deadline_at' => $timer->deadline_at->toIso8601String()]));
                $missed++;
            } elseif ($timer->warned_at === null && $elapsed / $total >= 0.75) {
                $timer->forceFill(['warned_at' => $now])->save();
                $this->outbox->publish(GenericEvent::of('compliance.timer.due', 'compliance_timer', $timer->id, ['timer' => $timer->timer, 'label' => $timer->label(), 'case_type' => $timer->case_type, 'case_id' => $timer->case_id, 'deadline_at' => $timer->deadline_at->toIso8601String(), 'remaining_minutes' => (int) round($timer->remainingSeconds() / 60)]));
                $warned++;
            }
        }

        return ['warned' => $warned, 'missed' => $missed];
    }

    // ── DSA notice-and-action ────────────────────────────────────────────────

    /**
     * Public abuse report (DSA Art. 16): validates the notice, resolves the target to a service/organization,
     * acknowledges receipt, and starts the Art. 18 clock for life/safety categories.
     *
     * @param  array{reporter:array{name:string,email:string,organization?:string,trusted_flagger?:bool},category:string,allegation:string,target_url?:string,service_id?:string,jurisdiction?:string,evidence?:list<array>}  $input
     */
    public function reportAbuse(array $input, CommandContext $context): AbuseCase
    {
        $reporter = (array) ($input['reporter'] ?? []);
        if (trim((string) ($reporter['email'] ?? '')) === '' || ! filter_var($reporter['email'], FILTER_VALIDATE_EMAIL)) {
            throw new DomainError('abuse_reporter_email', 'A valid reporter e-mail is required (DSA Art. 16(2)(c)).', 422, ['field' => 'reporter.email']);
        }
        $category = (string) ($input['category'] ?? 'other');
        if (! in_array($category, AbuseCase::CATEGORIES, true)) {
            throw new DomainError('abuse_category_invalid', 'Unknown category.', 422, ['field' => 'category']);
        }
        $allegation = trim((string) ($input['allegation'] ?? ''));
        if (mb_strlen($allegation) < 20) {
            throw new DomainError('abuse_allegation_short', 'Explain why the content is illegal (min. 20 characters, DSA Art. 16(2)(a)).', 422, ['field' => 'allegation']);
        }
        $targetUrl = isset($input['target_url']) ? trim((string) $input['target_url']) : null;
        $service = isset($input['service_id']) ? Service::query()->find((string) $input['service_id']) : null;
        if ($service === null && $targetUrl !== null) {
            $service = $this->serviceForUrl($targetUrl);
        }
        if ($service === null && $targetUrl === null) {
            throw new DomainError('abuse_target_required', 'Provide the exact URL of the content (DSA Art. 16(2)(b)).', 422, ['field' => 'target_url']);
        }
        $art18 = in_array($category, AbuseCase::ART18_CATEGORIES, true);

        $case = DB::transaction(function () use ($input, $reporter, $category, $allegation, $targetUrl, $service, $art18, $context) {
            $case = $this->withNumber('abuse_cases', 'ABU', fn (string $number) => AbuseCase::query()->create([
                'number' => $number, 'reporter' => ['name' => (string) ($reporter['name'] ?? ''), 'email' => strtolower((string) $reporter['email']), 'organization' => $reporter['organization'] ?? null, 'trusted_flagger' => (bool) ($reporter['trusted_flagger'] ?? false)],
                'category' => $category, 'allegation' => $allegation, 'target_url' => $targetUrl !== null ? mb_substr($targetUrl, 0, 500) : null,
                'service_id' => $service?->id, 'organization_id' => $service?->organization_id, 'jurisdiction' => isset($input['jurisdiction']) ? strtoupper((string) $input['jurisdiction']) : null,
                'evidence' => array_values((array) ($input['evidence'] ?? [])), 'art18' => $art18, 'state' => 'RECEIVED',
            ]));
            if ($art18) {
                $this->startTimer('abuse_case', $case->id, 'DSA_ART18_PROMPT', now());
            }
            $this->audit->record($context, 'abuse.case.report', 'succeeded', ['number' => $case->number, 'category' => $category, 'art18' => $art18, 'service' => $service?->id, 'trusted_flagger' => $case->reporter['trusted_flagger']], 'abuse_case', $case->id);

            return $case;
        });
        $this->outbox->publish(GenericEvent::of('abuse.case.opened', 'abuse_case', $case->id, ['number' => $case->number, 'category' => $category, 'art18' => $art18, 'service_id' => $service?->id, 'trusted_flagger' => $case->reporter['trusted_flagger']], $service?->organization_id));

        return $case;
    }

    /** Triage: `action` proceeds to the customer notice; `no_action` dismisses with a statement of reasons for the reporter. */
    public function triageAbuse(AbuseCase $case, string $decision, string $reason, CommandContext $context): AbuseCase
    {
        if ($case->state !== 'RECEIVED') {
            throw new DomainError('abuse_not_received', 'Only new cases can be triaged.', 409);
        }
        if (! in_array($decision, ['action', 'no_action'], true) || strlen(trim($reason)) < 10) {
            throw new DomainError('abuse_decision_invalid', 'Decision must be action|no_action with a reason (min. 10 characters).', 422, ['field' => 'decision']);
        }
        $case->forceFill([
            'state' => $decision === 'action' ? 'TRIAGED' : 'DISMISSED', 'decision' => $decision, 'decision_reason' => $reason, 'handled_by' => $context->actorId,
            'decided_at' => $decision === 'no_action' ? now() : null, 'appeal_deadline_at' => $decision === 'no_action' ? now()->addMonths(6) : null,
        ])->save();
        $this->audit->record($context, 'abuse.case.triage', 'succeeded', ['number' => $case->number, 'decision' => $decision, 'reason' => $reason], 'abuse_case', $case->id);

        return $case;
    }

    /** DSA Art. 17 statement of reasons to the customer, delivered as a support ticket on their organization. */
    public function notifyCustomer(AbuseCase $case, string $statement, CommandContext $context): AbuseCase
    {
        if ($case->state !== 'TRIAGED') {
            throw new DomainError('abuse_not_triaged', 'Triage the case first.', 409);
        }
        if ($case->organization_id === null) {
            throw new DomainError('abuse_no_customer', 'The target could not be mapped to a customer; handle externally.', 409);
        }
        $organization = Organization::query()->findOrFail($case->organization_id);
        $body = "Obdrželi jsme oznámení o možném protiprávním obsahu ({$case->number}, kategorie {$case->category}).\n\n"
            .($case->target_url ? "Dotčený obsah: {$case->target_url}\n\n" : '')
            ."Odůvodnění (DSA čl. 17):\n{$statement}\n\n"
            .'Máte možnost se k oznámení vyjádřit odpovědí na tento tiket. Proti rozhodnutí lze podat interní stížnost do 6 měsíců (DSA čl. 20).';
        $ticket = $this->tickets->create([
            'subject' => "Oznámení o obsahu {$case->number}", 'body' => $body, 'category' => 'bezpecnost', 'service_id' => $case->service_id, 'channel' => 'abuse', 'email' => $organization->billing_email,
        ], $context->withScope($organization->id), $organization, null, true);
        $case->forceFill(['state' => 'CUSTOMER_NOTIFIED', 'customer_notified_at' => now(), 'ticket_id' => $ticket->id])->save();
        $this->audit->record($context->withScope($organization->id), 'abuse.case.notify_customer', 'succeeded', ['number' => $case->number, 'ticket' => $ticket->number], 'abuse_case', $case->id);

        return $case;
    }

    /** Apply the decided action; `service_suspended` runs the regular suspend saga with an abuse reason. */
    public function actionAbuse(AbuseCase $case, string $action, string $reason, CommandContext $context): AbuseCase
    {
        if (! in_array($case->state, ['TRIAGED', 'CUSTOMER_NOTIFIED', 'APPEALED'], true)) {
            throw new DomainError('abuse_not_actionable', 'Case is not in an actionable state.', 409);
        }
        if (! in_array($action, ['content_removed', 'service_suspended', 'warning', 'none'], true)) {
            throw new DomainError('abuse_action_invalid', 'Unknown action.', 422, ['field' => 'action']);
        }
        if ($action === 'service_suspended') {
            $service = Service::query()->find($case->service_id ?? '');
            if ($service === null) {
                throw new DomainError('abuse_service_missing', 'No service is linked to this case.', 409);
            }
            if ($service->state !== ServiceStateMachine::SUSPENDED && $service->state !== ServiceStateMachine::SUSPENDING) {
                $this->services->requestAction($service, 'suspend', $context->withScope($service->organization_id), "abuse:{$case->number}:suspend", ['reason' => "abuse:{$case->number}"]);
            }
        }
        $case->forceFill(['state' => 'ACTIONED', 'action_taken' => $action, 'decision' => 'action', 'decision_reason' => $reason, 'decided_at' => now(), 'appeal_deadline_at' => now()->addMonths(6), 'handled_by' => $context->actorId])->save();
        if ($case->ticket_id !== null) {
            $ticket = Ticket::query()->find($case->ticket_id);
            if ($ticket !== null) {
                $this->tickets->reply($ticket, 'staff', $context->actorId, 'Trust & Safety', "Rozhodnutí k {$case->number}: {$this->actionLabel($action)}.\n\nOdůvodnění: {$reason}\n\nProti rozhodnutí lze podat stížnost odpovědí na tento tiket do ".now()->addMonths(6)->format('d.m.Y').'.', $context->withScope($case->organization_id));
            }
        }
        $this->audit->record($context, 'abuse.case.action', 'succeeded', ['number' => $case->number, 'action' => $action, 'reason' => $reason], 'abuse_case', $case->id);
        $this->outbox->publish(GenericEvent::of('abuse.case.actioned', 'abuse_case', $case->id, ['number' => $case->number, 'action' => $action], $case->organization_id));

        return $case;
    }

    /** Customer complaint (DSA Art. 20) within the appeal window. */
    public function appealAbuse(AbuseCase $case, string $text, CommandContext $context): AbuseCase
    {
        if (! in_array($case->state, ['ACTIONED', 'DISMISSED'], true)) {
            throw new DomainError('abuse_not_appealable', 'Only decided cases can be appealed.', 409);
        }
        if ($case->appeal_deadline_at !== null && now() > $case->appeal_deadline_at) {
            throw new DomainError('abuse_appeal_expired', 'The 6-month complaint window has passed.', 409);
        }
        if (mb_strlen(trim($text)) < 20) {
            throw new DomainError('abuse_appeal_short', 'Describe the complaint (min. 20 characters).', 422, ['field' => 'text']);
        }
        $case->forceFill(['state' => 'APPEALED', 'evidence' => [...($case->evidence ?? []), ['kind' => 'appeal', 'text' => $text, 'at' => now()->toIso8601String(), 'by' => $context->actorId]]])->save();
        $this->audit->record($context, 'abuse.case.appeal', 'succeeded', ['number' => $case->number], 'abuse_case', $case->id);

        return $case;
    }

    public function closeAbuse(AbuseCase $case, CommandContext $context, ?string $note = null): AbuseCase
    {
        if (! in_array($case->state, ['ACTIONED', 'DISMISSED', 'APPEALED'], true)) {
            throw new DomainError('abuse_not_closable', 'Decide the case before closing it.', 409);
        }
        $case->forceFill(['state' => 'CLOSED'])->save();
        $this->audit->record($context, 'abuse.case.close', 'succeeded', ['number' => $case->number, 'note' => $note], 'abuse_case', $case->id);

        return $case;
    }

    private function actionLabel(string $action): string
    {
        return match ($action) {
            'content_removed' => 'obsah byl odstraněn', 'service_suspended' => 'služba byla pozastavena', 'warning' => 'upozornění bez dalšího zásahu', default => 'bez zásahu',
        };
    }

    /** Map a URL host to a hosted service via service hostname or a customer domain. */
    private function serviceForUrl(string $url): ?Service
    {
        $host = strtolower((string) parse_url(str_contains($url, '://') ? $url : "https://{$url}", PHP_URL_HOST));
        if ($host === '') {
            return null;
        }
        $candidates = [$host];
        while (substr_count($host, '.') >= 2) {
            $host = substr($host, strpos($host, '.') + 1);
            $candidates[] = $host;
        }
        $service = Service::query()->whereIn('hostname', $candidates)->whereNotIn('state', [ServiceStateMachine::TERMINATED])->first();
        if ($service !== null) {
            return $service;
        }
        $domain = Domain::query()->whereIn('fqdn_ascii', $candidates)->first();
        if ($domain === null) {
            return null;
        }

        return Service::query()->where('organization_id', $domain->organization_id)->whereIn('family', ['web', 'managed', 'apps', 'cloud'])->whereNotIn('state', [ServiceStateMachine::TERMINATED])->orderBy('created_at')->first();
    }

    // ── GDPR / Data Act requests ─────────────────────────────────────────────

    public function requestData(Organization $organization, string $kind, CommandContext $context, ?string $reason = null): DataRequest
    {
        if (! in_array($kind, DataRequest::KINDS, true)) {
            throw new DomainError('data_request_kind_invalid', 'Kind must be export, deletion or switching.', 422, ['field' => 'kind']);
        }
        if (DataRequest::query()->where('organization_id', $organization->id)->where('kind', $kind)->whereIn('state', ['requested', 'processing'])->exists()) {
            throw new DomainError('data_request_pending', 'A request of this kind is already in progress.', 409);
        }
        if ($kind === 'deletion') {
            $this->assertDeletable($organization);
        }
        $request = DataRequest::query()->create(['organization_id' => $organization->id, 'kind' => $kind, 'state' => 'requested', 'requested_by' => $context->actorId, 'reason' => $reason, 'meta' => []]);
        if ($kind === 'switching') {
            $this->startTimer('data_request', $request->id, 'DATA_ACT_SWITCHING', now(), null);
        }
        $this->audit->record($context->withScope($organization->id), "compliance.data_request.{$kind}", 'succeeded', ['request' => $request->id, 'reason' => $reason], 'data_request', $request->id);

        return $request;
    }

    /**
     * A signed download link (audit §5j-7) the customer may hand to an auditor or the next provider without an account:
     * valid seven days at most (never past the export's own expiry), one active link per export — issuing a new one
     * revokes the previous, the token is stored hashed.
     *
     * @return array{url:string, expires_at:string}
     */
    public function downloadLink(DataRequest $request, CommandContext $context): array
    {
        if ($request->state !== 'ready' || $request->file_path === null || ($request->expires_at !== null && $request->expires_at < now())) {
            throw new DomainError('data_export_not_ready', 'The export is not ready or has expired.', 409, ['state' => $request->state]);
        }
        $token = Str::random(40);
        $expires = now()->addDays(7);
        if ($request->expires_at !== null && $request->expires_at->lt($expires)) {
            $expires = $request->expires_at->copy();
        }
        $request->forceFill(['meta' => array_merge((array) $request->meta, ['link_hash' => hash('sha256', $token), 'link_expires_at' => $expires->toIso8601String(), 'link_issued_by' => $context->actorId, 'link_issued_at' => now()->toIso8601String()])])->save();
        $this->audit->record($context->withScope($request->organization_id), 'compliance.data_export.link', 'succeeded', ['request' => $request->id, 'expires_at' => $expires->toIso8601String()], 'data_request', $request->id);

        return ['url' => URL::temporarySignedRoute('data-export.download', $expires, ['dataRequest' => $request->id, 'token' => $token]), 'expires_at' => $expires->toIso8601String()];
    }

    /** Resolves a signed link to the export file; anything stale, revoked or mismatched is refused. */
    public function redeemLink(DataRequest $request, string $token): string
    {
        $hash = (string) data_get($request->meta, 'link_hash', '');
        $until = data_get($request->meta, 'link_expires_at');
        if ($hash === '' || ! hash_equals($hash, hash('sha256', $token)) || ($until !== null && Carbon::parse((string) $until) < now()) || $request->state !== 'ready' || $request->file_path === null) {
            throw new DomainError('data_export_link_invalid', 'This download link is no longer valid.', 410);
        }

        return $request->file_path;
    }

    /** Deletion is refused while services are live, invoices are open, or a legal hold applies (blueprint §23.4). */
    public function assertDeletable(Organization $organization): void
    {
        $blocks = [];
        if (Service::query()->where('organization_id', $organization->id)->whereNotIn('state', [ServiceStateMachine::TERMINATED])->exists()) {
            $blocks[] = 'active_services';
        }
        if (Service::query()->withTrashed()->where('organization_id', $organization->id)->where('legal_hold', true)->exists() || ($organization->settings['legal_hold'] ?? false)) {
            $blocks[] = 'legal_hold';
        }
        if (Invoice::query()->where('organization_id', $organization->id)->whereIn('state', [Invoice::ISSUED])->where('type', 'invoice')->exists()) {
            $blocks[] = 'open_invoices';
        }
        if ($blocks !== []) {
            throw new DomainError('deletion_blocked', 'Deletion is blocked: '.implode(', ', $blocks), 409, ['blocks' => $blocks]);
        }
    }

    public function setLegalHold(Organization $organization, bool $hold, string $reason, CommandContext $context): Organization
    {
        if (strlen(trim($reason)) < 10) {
            throw new DomainError('legal_hold_reason', 'Legal hold needs a documented reason.', 422, ['field' => 'reason']);
        }
        $settings = $organization->settings ?? [];
        $settings['legal_hold'] = $hold;
        $settings['legal_hold_reason'] = $hold ? $reason : null;
        $organization->forceFill(['settings' => $settings])->save();
        Service::query()->withTrashed()->where('organization_id', $organization->id)->update(['legal_hold' => $hold]);
        $this->audit->record($context->withScope($organization->id), $hold ? 'compliance.legal_hold.apply' : 'compliance.legal_hold.lift', 'succeeded', ['reason' => $reason], 'organization', $organization->id);

        return $organization;
    }

    /** Scheduler: build exports (JSON archive on the private disk), execute deletions, expire old exports. */
    public function processDataRequests(?Carbon $now = null): array
    {
        $now ??= now();
        $stats = ['exported' => 0, 'deleted' => 0, 'expired' => 0, 'switching' => 0];
        foreach (DataRequest::query()->where('state', 'requested')->orderBy('created_at')->get() as $request) {
            $request->forceFill(['state' => 'processing'])->save();
            $organization = Organization::query()->find($request->organization_id);
            if ($organization === null) {
                $request->forceFill(['state' => 'rejected', 'meta' => ['reason' => 'organization_missing']])->save();

                continue;
            }
            match ($request->kind) {
                'export', 'switching' => $this->buildExport($request, $organization, $now, $stats),
                'deletion' => $this->executeDeletion($request, $organization, $now, $stats),
            };
        }
        foreach (DataRequest::query()->where('state', 'ready')->where('expires_at', '<', $now)->get() as $request) {
            if ($request->file_path !== null) {
                Storage::disk('local')->delete($request->file_path);
            }
            $request->forceFill(['state' => 'completed', 'completed_at' => $now, 'file_path' => null])->save();
            $stats['expired']++;
        }

        return $stats;
    }

    private function buildExport(DataRequest $request, Organization $organization, Carbon $now, array &$stats): void
    {
        $members = OrganizationMembership::query()->where('organization_id', $organization->id)->get();
        $users = User::query()->whereIn('id', $members->pluck('user_id'))->get(['id', 'email', 'name', 'locale', 'timezone', 'created_at']);
        $archive = [
            'generated_at' => $now->toIso8601String(), 'format' => 'onhost-export/1', 'kind' => $request->kind,
            'organization' => $organization->only(['id', 'name', 'type', 'ico', 'dic', 'vat_id', 'billing_email', 'street', 'city', 'postal_code', 'country', 'currency', 'locale', 'billing_mode', 'created_at']),
            'members' => $members->map(fn (OrganizationMembership $m) => ['user_id' => $m->user_id, 'role' => $m->role_key, 'joined_at' => ($m->joined_at ?? $m->created_at)?->toIso8601String()])->all(),
            'users' => $users->map(fn (User $u) => $u->only(['id', 'email', 'name', 'locale', 'timezone', 'created_at']))->all(),
            'services' => Service::query()->withTrashed()->where('organization_id', $organization->id)->get()->map(fn (Service $s) => $s->only(['id', 'product_key', 'family', 'name', 'hostname', 'state', 'region_code', 'desired_spec', 'entitlements', 'sla_class', 'activated_at', 'terminated_at']))->all(),
            'domains' => Domain::query()->where('organization_id', $organization->id)->get()->map(fn (Domain $d) => $d->only(['id', 'fqdn_ascii', 'fqdn_unicode', 'tld', 'state', 'expires_at', 'nameservers']))->all(),
            'invoices' => Invoice::query()->where('organization_id', $organization->id)->get()->map(fn (Invoice $i) => $i->only(['id', 'number', 'type', 'state', 'currency', 'total_minor', 'issued_at', 'due_at', 'paid_at']))->all(),
            'tickets' => Ticket::query()->where('organization_id', $organization->id)->get()->map(fn (Ticket $t) => $t->only(['id', 'number', 'subject', 'state', 'priority', 'created_at']))->all(),
            // the audit trail of the account (audit §5j-7): who did what, when, from where — the last 5 000 rows
            'audit' => AuditEvent::query()->where('organization_id', $organization->id)->orderByDesc('created_at')->limit(5000)->get(['id', 'actor_type', 'actor_id', 'action', 'result', 'resource_type', 'resource_id', 'ip', 'created_at'])->map(fn (AuditEvent $e) => $e->only(['id', 'actor_type', 'actor_id', 'action', 'result', 'resource_type', 'resource_id', 'ip', 'created_at']))->all(),
        ];
        if ($request->kind === 'switching') { // Data Act: exportable formats + DNS zone and service specs for the receiving provider
            $archive['portability'] = ['dns_zones' => 'GET /v1/domains/{zone}/zone?format=bind', 'backups' => 'GET /v1/services/{service}/backups', 'formats' => ['json', 'bind', 'ubl-2.1']];
        }
        $path = "exports/{$organization->id}/{$request->id}.json";
        Storage::disk('local')->put($path, json_encode($archive, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $days = (int) config('onhost.compliance.data_export_grace_days', 30);
        $request->forceFill(['state' => 'ready', 'file_path' => $path, 'ready_at' => $now, 'expires_at' => $now->copy()->addDays($days), 'meta' => ['bytes' => Storage::disk('local')->size($path), 'counts' => ['services' => count($archive['services']), 'domains' => count($archive['domains']), 'invoices' => count($archive['invoices']), 'tickets' => count($archive['tickets'])]]])->save();
        $this->outbox->publish(GenericEvent::of('compliance.data_export.ready', 'data_request', $request->id, ['kind' => $request->kind, 'days' => $days, 'bytes' => $request->meta['bytes']], $organization->id));
        $stats[$request->kind === 'switching' ? 'switching' : 'exported']++;
    }

    /** Anonymise PII while keeping tax documents (10-year retention) and the hash-chained audit trail. */
    private function executeDeletion(DataRequest $request, Organization $organization, Carbon $now, array &$stats): void
    {
        try {
            $this->assertDeletable($organization);
        } catch (DomainError $e) {
            $request->forceFill(['state' => 'rejected', 'meta' => ['reason' => $e->error, 'blocks' => $e->extra['blocks'] ?? []]])->save();

            return;
        }
        DB::transaction(function () use ($organization, $now): void {
            $members = OrganizationMembership::query()->where('organization_id', $organization->id)->pluck('user_id');
            foreach (User::query()->whereIn('id', $members)->get() as $user) {
                if (OrganizationMembership::query()->where('user_id', $user->id)->where('organization_id', '!=', $organization->id)->exists() || $user->is_staff) {
                    continue; // user still belongs to another organization
                }
                $user->tokens()->delete();
                $user->forceFill(['email' => "deleted+{$user->id}@invalid.onhost", 'name' => 'Smazaný uživatel', 'password' => Str::random(48), 'state' => 'deleted', 'totp_secret' => null, 'recovery_codes' => null, 'remember_token' => null])->save();
            }
            $organization->forceFill(['name' => 'Smazaná organizace', 'billing_email' => "deleted+{$organization->id}@invalid.onhost", 'street' => null, 'city' => null, 'postal_code' => null, 'ico' => null, 'dic' => null, 'vat_id' => null, 'state' => 'closed', 'closed_at' => $now])->save();
        });
        $request->forceFill(['state' => 'completed', 'completed_at' => $now, 'meta' => ['anonymised' => ['organization', 'users'], 'retained' => ['invoices', 'ledger', 'audit']]])->save();
        $this->audit->record(CommandContext::system('gdpr.deletion')->withScope($organization->id), 'compliance.data_request.deleted', 'succeeded', ['request' => $request->id], 'organization', $organization->id);
        $stats['deleted']++;
    }

    private function withNumber(string $table, string $prefix, callable $create): mixed
    {
        $year = now()->format('Y');
        $offset = strlen($prefix) + 7; // "ABU-2026-" → substr from position 10 (1-based)
        for ($i = 0; $i < 5; $i++) {
            $last = (int) DB::table($table)->where('number', 'like', "{$prefix}-{$year}-%")->selectRaw("max(cast(substr(number, {$offset}) as integer)) as n")->value('n');
            try {
                return $create(sprintf('%s-%s-%04d', $prefix, $year, $last + 1 + $i));
            } catch (QueryException $e) {
                if (! str_contains(strtolower($e->getMessage()), 'unique')) {
                    throw $e;
                }
            }
        }
        throw new DomainError('number_conflict', "Could not allocate a {$prefix} number.", 500);
    }
}
