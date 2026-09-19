<?php

declare(strict_types=1);

use Onhost\Domain\Identity\StepUp\StepUpService;
use Onhost\Domain\Incidents\MaintenanceService;
use Onhost\Domain\Incidents\Models\Incident;
use Onhost\Domain\Incidents\Models\Maintenance;
use Onhost\Domain\Incidents\Models\StatusComponent;
use Onhost\Domain\Notifications\Models\MailOutbox;
use Onhost\Domain\Notifications\Models\Notification;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Platform\Audit\AuditEvent;
use Onhost\Platform\Outbox\OutboxMessage;
use Onhost\Platform\Outbox\OutboxPublisher;

function incidentWebService(Organization $org, array $overrides = []): Service
{
    return Service::query()->create(array_merge(['organization_id' => $org->id, 'product_key' => 'web-hosting', 'family' => 'web', 'name' => 'Webhosting Start', 'hostname' => 'shop.cz', 'state' => ServiceStateMachine::ACTIVE, 'region_code' => 'cz1', 'entitlements' => [], 'desired_spec' => ['executor' => 'ispconfig'], 'sla_class' => 'standard', 'activated_at' => now()->subMonth()], $overrides));
}

it('runs an incident through the public status page: open → updates → resolve → post-mortem, notifying affected customers', function () {
    [$customer, $org] = $this->customerWithOrganization();
    $service = incidentWebService($org);
    $sre = $this->staff('sre');

    $this->actingAs($customer, 'sanctum');
    $this->postJson('/v1/staff/incidents', ['title' => 'x', 'severity' => 'p1', 'components' => ['web-cz1']])->assertForbidden();

    $this->actingAs($sre, 'sanctum');
    $opened = $this->postJson('/v1/staff/incidents', ['title' => 'Výpadek webhostingu CZ1', 'severity' => 'p1', 'components' => ['web-cz1'], 'impact' => 'Weby na shared01 nedostupné', 'affected_services' => [$service->id]])
        ->assertCreated()->assertJsonPath('number', 'INC-'.now()->format('Y').'-0001')->assertJsonPath('state', 'DETECTED')->assertJsonPath('ui_state', 'vysetrovani');
    $id = $opened->json('id');
    expect(Incident::query()->find($id)->affected_organizations)->toBe([$org->id]);

    // customers of affected services are notified (mandatory kind `incident.affecting` → in-app + mail)
    app(OutboxPublisher::class)->relayPending();
    expect(Notification::query()->where('audience', 'customer')->where('organization_id', $org->id)->where('kind', 'incident.affecting')->exists())->toBeTrue()
        ->and(MailOutbox::query()->where('template_key', 'incident')->where('to', $customer->email)->exists())->toBeTrue();

    // public status page reflects the worst open incident
    $status = $this->getJson('/v1/status')->assertOk();
    expect($status->json('data.overall'))->toBe('major_outage')
        ->and(collect($status->json('data.components'))->firstWhere('key', 'web-cz1')['state'])->toBe('major_outage')
        ->and($status->json('data.incidents.0.number'))->toBe('INC-'.now()->format('Y').'-0001');

    $this->postJson("/v1/staff/incidents/{$id}/updates", ['note' => 'Příčina: plný disk na shared01.', 'state' => 'IDENTIFIED'])->assertOk()->assertJsonPath('ui_state', 'identifikovano');
    $this->postJson("/v1/staff/incidents/{$id}/updates", ['note' => 'Interní: eskalováno na SRE on-call.', 'public' => false])->assertOk();
    $this->postJson("/v1/staff/incidents/{$id}/updates", ['note' => 'x', 'state' => 'POSTMORTEM'])->assertUnprocessable();

    $public = $this->getJson('/v1/incidents/INC-'.now()->format('Y').'-0001')->assertOk();
    expect($public->json('data.hist'))->toHaveCount(2)->and(collect($public->json('data.hist'))->pluck('note')->contains(fn ($n) => str_contains($n, 'Interní')))->toBeFalse();
    expect($this->getJson("/v1/staff/incidents/{$id}")->json('data.hist'))->toHaveCount(3);

    $this->postJson("/v1/staff/incidents/{$id}/resolve", ['note' => 'Disk rozšířen, služby obnoveny.'])->assertOk()->assertJsonPath('state', 'RESOLVED');
    expect(StatusComponent::query()->find('web-cz1')->state)->toBe('operational');
    app(OutboxPublisher::class)->relayPending();
    expect(MailOutbox::query()->where('template_key', 'incident-resolved')->exists())->toBeTrue();

    $this->postJson("/v1/staff/incidents/{$id}/postmortem", ['summary' => 'Disk plný', 'root_cause' => 'Chybějící alert na využití disku', 'actions' => [['owner' => 'sre', 'what' => 'Alert na 80 %']]])->assertUnprocessable();
    $this->postJson("/v1/staff/incidents/{$id}/postmortem", ['summary' => 'Disk na shared01 se zaplnil logy.', 'root_cause' => 'Chybějící alert na využití disku.', 'actions' => [['owner' => 'sre', 'deadline' => now()->addWeek()->toDateString(), 'what' => 'Alert na 80 % disku']]])
        ->assertOk()->assertJsonPath('state', 'POSTMORTEM');
    expect($this->getJson('/v1/incidents/postmortems')->json('data'))->toHaveCount(1)->and($this->getJson('/v1/incidents/postmortems')->json('data.0.postmortem.root_cause'))->toBe('Chybějící alert na využití disku.');

    $metrics = $this->getJson('/v1/staff/incidents/metrics')->assertOk();
    expect($metrics->json('data.total'))->toBe(1)->and($metrics->json('data.by_severity.p1'))->toBe(1)->and($metrics->json('data.mttr_seconds'))->toBeInt();
    foreach (['incident.open', 'incident.update', 'incident.resolve', 'incident.postmortem'] as $action) {
        expect(AuditEvent::query()->where('action', $action)->where('result', 'succeeded')->exists())->toBeTrue($action);
    }
});

