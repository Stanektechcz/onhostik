<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;
use Onhost\Domain\Compliance\ComplianceService;
use Onhost\Domain\Compliance\Models\AbuseCase;
use Onhost\Domain\Compliance\Models\ComplianceTimer;
use Onhost\Domain\Compliance\Models\DataRequest;
use Onhost\Domain\Identity\Authorization\Models\PolicyBinding;
use Onhost\Domain\Identity\StepUp\StepUpService;
use Onhost\Domain\Notifications\Models\MailOutbox;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Organizations\Models\OrganizationMembership;
use Onhost\Domain\Services\FinalArchive;
use Onhost\Domain\Services\Models\Backup;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Domain\Support\Models\Ticket;
use Onhost\Domain\Support\Models\TicketMessage;
use Onhost\Platform\Audit\AuditEvent;
use Onhost\Platform\Outbox\OutboxMessage;
use Onhost\Platform\Outbox\OutboxPublisher;

function complianceWebService(Organization $org): Service
{
    return Service::query()->create(['organization_id' => $org->id, 'product_key' => 'web-hosting', 'family' => 'web', 'name' => 'Webhosting Start', 'hostname' => 'shop.cz', 'state' => ServiceStateMachine::ACTIVE, 'region_code' => 'cz1', 'entitlements' => [], 'desired_spec' => ['executor' => 'ispconfig'], 'sla_class' => 'standard', 'activated_at' => now()->subMonth()]);
}

it('starts NIS2 and GDPR clocks for a cyber incident, warns at 75 %, marks missed deadlines and records filings', function () {
    $this->actingAs($this->staff('sre'), 'sanctum');
    $this->postJson('/v1/staff/security/incidents', ['title' => 'x'])->assertForbidden();

    $this->actingAs($this->staff('security_soc'), 'sanctum');
    $opened = $this->postJson('/v1/staff/security/incidents', ['title' => 'Únik přihlašovacích údajů z podpory', 'severity' => 'p1', 'nis2_scope' => true, 'personal_data_breach' => true, 'jurisdictions' => ['CZ']])
        ->assertCreated()->assertJsonPath('number', 'SEC-'.now()->format('Y').'-0001')->assertJsonPath('state', 'OPEN');
    $timers = collect($opened->json('timers'));
    expect($timers)->toHaveCount(4)->and($timers->pluck('timer')->sort()->values()->all())->toBe(['GDPR_72H', 'NIS2_EARLY_WARNING', 'NIS2_FINAL_REPORT', 'NIS2_NOTIFICATION']);
    expect(OutboxMessage::query()->where('name', 'security.incident.opened')->exists())->toBeTrue();

    $compliance = app(ComplianceService::class);
    expect($compliance->tickTimers(now()->addHours(19)))->toBe(['warned' => 1, 'missed' => 0]); // 24 h clock is 79 % elapsed
    expect(OutboxMessage::query()->where('name', 'compliance.timer.due')->exists())->toBeTrue();
    expect($compliance->tickTimers(now()->addHours(25)))->toBe(['warned' => 0, 'missed' => 1]);
    expect(ComplianceTimer::query()->where('timer', 'NIS2_EARLY_WARNING')->value('state'))->toBe('missed');

    $gdpr = ComplianceTimer::query()->where('timer', 'GDPR_72H')->firstOrFail();
    $this->postJson("/v1/staff/compliance/timers/{$gdpr->id}/submit", ['authority_reference' => 'ÚOOÚ-2026-0417'])->assertForbidden(); // SOC contains, legal files
    $this->actingAs($this->staff('compliance_legal'), 'sanctum');
    $this->postJson("/v1/staff/compliance/timers/{$gdpr->id}/submit", ['authority_reference' => 'ÚOOÚ-2026-0417'])->assertOk()->assertJsonPath('state', 'met');

    $this->actingAs($this->staff('security_soc'), 'sanctum');
    $this->postJson("/v1/staff/security/incidents/{$opened->json('id')}/transition", ['state' => 'CLOSED'])->assertStatus(409)->assertJsonPath('error', 'cyber_incident_timers_running');
    $this->postJson("/v1/staff/security/incidents/{$opened->json('id')}/transition", ['state' => 'CONTAINED'])->assertOk()->assertJsonPath('state', 'CONTAINED');
    $this->postJson("/v1/staff/security/incidents/{$opened->json('id')}/evidence", ['name' => 'auth.log', 'sha256' => str_repeat('a', 64)])->assertCreated();
    expect($this->getJson('/v1/staff/compliance/timers?state=running')->assertOk()->json('data'))->toHaveCount(2);
});

