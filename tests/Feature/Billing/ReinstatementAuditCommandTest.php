<?php

declare(strict_types=1);

use Onhost\Domain\Billing\Models\Subscription;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;

/*
 * The read-only look before pay and restore is switched on (TASK-0025): which undone cancellations run unbilled, and the
 * one-service-at-a-time way to bill one of them again. Nothing changes in bulk, nothing is billed back.
 */

/** A cancellation the customer took back before TASK-0025: the service runs, its subscription stayed CANCELLED. */
function reinstateAuditLeak(Organization $org, string $name): Service
{
    $service = Service::query()->create([
        'organization_id' => $org->id, 'product_key' => 'database', 'family' => 'data', 'name' => $name, 'state' => ServiceStateMachine::ACTIVE, 'region_code' => 'cz1',
        'desired_spec' => ['executor' => 'proxmox'], 'entitlements' => [], 'sla_class' => 'standard', 'activated_at' => now()->subMonths(4),
        'tags' => ['deletion_cancelled' => ['requested_at' => now()->subMonths(2)->toIso8601String(), 'cancelled_at' => now()->subMonths(2)->addDay()->toIso8601String()]],
    ]);
    $end = now()->subMonth();
    Subscription::query()->create(['organization_id' => $org->id, 'service_id' => $service->id, 'currency' => 'CZK', 'period' => 'month', 'amount_minor' => 30000, 'state' => Subscription::CANCELLED,
        'current_period_start' => $end->copy()->subMonth(), 'current_period_end' => $end, 'next_renewal_at' => $end, 'auto_renew' => false, 'renewal_priority' => 'normal']);

    return $service;
}

it('lists the undone cancellations that run unbilled without changing anything', function () {
    [, $org] = $this->customerWithOrganization();
    $leak = reinstateAuditLeak($org, 'Databáze A');

    $this->artisan('onhost:billing:reinstatement-audit')->expectsOutputToContain($leak->id)->expectsOutputToContain('Nic nebylo změněno')->assertSuccessful();
    $this->artisan('onhost:billing:reinstatement-audit', ['--dry-run' => true])->assertSuccessful();

    expect(Subscription::query()->where('service_id', $leak->id)->value('state'))->toBe(Subscription::CANCELLED);
});

it('bills again exactly the one service it is told to, and refuses to do it in bulk', function () {
    [, $org] = $this->customerWithOrganization();
    $one = reinstateAuditLeak($org, 'Databáze A');
    $other = reinstateAuditLeak($org, 'Databáze B');

    $this->artisan('onhost:billing:reinstatement-audit', ['--apply' => true])->assertFailed();
    $this->artisan('onhost:billing:reinstatement-audit', ['--apply' => true, '--service' => 'svc_not_on_the_list'])->assertFailed();
    expect(Subscription::query()->where('state', Subscription::CANCELLED)->count())->toBe(2);

    $this->artisan('onhost:billing:reinstatement-audit', ['--apply' => true, '--service' => $one->id])->assertSuccessful();
    $restarted = Subscription::query()->where('service_id', $one->id)->firstOrFail();
    expect($restarted->state)->toBe(Subscription::ACTIVE)->and($restarted->auto_renew)->toBeTrue()
        ->and($restarted->next_renewal_at->toDateString())->toBe(now()->toDateString()) // the next renewal bills a full period from today; nothing back
        ->and(Subscription::query()->where('service_id', $other->id)->value('state'))->toBe(Subscription::CANCELLED)
        ->and((int) data_get($one->fresh()->tags, 'billing_anchor_day'))->toBe(now()->day);
});