it('keeps internal and security incidents off the status page but visible to affected customers', function () {
    [$customer, $org] = $this->customerWithOrganization();
    $service = incidentWebService($org);
    $this->actingAs($this->staff('sre'), 'sanctum');
    $this->postJson('/v1/staff/incidents', ['title' => 'Interní degradace', 'severity' => 'p3', 'components' => ['web-cz1'], 'visibility' => 'internal', 'affected_services' => [$service->id]])->assertCreated();
    $this->postJson('/v1/staff/incidents', ['title' => 'Podezření na kompromitaci', 'severity' => 'p2', 'components' => ['portal'], 'security' => true])->assertForbidden(); // security incidents need security.incident.manage
    $this->actingAs($this->staff('security_soc'), 'sanctum');
    $this->postJson('/v1/staff/incidents', ['title' => 'Podezření na kompromitaci', 'severity' => 'p2', 'components' => ['portal'], 'security' => true])->assertCreated()->assertJsonPath('visibility', 'internal');
    app(OutboxPublisher::class)->relayPending();
    expect(OutboxMessage::query()->where('name', 'security.incident.opened')->exists())->toBeTrue();

    expect($this->getJson('/v1/incidents')->json('data'))->toHaveCount(0);
    expect($this->getJson('/v1/status')->json('data.components.0.state'))->toBe('degraded'); // component state still reflects the internal incident

    $this->actingAs($customer, 'sanctum');
    $mine = $this->withHeader('X-Organization', $org->id)->getJson('/v1/my/incidents')->assertOk()->assertHeader('X-Total-Count', '1');
    expect($mine->json('data.0.title'))->toBe('Interní degradace');
});

it('schedules maintenance with lead time, rollback plan and four-eyes approval, then walks the window through the component state', function () {
    [$customer, $org] = $this->customerWithOrganization();
    $service = incidentWebService($org);
    $owner = $this->staff('sre');
    $this->actingAs($owner, 'sanctum');
    $window = ['title' => 'Výměna disků shared01', 'components' => ['web-cz1'], 'starts_at' => now()->addHours(72)->toIso8601String(), 'ends_at' => now()->addHours(74)->toIso8601String(), 'impact' => 'Krátké výpadky', 'affected_services' => [$service->id]];

    $this->postJson('/v1/staff/maintenance', $window)->assertUnprocessable()->assertJsonValidationErrors(['rollback']);
    $this->postJson('/v1/staff/maintenance', array_merge($window, ['rollback' => 'Vrátit staré disky', 'starts_at' => now()->addHour()->toIso8601String(), 'ends_at' => now()->addHours(2)->toIso8601String()]))->assertUnprocessable()->assertJsonPath('error', 'maintenance_lead_time');
    $created = $this->postJson('/v1/staff/maintenance', $window + ['rollback' => 'Vrátit staré disky'])->assertCreated()->assertJsonPath('state', 'planned')->assertJsonPath('number', 'MNT-'.now()->format('Y').'-0001');
    $id = $created->json('id');

    $this->postJson("/v1/staff/maintenance/{$id}/approve")->assertForbidden()->assertJsonPath('error', 'step_up_required'); // announcing to customers is HIGH risk
    app(StepUpService::class)->grant($owner, 'totp', null, '127.0.0.1');
    $this->postJson("/v1/staff/maintenance/{$id}/approve")->assertForbidden()->assertJsonPath('error', 'maintenance_self_approval');
    $approver = $this->staff('sre');
    $this->actingAs($approver, 'sanctum');
    app(StepUpService::class)->grant($approver, 'totp', null, '127.0.0.1');
    $this->postJson("/v1/staff/maintenance/{$id}/approve")->assertOk()->assertJsonPath('state', 'approved');
    app(OutboxPublisher::class)->relayPending();
    expect(MailOutbox::query()->where('template_key', 'maintenance')->where('to', $customer->email)->exists())->toBeTrue();
    expect($this->getJson('/v1/status')->json('data.maintenance.0.number'))->toBe('MNT-'.now()->format('Y').'-0001');

    $this->travelTo(now()->addHours(72)->addMinute());
    expect(app(MaintenanceService::class)->tick()['started'])->toBe(1);
    expect(Maintenance::query()->find($id)->state)->toBe('in_progress')->and(StatusComponent::query()->find('web-cz1')->state)->toBe('maintenance');
    $this->travelTo(now()->addHours(3));
    expect(app(MaintenanceService::class)->tick()['completed'])->toBe(1);
    expect(Maintenance::query()->find($id)->state)->toBe('completed')->and(StatusComponent::query()->find('web-cz1')->state)->toBe('operational');
});

