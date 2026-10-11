<?php

declare(strict_types=1);

use App\Http\StaffReadAudit;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route as Router;
use Onhost\Domain\Compliance\Models\AbuseCase;
use Onhost\Domain\Compliance\Models\CyberIncident;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Partners\Models\Partner;
use Onhost\Domain\Provisioning\Models\BulkJob;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Provisioning\Workflows\ServiceActionWorkflow;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Support\TicketService;
use Onhost\Platform\Audit\AuditEvent;

/*
 * G7 (TASK-0115): four staff controllers recorded that a member of staff looked at a customer's data; the other staff screens
 * over orders, services, domains, payments, partners, withdrawals, leads … left nothing. The staff guard records every
 * successful GET of a route in StaffReadAudit::ROUTES now. This sweep walks the router: every GET under /v1/staff is classified
 * (ROUTES or EXEMPT with a reason), and every classified customer-data route — opened by the platform owner — writes a row.
 */

beforeEach(fn () => Http::preventStrayRequests());

/** @return list<string> the URIs of every GET route under /v1/staff */
function staffReadSweepRoutes(): array
{
    return collect(Router::getRoutes()->getRoutes())
        ->filter(fn (Route $route) => in_array('GET', $route->methods(), true) && str_starts_with($route->uri(), 'v1/staff/'))
        ->map(fn (Route $route) => $route->uri())->unique()->sort()->values()->all();
}

/** A customer organization with one row behind every staff detail route; returns route URI → concrete path. @return array<string, string> */
function staffReadSweepFixtures(Organization $org, Service $service, string $ticketId): array
{
    $abuse = AbuseCase::query()->create(['number' => 'ABU-2026-9001', 'reporter' => ['name' => 'R', 'email' => 'r@example.cz'], 'category' => 'phishing', 'allegation' => 'A phishing page.', 'organization_id' => $org->id, 'service_id' => $service->id]);
    $cyber = CyberIncident::query()->create(['number' => 'SEC-2026-9001', 'title' => 'Credential leak', 'detected_at' => now(), 'jurisdictions' => ['CZ']]);
    $bulk = BulkJob::query()->create(['action' => 'php.set', 'params' => ['version' => '8.3'], 'filter' => ['organization_id' => $org->id], 'state' => 'finished', 'items' => []]);
    $partner = Partner::query()->create(['organization_id' => $org->id, 'code' => 'G7SWEEP', 'state' => 'active']);
    $operation = Operation::query()->create(['service_id' => $service->id, 'organization_id' => $org->id, 'kind' => ServiceActionWorkflow::kind(), 'workflow' => ServiceActionWorkflow::class, 'state' => Operation::FAILED, 'queue' => 'default', 'idempotency_key' => 'g7-sweep-op', 'desired' => ['action' => 'restart'], 'queued_at' => now()->subHour(), 'finished_at' => now()->subHour(), 'attempts' => 1, 'error' => ['message' => 'nope', 'retryable' => false]]);

    return [
        'v1/staff/abuse-cases/{case}' => "v1/staff/abuse-cases/{$abuse->id}",
        'v1/staff/bulk-jobs/{job}' => "v1/staff/bulk-jobs/{$bulk->id}",
        'v1/staff/customers/{organization}' => "v1/staff/customers/{$org->id}",
        'v1/staff/integrations/{instance}' => 'v1/staff/integrations/'.pveLab()->id,
        'v1/staff/partners/{partner}' => "v1/staff/partners/{$partner->id}",
        'v1/staff/provisioning/jobs/{operation}' => "v1/staff/provisioning/jobs/{$operation->id}",
        'v1/staff/security/incidents/{case}' => "v1/staff/security/incidents/{$cyber->id}",
        'v1/staff/tickets/{ticket}' => "v1/staff/tickets/{$ticketId}",
        'v1/staff/tickets/{ticket}/work-offers' => "v1/staff/tickets/{$ticketId}/work-offers",
    ];
}

