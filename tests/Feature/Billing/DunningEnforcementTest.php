<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Billing\DunningService;
use Onhost\Domain\Billing\Models\DunningCase;
use Onhost\Domain\Invoicing\Models\Invoice;
use Onhost\Domain\Notifications\Models\Notification;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Provisioning\Models\ProviderBinding;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Platform\Outbox\OutboxPublisher;

/*
 * Dunning says "suspended" and "terminated" about a CASE; what matters is the service. A suspension the panel refused was
 * never asked for again, and a case whose service was still running was closed as TERMINATED without anybody touching the
 * service — it then ran for nothing, with no case left to notice. A step that did not happen is asked for again every day,
 * staff hear about it, and a case is closed only once the service is really down.
 */

beforeEach(function () {
    $this->seed([CatalogSeeder::class, TaxRuleSeeder::class, LegalEntitySeeder::class]);
    Http::preventStrayRequests();
});

/** @return array{0:Service,1:Invoice,2:DunningCase} a VPS with an invoice `$daysOverdue` days past due and its dunning case */
function dunningOverdueVps(Organization $org, int $daysOverdue): array
{
    $instance = pveLab();
    $service = Service::query()->create(['organization_id' => $org->id, 'product_key' => 'vps', 'family' => 'cloud', 'name' => 'VPS', 'state' => ServiceStateMachine::ACTIVE, 'region_code' => 'cz1', 'provider_instance_id' => $instance->id, 'entitlements' => [],
        'desired_spec' => ['executor' => 'proxmox', 'family' => 'cloud'], 'sla_class' => 'standard', 'activated_at' => now()->subMonths(3), 'tags' => [], 'health' => []]);
    ProviderBinding::query()->create(['service_id' => $service->id, 'provider_instance_id' => $instance->id, 'remote_type' => 'qemu', 'remote_id' => '1042', 'remote_node' => 'prg1-n2', 'meta' => [], 'ownership' => [], 'idempotency_key' => 'b-dun-'.$service->id, 'adapter_version' => '1.0.0']);
    $invoice = Invoice::query()->create(['legal_entity' => 'onhost-cz', 'series' => 'FV', 'type' => 'invoice', 'number' => 'FV-2026-0'.random_int(200, 999), 'organization_id' => $org->id, 'currency' => 'CZK', 'state' => Invoice::OVERDUE,
        'subtotal_minor' => 44900, 'discount_minor' => 0, 'tax_minor' => 9429, 'total_minor' => 54329, 'paid_minor' => 0, 'issued_at' => now()->subDays($daysOverdue + 14), 'due_at' => now()->subDays($daysOverdue), 'meta' => ['postpaid' => true]]);
    $case = app(DunningService::class)->open($org->id, $invoice->id, $service->id, $invoice->due_at);

    return [$service, $invoice, $case];
}