/*
 * The right customers, and nothing internal (Brain card H16). An incident reaches the organizations whose services it
 * touches and nobody else, and what leaves the building — the status page, the customer's own list, the mail — has a
 * fixed shape: a field added to it later (who else is affected, which node, who is on call) has to be added here on purpose.
 */
it('tells only the affected customers, and never who else is affected or what staff said among themselves', function () {
    [$affected, $orgA] = $this->customerWithOrganization();
    [$bystander, $orgB] = $this->customerWithOrganization();
    $service = incidentWebService($orgA);
    $this->actingAs($this->staff('sre'), 'sanctum');
    $id = $this->postJson('/v1/staff/incidents', ['title' => 'Výpadek webhostingu CZ1', 'severity' => 'p1', 'components' => ['web-cz1'], 'impact' => 'Část webů je nedostupná.', 'affected_services' => [$service->id]])->assertCreated()->json('id');
    $this->postJson("/v1/staff/incidents/{$id}/updates", ['note' => 'Interní: zákazník '.$orgA->name.' volal, eskalováno na on-call.', 'public' => false])->assertOk();
    $this->postJson("/v1/staff/incidents/{$id}/updates", ['note' => 'Příčinu známe, opravujeme.', 'state' => 'IDENTIFIED'])->assertOk();
    app(OutboxPublisher::class)->relayPending();

    // the bystander hears nothing and sees nothing of their own
    expect(Notification::query()->where('organization_id', $orgB->id)->where('kind', 'incident.affecting')->count())->toBe(0)
        ->and(MailOutbox::query()->where('to', $bystander->email)->count())->toBe(0)
        ->and(Notification::query()->where('organization_id', $orgA->id)->where('kind', 'incident.affecting')->count())->toBeGreaterThan(0);
    $this->actingAs($bystander, 'sanctum');
    $this->withHeader('X-Organization', $orgB->id)->getJson('/v1/my/incidents')->assertOk()->assertHeader('X-Total-Count', '0');
    $this->flushHeaders();

    // what anybody can read has exactly these fields, and none of them names a customer, a service or an internal note
    $allowed = ['number', 'title', 'sev', 'state', 'ui_state', 'state_label', 'components', 'impact', 'started_at', 'resolved_at', 'duration', 'hist', 'postmortem'];
    $number = Incident::query()->findOrFail($id)->number;
    $this->actingAs($affected, 'sanctum');
    foreach ([$this->getJson("/v1/incidents/{$number}")->assertOk()->json('data'), $this->withHeader('X-Organization', $orgA->id)->getJson('/v1/my/incidents')->assertOk()->json('data.0'), $this->getJson('/v1/status')->assertOk()->json('data.incidents.0')] as $seen) {
        expect(array_values(array_diff(array_keys($seen), $allowed)))->toBe([]);
        $text = (string) json_encode($seen, JSON_UNESCAPED_UNICODE);
        expect($text)->not->toContain($orgA->id)->not->toContain($orgA->name)->not->toContain($service->id)->not->toContain('Interní')->not->toContain('on-call');
        foreach ((array) ($seen['hist'] ?? []) as $entry) {
            expect(array_keys($entry))->toBe(['state', 'ui_state', 'at', 'note']); // no author, no audience flag
        }
    }
    // the mail to the affected customer carries the public wording only
    $mail = MailOutbox::query()->where('to', $affected->email)->where('template_key', 'incident')->firstOrFail();
    expect((string) json_encode($mail->toArray(), JSON_UNESCAPED_UNICODE))->not->toContain('Interní')->not->toContain('on-call');
});