it('classifies every GET route under /v1/staff as customer data or as exempt with a reason', function () {
    $routes = staffReadSweepRoutes();
    $classified = array_merge(array_keys(StaffReadAudit::ROUTES), array_keys(StaffReadAudit::EXEMPT));

    expect(array_values(array_diff($routes, $classified)))->toBe([]) // a new staff screen: add it to ROUTES, or to EXEMPT with why
        ->and(array_values(array_diff($classified, $routes)))->toBe([]) // a route that is gone leaves the lists too
        ->and(array_values(array_intersect(array_keys(StaffReadAudit::ROUTES), array_keys(StaffReadAudit::EXEMPT))))->toBe([])
        ->and(array_filter(StaffReadAudit::EXEMPT, fn (string $why) => trim($why) === ''))->toBe([]);
});

it('writes a staff read row for every staff GET route that shows customer data', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $service = featureWebService($org, 'aapanel');
    $ticket = app(TicketService::class)->create(['subject' => 'Nejde web', 'body' => 'Chyba 500.'], $this->contextFor($owner, $org), $org, $owner);
    $paths = staffReadSweepFixtures($org, $service, $ticket->id);
    $staff = $this->staff('platform_owner');
    $this->actingAs($staff, 'sanctum');

    $missing = [];
    foreach (array_keys(StaffReadAudit::ROUTES) as $uri) {
        $before = AuditEvent::query()->where('actor_id', $staff->id)->where('action', 'like', 'staff.read.%')->count();
        $response = $this->getJson('/'.($paths[$uri] ?? $uri));
        if ($response->getStatusCode() !== 200) {
            $missing[$uri] = 'HTTP '.$response->getStatusCode();

            continue;
        }
        if (AuditEvent::query()->where('actor_id', $staff->id)->where('action', 'like', 'staff.read.%')->count() <= $before) {
            $missing[$uri] = 'no staff.read row';
        }
    }

    expect($missing)->toBe([]);
});

it('files a look at one customer\'s record in that customer\'s own trail, and records nothing for a refused or exempt read', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $service = featureWebService($org, 'aapanel');
    $ticket = app(TicketService::class)->create(['subject' => 'Nejde web', 'body' => 'Chyba 500.'], $this->contextFor($owner, $org), $org, $owner);
    $paths = staffReadSweepFixtures($org, $service, $ticket->id);
    $staff = $this->staff('platform_owner');
    $this->actingAs($staff, 'sanctum');

    $this->getJson('/'.$paths['v1/staff/partners/{partner}'])->assertOk();
    $this->getJson('/'.$paths['v1/staff/provisioning/jobs/{operation}'])->assertOk();
    $this->getJson('/'.$paths['v1/staff/tickets/{ticket}/work-offers'])->assertOk();
    $this->getJson('/v1/staff/partners/does-not-exist')->assertNotFound();
    $this->getJson('/v1/staff/reports/mrr')->assertOk();

    $reads = AuditEvent::query()->where('actor_id', $staff->id)->where('action', 'like', 'staff.read.%')->get();
    expect($reads->where('organization_id', $org->id)->pluck('action')->sort()->values()->all())->toBe(['staff.read.operation', 'staff.read.partner', 'staff.read.ticket_work_offers'])
        ->and($reads->where('resource_id', 'does-not-exist')->count())->toBe(0)
        ->and($reads->pluck('action')->all())->not->toContain('staff.read.mrr');
});

it('keeps the id and the organization of a parameter bound to its model (implicit route-model binding)', function () {
    [, $org] = $this->customerWithOrganization();
    $partner = Partner::query()->create(['organization_id' => $org->id, 'code' => 'G7BOUND', 'state' => 'active']);
    $staff = $this->staff('platform_owner');
    $request = Request::create('/v1/staff/partners/'.$partner->id, 'GET');
    $route = (new Route(['GET'], 'v1/staff/partners/{partner}', fn () => null))->bind($request);
    $route->setParameter('partner', $partner); // what SubstituteBindings leaves for a typed controller argument
    $request->setRouteResolver(fn () => $route);

    app(StaffReadAudit::class)->afterResponse($request, new Response('', 200), $this->staffContextFor($staff));

    $read = AuditEvent::query()->where('actor_id', $staff->id)->where('action', 'staff.read.partner')->sole();
    expect($read->resource_type)->toBe('partner')->and($read->resource_id)->toBe($partner->id)->and($read->organization_id)->toBe($org->id);
});