it('handles a DSA abuse notice: acknowledgement, triage, statement of reasons to the customer, action, and the customer appeal', function () {
    [$customer, $org] = $this->customerWithOrganization();
    $service = complianceWebService($org);
    $notice = ['reporter' => ['name' => 'Jana Nováková', 'email' => 'jana@example.org'], 'category' => 'phishing', 'allegation' => 'Stránka napodobuje přihlášení do internetového bankovnictví a sbírá hesla.', 'target_url' => 'https://shop.cz/login', 'good_faith' => true];

    $this->postJson('/v1/abuse/reports', array_diff_key($notice, ['good_faith' => 1]))->assertUnprocessable()->assertJsonValidationErrors(['good_faith']);
    $received = $this->postJson('/v1/abuse/reports', $notice)->assertCreated()->assertJsonPath('data.number', 'ABU-'.now()->format('Y').'-0001')->assertJsonPath('data.state', 'RECEIVED');
    $case = AbuseCase::query()->where('number', $received->json('data.number'))->firstOrFail();
    expect($case->service_id)->toBe($service->id)->and($case->organization_id)->toBe($org->id)->and($case->art18)->toBeFalse();
    expect(OutboxMessage::query()->where('name', 'abuse.case.opened')->exists())->toBeTrue();

    $this->actingAs($this->staff('abuse_trust_safety'), 'sanctum');
    $this->postJson("/v1/staff/abuse-cases/{$case->id}/triage", ['decision' => 'action', 'reason' => 'Potvrzený phishing (screenshot, VirusTotal).'])->assertOk()->assertJsonPath('state', 'TRIAGED');
    $this->postJson("/v1/staff/abuse-cases/{$case->id}/notify", ['statement' => 'Obsah na /login napodobuje bankovní přihlášení; jde o phishing dle § 230 TZ.'])->assertOk()->assertJsonPath('state', 'CUSTOMER_NOTIFIED');
    $ticket = Ticket::query()->where('organization_id', $org->id)->firstOrFail();
    expect($ticket->subject)->toBe("Oznámení o obsahu {$case->number}")->and($ticket->channel)->toBe('abuse');

    $soc = $this->staff('abuse_trust_safety');
    $this->actingAs($soc, 'sanctum');
    $this->postJson("/v1/staff/abuse-cases/{$case->id}/action", ['action' => 'service_suspended', 'reason' => 'Aktivní phishing ohrožuje třetí osoby.'])->assertForbidden()->assertJsonPath('error', 'step_up_required');
    $this->postJson("/v1/staff/abuse-cases/{$case->id}/action", ['action' => 'content_removed', 'reason' => 'Zákazník phishingovou stránku odstranil, ověřeno.'])->assertOk()->assertJsonPath('state', 'ACTIONED')->assertJsonPath('action_taken', 'content_removed');
    expect(TicketMessage::query()->where('ticket_id', $ticket->id)->where('author_type', 'staff')->exists())->toBeTrue();

    $this->actingAs($customer, 'sanctum');
    $list = $this->withHeader('X-Organization', $org->id)->getJson('/v1/abuse-cases')->assertOk()->assertHeader('X-Total-Count', '1');
    expect($list->json('data.0.reporter'))->toBeNull()->and($list->json('data.0.decision_reason'))->toContain('ověřeno');
    $this->withHeader('X-Organization', $org->id)->postJson("/v1/abuse-cases/{$case->number}/appeal", ['text' => 'Stránka byla legitimní testovací kopie našeho vlastního e-shopu, nikoli banky.'])->assertOk()->assertJsonPath('data.state', 'APPEALED');
});