it('asks again for a suspension the panel refused, and tells staff that an unpaid service still runs', function () {
    $panelDown = true;
    Http::fake(function (Request $r) use (&$panelDown) {
        $path = (string) parse_url($r->url(), PHP_URL_PATH);

        return match (true) {
            str_ends_with($path, '/status/current') => Http::response(['data' => ['status' => 'running']]),
            str_ends_with($path, '/qemu/1042/config') => $r->method() === 'GET' ? Http::response(pveVmConfig()) : Http::response(['data' => null]),
            str_ends_with($path, '/status/shutdown'), str_ends_with($path, '/status/stop') => $panelDown ? Http::response(['errors' => 'cluster not ready - no quorum?'], 500) : Http::response(['data' => 'UPID:prg1-n2:1:1:1:qmshutdown:1042:onhost@pve!cp:']),
            str_contains($path, '/tasks/') => Http::response(['data' => ['status' => 'stopped', 'exitstatus' => 'OK']]),
            default => Http::response(['data' => null]),
        };
    });
    [, $org] = $this->customerWithOrganization();
    [$service, , $case] = dunningOverdueVps($org, 35);
    $dunning = app(DunningService::class);

    expect($dunning->tick()['suspended'])->toBe(1);
    driveOperations();
    expect($case->refresh()->state)->toBe(DunningCase::SUSPENDED)->and($service->refresh()->state)->toBe(ServiceStateMachine::ACTIVE); // the case says suspended, the site runs

    // the next day: asked for again (it used to be never), and staff hear that an unpaid service still runs.
    // A case says when it may be looked at again — tomorrow at 06:00 — so the clock is moved PAST that hour and not
    // by a flat day: `travel(1)->days()` from a run that started between midnight and 06:00 UTC lands before it, and
    // the case is then not due, which made this test fail every night for six hours.
    // … and the retry is only asked for once the suspension has stood for twelve hours (`suspended_at->lt(now()->subHours(12))`),
    // so a run late in the evening has to travel past that window as well: from 19:00 UTC the next morning at 07:00 is
    // only eleven hours away, and this test went red every night from seven o'clock.
    $nextMorning = function () {
        $morning = now()->addDay()->startOfDay()->addHours(7);

        $this->travelTo($morning->lt(now()->addHours(13)) ? now()->addHours(13) : $morning);
    };
    $nextMorning();
    $dunning->tick();
    app(OutboxPublisher::class)->relayPending();
    expect($case->actions()->where('action', 'suspend_retry')->count())->toBe(1)
        ->and(Notification::query()->where('audience', 'internal')->where('title', 'like', 'Neplacená služba stále běží%')->exists())->toBeTrue();
    driveOperations();
    expect($service->refresh()->state)->toBe(ServiceStateMachine::ACTIVE);

    // the panel is back: the next day's attempt goes through
    $panelDown = false;
    $nextMorning();
    $dunning->tick();
    driveOperations();
    expect($service->refresh()->state)->toBe(ServiceStateMachine::SUSPENDED)->and($service->suspended_reason)->toBe('dunning')->and($case->actions()->where('action', 'suspend_retry')->count())->toBe(2);

    // and once it is down nothing more is asked for
    $nextMorning();
    $dunning->tick();
    expect($case->actions()->where('action', 'suspend_retry')->count())->toBe(2);
});

it('does not close a case as terminated while its service still runs', function () {
    Http::fake(fn () => Http::response(['data' => null]));
    [, $org] = $this->customerWithOrganization();
    [$service, , $case] = dunningOverdueVps($org, 61);
    // the suspension never happened (the panel was down for weeks); the case walked on by the calendar
    $case->forceFill(['state' => DunningCase::TERMINATION_SCHEDULED, 'suspended_at' => now()->subDays(30), 'termination_at' => now()->subDay(), 'notices_sent' => [3, 7, 14], 'next_action_at' => now()->subHour()])->save();
    $dunning = app(DunningService::class);

    $stats = $dunning->tick();

    // it used to be: case TERMINATED, service ACTIVE, nothing asked of the panel — a service running for nothing with no case left
    expect($stats['terminated'])->toBe(0)->and($case->refresh()->state)->toBe(DunningCase::TERMINATION_SCHEDULED)
        ->and(Operation::query()->where('service_id', $service->id)->get()->contains(fn (Operation $o) => ($o->desired['action'] ?? null) === 'terminate'))->toBeTrue()
        ->and($case->actions()->where('action', 'terminate')->exists())->toBeTrue();

    // the cancellation went through (deactivated, the removal follows the restore window): now the case is closed
    Operation::query()->where('service_id', $service->id)->update(['state' => Operation::SUCCEEDED, 'finished_at' => now()]);
    $service->forceFill(['state' => ServiceStateMachine::SUSPENDED, 'terminate_at' => now()->addDays(30)])->save();
    $this->travelTo(now()->addDay()->startOfDay()->addHours(7)); // past the hour the case may be looked at again, whatever time the suite starts
    expect($dunning->tick()['terminated'])->toBe(1)->and($case->refresh()->state)->toBe(DunningCase::TERMINATED);
});