it('builds GDPR data exports and blocks deletion while services are live or a legal hold applies', function () {
    Storage::fake('local');
    [$customer, $org] = $this->customerWithOrganization();
    $service = complianceWebService($org);
    $this->actingAs($customer, 'sanctum');
    $headers = ['X-Organization' => $org->id];

    $export = $this->withHeaders($headers)->postJson('/v1/data-requests', ['kind' => 'export'])->assertStatus(202)->assertJsonPath('data.state', 'requested');
    $this->withHeaders($headers)->postJson('/v1/data-requests', ['kind' => 'export'])->assertStatus(409); // one at a time
    $this->withHeaders($headers)->getJson("/v1/data-requests/{$export->json('data.id')}/download")->assertStatus(409);

    expect(app(ComplianceService::class)->processDataRequests()['exported'])->toBe(1);
    $request = DataRequest::query()->findOrFail($export->json('data.id'));
    expect($request->state)->toBe('ready')->and($request->meta['counts']['services'])->toBe(1);
    Storage::disk('local')->assertExists($request->file_path);
    app(OutboxPublisher::class)->relayPending();
    expect(MailOutbox::query()->where('template_key', 'data-export')->where('to', $customer->email)->exists())->toBeTrue();
    $download = $this->withHeaders($headers)->get("/v1/data-requests/{$request->id}/download")->assertOk();
    expect($download->streamedContent())->toContain('"format": "onhost-export/1"')->toContain('Test s.r.o.');
    expect(AuditEvent::query()->where('action', 'compliance.data_export.download')->where('actor_id', $customer->id)->count())->toBe(1); // who took the archive is written down

    // The archive holds every member's identity, the billing data and thousands of audit rows. Asking for it needs
    // `organization.manage`; downloading it needed nothing — a read-only member could, and so could any staff account that may
    // merely READ customers (it reaches the organization through X-Organization).
    $viewer = $this->customer(['email' => 'viewer@example.cz']);
    PolicyBinding::query()->create(['principal_type' => 'user', 'principal_id' => $viewer->id, 'role_key' => 'viewer', 'scope_type' => 'organization', 'scope_id' => $org->id, 'organization_id' => $org->id]);
    OrganizationMembership::query()->create(['organization_id' => $org->id, 'user_id' => $viewer->id, 'state' => 'active', 'role_key' => 'viewer', 'joined_at' => now()]);
    $this->actingAs($viewer, 'sanctum')->withHeaders($headers)->get("/v1/data-requests/{$request->id}/download")->assertForbidden();
    $this->actingAs($this->staff('support_l1'), 'sanctum')->withHeaders($headers)->get("/v1/data-requests/{$request->id}/download")->assertForbidden();
    expect(AuditEvent::query()->where('action', 'compliance.data_export.download')->count())->toBe(1);
    $this->actingAs($customer, 'sanctum');

    $this->withHeaders($headers)->postJson('/v1/data-requests', ['kind' => 'deletion'])->assertStatus(409)->assertJsonPath('error', 'deletion_blocked')->assertJsonPath('blocks.0', 'active_services');
    $service->forceFill(['state' => ServiceStateMachine::TERMINATED, 'terminated_at' => now()])->save();

    $legal = $this->staff('compliance_legal');
    $this->actingAs($legal, 'sanctum');
    $this->postJson("/v1/staff/customers/{$org->id}/legal-hold", ['hold' => true, 'reason' => 'Žádost PČR č. j. KRPA-1234/2026'])->assertForbidden()->assertJsonPath('error', 'step_up_required');
    app(StepUpService::class)->grant($legal, 'totp', null, '127.0.0.1');
    $this->postJson("/v1/staff/customers/{$org->id}/legal-hold", ['hold' => true, 'reason' => 'Žádost PČR č. j. KRPA-1234/2026'])->assertOk()->assertJsonPath('legal_hold', true);

    $this->actingAs($customer, 'sanctum');
    $this->withHeaders($headers)->postJson('/v1/data-requests', ['kind' => 'deletion'])->assertStatus(409)->assertJsonPath('blocks.0', 'legal_hold');

    $this->actingAs($legal, 'sanctum');
    $this->postJson("/v1/staff/customers/{$org->id}/legal-hold", ['hold' => false, 'reason' => 'Řízení ukončeno, hold zrušen.'])->assertOk()->assertJsonPath('legal_hold', false);
    $this->actingAs($customer, 'sanctum');
    $this->withHeaders($headers)->postJson('/v1/data-requests', ['kind' => 'deletion'])->assertStatus(202);
    expect(app(ComplianceService::class)->processDataRequests()['deleted'])->toBe(1);
    expect($org->fresh()->name)->toBe('Smazaná organizace')->and($customer->fresh()->email)->toStartWith('deleted+')->and($customer->fresh()->state)->toBe('deleted');
});

it('waits out the restore window of a cancelled service and erases its archive with the organization (audit §5ab)', function () {
    Storage::fake('local');
    [$customer, $org] = $this->customerWithOrganization();
    $service = Service::query()->create(['organization_id' => $org->id, 'product_key' => 'web-hosting', 'family' => 'web', 'name' => 'Webhosting', 'state' => ServiceStateMachine::SUSPENDED,
        'region_code' => 'cz1', 'entitlements' => [], 'desired_spec' => [], 'sla_class' => 'standard', 'terminate_at' => now()->addDays(30), 'tags' => ['deletion' => ['grace_days' => 30]]]);
    $set = FinalArchive::PREFIX.'/'.$org->id.'/'.$service->id.'-20260901-120000';
    Storage::disk('local')->put($set.'/service.json', '{"service":{}}');
    Storage::disk('local')->put($set.'/site-files.tar.gz', str_repeat('data', 100));
    $archive = Backup::query()->create(['service_id' => $service->id, 'organization_id' => $org->id, 'kind' => 'final', 'state' => 'completed', 'protected' => true,
        'started_at' => now(), 'finished_at' => now(), 'size_bytes' => 400, 'retention_until' => now()->addDays(60), 'meta' => ['set' => $set, 'family' => 'web']]);
    $request = DataRequest::query()->create(['organization_id' => $org->id, 'requested_by' => $customer->id, 'kind' => 'deletion', 'state' => 'requested', 'meta' => []]);

    // the service is cancelled but still restorable: the erasure waits instead of being rejected
    app(ComplianceService::class)->processDataRequests();
    $request->refresh();
    expect($request->state)->toBe('requested')->and($request->meta['waiting_for'])->toBe('pending_deletion')->and($request->meta['retry_after'])->not->toBeNull();
    expect(Storage::disk('local')->exists($set.'/site-files.tar.gz'))->toBeTrue();

    // once the service is really gone, the erasure completes and takes the archive with it
    $service->forceFill(['state' => ServiceStateMachine::TERMINATED, 'terminated_at' => now(), 'terminate_at' => null])->save();
    expect(app(ComplianceService::class)->processDataRequests()['deleted'])->toBe(1);
    expect(Storage::disk('local')->exists($set.'/site-files.tar.gz'))->toBeFalse()
        ->and(Backup::query()->findOrFail($archive->id)->state)->toBe('purged')
        ->and(Backup::query()->findOrFail($archive->id)->protected)->toBeFalse()
        ->and($request->refresh()->meta['archives_erased'])->toBe(1);
});
