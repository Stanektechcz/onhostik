<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Onhost\Domain\Billing\Models\ChargebackRequest;
use Onhost\Domain\Billing\Models\DunningCase;
use Onhost\Domain\Billing\Models\Subscription;
use Onhost\Domain\Billing\ServiceReinstatement;
use Onhost\Domain\Billing\SubscriptionService;
use Onhost\Domain\Identity\Authorization\Authorizer;
use Onhost\Domain\Identity\Authorization\Models\PolicyBinding;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Invoicing\InvoiceService;
use Onhost\Domain\Invoicing\Models\Invoice;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Organizations\OrganizationService;
use Onhost\Domain\Provisioning\AutomationLedger;
use Onhost\Domain\Provisioning\FreezeSwitch;
use Onhost\Domain\Provisioning\Models\Node;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Provisioning\Models\ProviderBinding;
use Onhost\Domain\Services\Commands\ServiceActionCommand;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Domain\Services\ServiceService;
use Onhost\Domain\Services\SuspensionHold;
use Onhost\Domain\WalletLedger\Models\LedgerTransaction;
use Onhost\Domain\WalletLedger\WalletService;
use Onhost\Platform\Commands\CommandBus;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Money\Money;
use Onhost\Platform\Outbox\OutboxMessage;
use Onhost\Platform\Outbox\OutboxPublisher;

/*
 * Pay and restore (owner decision 23): a service that ended — cancelled by dunning, its subscription expired, or cancelled
 * by the customer and then taken back — comes back inside its restore window once what it owes is paid, through the
 * ordinary resume. Money never lifts a quarantine, never touches a service that is already gone, is taken once, and an
 * undone cancellation is billed again. Everything new is behind the default-off rule `services.reinstate`.
 */

beforeEach(function () {
    $this->seed([CatalogSeeder::class, TaxRuleSeeder::class, LegalEntitySeeder::class]);
    Http::preventStrayRequests();
    Http::fake([
        PVE.'/nodes/prg1-n2/qemu/*/status/current' => Http::response(['data' => ['status' => 'stopped']]),
        PVE.'/nodes/prg1-n2/qemu/*/config' => fn (Request $r) => $r->method() === 'GET' ? Http::response(pveVmConfig()) : Http::response(['data' => null]),
        PVE.'/nodes/prg1-n2/qemu/*/status/start' => Http::response(['data' => 'UPID:prg1-n2:000A1B36:0004E1FE:66F0AA1A:qmstart:1042:onhost@pve!cp:']),
        PVE.'/nodes/prg1-n2/tasks/*/status' => Http::response(['data' => ['status' => 'stopped', 'exitstatus' => 'OK']]),
    ]);
});

afterEach(function () {
    unset($_ENV['PROXMOX_CZ1_TOKEN_ID'], $_ENV['PROXMOX_CZ1_TOKEN_SECRET']);
});

function reinstateSwitchOn(bool $on = true): void
{
    app(AutomationLedger::class)->setEnabled('services.reinstate', $on);
}

/**
 * A managed database the platform cancelled: deactivated, its restore window running, its subscription CANCELLED the way
 * the terminate saga leaves it. `hold` null = the customer's own cancellation (no hold).
 *
 * @param  array<string,mixed>  $o
 */
function reinstateCancelled(Organization $org, array $o = []): Service
{
    $instance = pveLab();
    $node = Node::query()->where('name', 'prg1-n2')->firstOrFail();
    $reason = (string) ($o['reason'] ?? 'unpaid after dunning');
    $hold = array_key_exists('hold', $o) ? $o['hold'] : SuspensionHold::PAYMENT;
    $holds = [];
    foreach ((array) ($o['holds'] ?? ($hold === null ? [] : [$hold])) as $kind) {
        $holds[$kind] = ['reason' => $reason, 'by' => 'system', 'at' => now()->subDays(5)->toIso8601String()];
    }
    $terminateAt = $o['terminate_at'] ?? now()->addDays(20);
    $service = Service::query()->create([
        'organization_id' => $org->id, 'product_key' => 'database', 'family' => 'data', 'name' => (string) ($o['name'] ?? 'Databáze'), 'hostname' => 'db-'.uniqid().'.cust.onhost.cz',
        'state' => ServiceStateMachine::SUSPENDED, 'region_code' => 'cz1', 'provider_instance_id' => $instance->id, 'node_id' => $node->id,
        'desired_spec' => ['executor' => 'proxmox', 'family' => 'data'], 'entitlements' => [], 'sla_class' => 'standard', 'activated_at' => $o['activated_at'] ?? now()->subMonths(3),
        'suspended_at' => now()->subDays(5), 'suspended_reason' => $reason, 'terminate_at' => $terminateAt,
        'tags' => ['suspension' => ['holds' => $holds], 'deletion' => [
            'requested_at' => now()->subDays(5)->toIso8601String(), 'grace_until' => $terminateAt->toIso8601String(), 'grace_days' => 30, 'reason' => $reason,
            'operation_id' => (string) ($o['operation_id'] ?? 'op_cancel_'.substr(uniqid(), -8)), 'subscription' => ['state' => 'active', 'auto_renew' => (bool) ($o['auto_renew'] ?? true)],
        ]] + (array) ($o['tags'] ?? []),
    ]);
    ProviderBinding::query()->create(['service_id' => $service->id, 'provider_instance_id' => $instance->id, 'remote_type' => 'qemu', 'remote_id' => (string) ($o['remote_id'] ?? '1042'), 'remote_node' => 'prg1-n2', 'meta' => ['name' => 'vm-test'], 'ownership' => ['managed_by' => 'onhost'], 'idempotency_key' => "provision:{$service->id}:qemu", 'adapter_version' => '1.0.0']);
    $end = $o['period_end'] ?? now()->subDays(2);
    $subscription = Subscription::query()->create([
        'organization_id' => $org->id, 'service_id' => $service->id, 'currency' => 'CZK', 'period' => 'month', 'amount_minor' => 30000, 'state' => Subscription::CANCELLED,
        'current_period_start' => $end->copy()->subMonthNoOverflow(), 'current_period_end' => $end, 'next_renewal_at' => $end, 'auto_renew' => false, 'renewal_priority' => 'normal',
    ]);
    $service->forceFill(['subscription_id' => $subscription->id])->save();

    return $service->refresh();
}

function reinstateCharges(): int
{
    return LedgerTransaction::query()->where('idempotency_key', 'like', 'ledger:sub_reinstate:%')->count();
}

/** An invoice for the service's current period that nobody paid, and the dunning case that cancelled the service for it. @return array{0:Invoice,1:DunningCase} */
function reinstateDunnedInvoice(Organization $org, Service $service): array
{
    $invoice = chargebackPaidStatement($org, $service, 36300, 6300, -20, 10, 'invoice', false);
    $invoice->forceFill(['state' => Invoice::OVERDUE])->save();
    $case = DunningCase::query()->create(['organization_id' => $org->id, 'invoice_id' => $invoice->id, 'service_id' => $service->id, 'state' => DunningCase::TERMINATED, 'due_at' => now()->subDays(60), 'next_action_at' => null, 'notices_sent' => [3, 7, 14]]);

    return [$invoice->fresh(), $case];
}

it('restores a service cancelled by dunning once its overdue invoice is paid inside the restore window', function () {
    reinstateSwitchOn();
    [, $org] = $this->customerWithOrganization();
    $service = reinstateCancelled($org, ['period_end' => now()->addDays(10)]); // the unpaid invoice is for the period that still runs
    [$invoice, $case] = reinstateDunnedInvoice($org, $service);

    app(InvoiceService::class)->markPaid($invoice, $invoice->total(), 'bank', CommandContext::system('test')->withScope($org->id));
    app(OutboxPublisher::class)->relayPending();
    driveOperations();
    app(OutboxPublisher::class)->relayPending();

    $back = Service::query()->findOrFail($service->id);
    expect($back->state)->toBe(ServiceStateMachine::ACTIVE)->and($back->terminate_at)->toBeNull()->and(SuspensionHold::holds($back))->toBe([]);
    expect(Subscription::query()->where('service_id', $service->id)->value('state'))->toBe(Subscription::ACTIVE);
    expect($case->actions()->pluck('action')->all())->toContain('reinstate');
    expect(OutboxMessage::query()->where('name', 'service.reinstated')->where('aggregate_id', $service->id)->exists())->toBeTrue();
    expect(reinstateCharges())->toBe(0); // the paid invoice covers the period: nothing more is taken
});

it('lets the customer pay and bring back a service whose subscription expired', function () {
    reinstateSwitchOn();
    [$owner, $org] = $this->customerWithOrganization();
    app(WalletService::class)->topup($org, Money::decimal('1000', 'CZK'), 'bank', 'seed', $this->contextFor($owner, $org));
    $service = reinstateCancelled($org, ['reason' => 'subscription ended']);
    $this->actingAs($owner, 'sanctum');

    $shown = $this->getJson("/v1/services/{$service->id}")->assertOk()->json('data');
    expect($shown['deletion']['pay_to_restore'])->toBeTrue()->and($shown['suspension']['message'])->toContain('Zaplatit a obnovit');
    $quote = $this->getJson("/v1/services/{$service->id}/reinstatement")->assertOk()->json('data');
    expect($quote)->toMatchArray(['eligible' => true, 'reason' => null, 'mode' => 'wallet'])
        ->and($quote['total_due']['minor'])->toBe(36300)->and($quote['shortfall']['minor'])->toBe(0)->and($quote['renewal']['amount']['minor'])->toBe(36300);

    $this->withHeader('Idempotency-Key', 're-expired-1')->postJson("/v1/services/{$service->id}/reinstate")->assertStatus(202)->assertJsonPath('state', 'restoring');
    $statement = Invoice::query()->where('type', 'statement')->where('meta->reinstatement', true)->firstOrFail();
    expect($statement->state)->toBe(Invoice::PAID)->and($statement->total_minor)->toBe(36300);
    expect(app(WalletService::class)->spendable($org, 'CZK')->minor)->toBe(100000 - 36300)->and(reinstateCharges())->toBe(1);
    $subscription = Subscription::query()->where('service_id', $service->id)->firstOrFail();
    expect($subscription->state)->toBe(Subscription::ACTIVE)->and($subscription->auto_renew)->toBeTrue()
        ->and($subscription->current_period_start->toDateString())->toBe(now()->toDateString())
        ->and($subscription->current_period_end->toDateString())->toBe(now()->addMonthNoOverflow()->toDateString());

    driveOperations();
    $back = Service::query()->findOrFail($service->id);
    expect($back->state)->toBe(ServiceStateMachine::ACTIVE)->and($back->terminate_at)->toBeNull()->and(SuspensionHold::holds($back))->toBe([]);
});

it('records the wish when the credit is short and restores after the top-up', function () {
    reinstateSwitchOn();
    [$owner, $org] = $this->customerWithOrganization();
    app(WalletService::class)->topup($org, Money::decimal('100', 'CZK'), 'bank', 'seed', $this->contextFor($owner, $org));
    $service = reinstateCancelled($org, ['reason' => 'subscription ended']);
    $this->actingAs($owner, 'sanctum');

    $this->withHeader('Idempotency-Key', 're-short-1')->postJson("/v1/services/{$service->id}/reinstate")->assertStatus(202)
        ->assertJsonPath('state', 'awaiting_payment')->assertJsonPath('shortfall.minor', 36300 - 10000);
    $waiting = Service::query()->findOrFail($service->id);
    expect($waiting->state)->toBe(ServiceStateMachine::SUSPENDED)->and($waiting->terminate_at)->not->toBeNull()
        ->and(data_get($waiting->tags, 'reinstatement.requested_at'))->not->toBeNull()->and(reinstateCharges())->toBe(0);

    app(WalletService::class)->topup($org, Money::decimal('1000', 'CZK'), 'bank', 'seed-2', $this->contextFor($owner, $org));
    app(OutboxPublisher::class)->relayPending();
    driveOperations();

    $back = Service::query()->findOrFail($service->id);
    expect($back->state)->toBe(ServiceStateMachine::ACTIVE)->and($back->terminate_at)->toBeNull()->and(data_get($back->tags, 'reinstatement'))->toBeNull();
    expect(reinstateCharges())->toBe(1);
});

it('never charges a cancelled service that nobody asked to restore when credit arrives', function () {
    reinstateSwitchOn();
    [$owner, $org] = $this->customerWithOrganization();
    $service = reinstateCancelled($org, ['reason' => 'subscription ended']);

    app(WalletService::class)->topup($org, Money::decimal('1000', 'CZK'), 'bank', 'seed', $this->contextFor($owner, $org));
    app(OutboxPublisher::class)->relayPending();

    expect(reinstateCharges())->toBe(0)->and(Service::query()->findOrFail($service->id)->terminate_at)->not->toBeNull();
});

it('starts billing again when the customer takes back their own cancellation', function () {
    reinstateSwitchOn();
    [$owner, $org] = $this->customerWithOrganization();
    $end = now()->addDays(15);
    $service = reinstateCancelled($org, ['hold' => null, 'reason' => 'customer request', 'period_end' => $end]);

    $operation = driveOperation(app(ServiceService::class)->requestAction($service, 'resume', $this->contextFor($owner, $org), 'undo-own-1'));
    expect($operation->state)->toBe(Operation::SUCCEEDED);
    app(OutboxPublisher::class)->relayPending();

    $subscription = Subscription::query()->where('service_id', $service->id)->firstOrFail();
    expect($subscription->state)->toBe(Subscription::ACTIVE)->and($subscription->auto_renew)->toBeTrue()->and($subscription->cancel_at_period_end)->toBeFalse()
        ->and($subscription->next_renewal_at->toDateString())->toBe($end->copy()->subDays(7)->toDateString())->and(reinstateCharges())->toBe(0);
});

it('asks for payment when the paid period of an undone cancellation has ended, and resumes as before with the switch off', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $service = reinstateCancelled($org, ['hold' => null, 'reason' => 'customer request', 'period_end' => now()->subDay()]);
    $services = app(ServiceService::class);

    reinstateSwitchOn();
    try {
        $services->requestAction($service, 'resume', $this->contextFor($owner, $org), 'undo-ended-on');
        $this->fail('a free resume of an unpaid period');
    } catch (DomainError $e) {
        expect($e->error)->toBe('reinstatement_payment_required')->and($e->status)->toBe(402)->and($e->extra['quote']['total_due']->minor)->toBe(36300)
            ->and($e->extra['pay'])->toBe("/v1/services/{$service->id}/reinstate");
    }

    reinstateSwitchOn(false);
    expect($services->requestAction($service->fresh(), 'resume', $this->contextFor($owner, $org), 'undo-ended-off'))->toBeInstanceOf(Operation::class);
});

it('does not let the customer undo a cancellation that was refunded through a chargeback', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $service = reinstateCancelled($org, ['hold' => null, 'reason' => 'chargeback cb_1', 'period_end' => now()->addDays(20), 'operation_id' => 'op_chargeback_1']);
    ChargebackRequest::query()->create(['organization_id' => $org->id, 'service_id' => $service->id, 'requested_by' => $owner->id, 'state' => ChargebackRequest::REFUNDED, 'reason' => 'Odcházíme jinam.', 'percent' => 70, 'currency' => 'CZK',
        'unused_minor' => 24200, 'refund_minor' => 16940, 'operation_id' => 'op_chargeback_1', 'cancelled_at' => now()->subDays(5), 'refunded_at' => now()->subDays(5)]);
    $services = app(ServiceService::class);

    // switch off: the refund was paid for giving the service up — the customer cannot take it back for free
    expect(fn () => $services->requestAction($service, 'resume', $this->contextFor($owner, $org), 'cb-undo-off'))->toThrow(DomainError::class, 'chargeback');

    // switch on: the refunded period does not count, a whole new one is owed, and a plain resume asks for it
    reinstateSwitchOn();
    $quote = app(ServiceReinstatement::class)->quote($service->fresh());
    expect($quote['renewal'])->not->toBeNull()->and($quote['total_due']->minor)->toBe(36300);
    try {
        $services->requestAction($service->fresh(), 'resume', $this->contextFor($owner, $org), 'cb-undo-on');
        $this->fail('a free resume after a chargeback');
    } catch (DomainError $e) {
        expect($e->error)->toBe('reinstatement_payment_required');
    }

    // staff may still decide to bring it back
    $staff = $this->staff();
    expect(driveOperation($services->requestAction($service->fresh(), 'resume', $this->contextFor($staff), 'cb-undo-staff', ['reason' => 'rozhodnutí podpory']))->state)->toBe(Operation::SUCCEEDED);
    app(OutboxPublisher::class)->relayPending();
    // … and it is billed again from today: the refunded period is not given a second time
    $subscription = Subscription::query()->where('service_id', $service->id)->firstOrFail();
    expect($subscription->state)->toBe(Subscription::ACTIVE)->and($subscription->next_renewal_at->toDateString())->toBe(now()->toDateString());
});

it('never resumes a chargeback-cancelled metered service for free, the switch on or off', function () {
    reinstateSwitchOn();
    [$owner, $org] = $this->customerWithOrganization();
    app(WalletService::class)->topup($org, Money::decimal('1000', 'CZK'), 'bank', 'seed', $this->contextFor($owner, $org));
    $service = reinstateCancelled($org, ['hold' => null, 'reason' => 'chargeback cb_2', 'period_end' => now()->addDays(20), 'operation_id' => 'op_chargeback_2']);
    $service->forceFill(['product_key' => 'vps'])->save(); // billed by the hour: a restore owes no new period, so its quote can be nothing
    ChargebackRequest::query()->create(['organization_id' => $org->id, 'service_id' => $service->id, 'requested_by' => $owner->id, 'state' => ChargebackRequest::REFUNDED, 'reason' => 'Odcházíme jinam.', 'percent' => 70, 'currency' => 'CZK',
        'unused_minor' => 24200, 'refund_minor' => 16940, 'operation_id' => 'op_chargeback_2', 'cancelled_at' => now()->subDays(5), 'refunded_at' => now()->subDays(5)]);
    $services = app(ServiceService::class);
    expect(app(ServiceReinstatement::class)->quote($service->fresh())['total_due']->minor)->toBe(0);

    try {
        $services->requestAction($service->fresh(), 'resume', $this->contextFor($owner, $org), 'cb-metered-resume');
        $this->fail('a free resume of a chargeback-cancelled metered service');
    } catch (DomainError $e) {
        expect($e->error)->toBe('chargeback_cancelled')->and($e->status)->toBe(409);
    }
    $this->actingAs($owner, 'sanctum');
    $this->withHeader('Idempotency-Key', 'cb-metered-pay')->postJson("/v1/services/{$service->id}/reinstate")->assertStatus(409)->assertJsonPath('error', 'reinstatement_refused')->assertJsonPath('reason', 'chargeback');
    $this->flushHeaders();

    $fresh = Service::query()->findOrFail($service->id);
    expect($fresh->state)->toBe(ServiceStateMachine::SUSPENDED)->and($fresh->terminate_at)->not->toBeNull()->and(reinstateCharges())->toBe(0)
        ->and(Operation::query()->where('service_id', $service->id)->count())->toBe(0);
});

it('makes a customer who restores a chargeback-cancelled service pay a whole new period, not the refunded one', function () {
    reinstateSwitchOn();
    [$owner, $org] = $this->customerWithOrganization();
    app(WalletService::class)->topup($org, Money::decimal('1000', 'CZK'), 'bank', 'seed', $this->contextFor($owner, $org));
    $service = reinstateCancelled($org, ['hold' => null, 'reason' => 'chargeback cb_3', 'period_end' => now()->addDays(20), 'operation_id' => 'op_chargeback_3']);
    ChargebackRequest::query()->create(['organization_id' => $org->id, 'service_id' => $service->id, 'requested_by' => $owner->id, 'state' => ChargebackRequest::REFUNDED, 'reason' => 'Odcházíme jinam.', 'percent' => 70, 'currency' => 'CZK',
        'unused_minor' => 24200, 'refund_minor' => 16940, 'operation_id' => 'op_chargeback_3', 'cancelled_at' => now()->subDays(5), 'refunded_at' => now()->subDays(5)]);
    $this->actingAs($owner, 'sanctum');

    // the pay endpoint itself, not only the plain resume: the paid period that the chargeback gave back does not cover it
    $this->withHeader('Idempotency-Key', 'cb-pay-1')->postJson("/v1/services/{$service->id}/reinstate")->assertStatus(202)->assertJsonPath('state', 'restoring')->assertJsonPath('charged.minor', 36300);
    $this->withHeader('Idempotency-Key', 'cb-pay-2')->postJson("/v1/services/{$service->id}/reinstate")->assertStatus(409); // a second click is not a second restore
    $this->flushHeaders();

    expect(reinstateCharges())->toBe(1)->and(Invoice::query()->where('type', 'statement')->where('meta->reinstatement', true)->sole()->total_minor)->toBe(36300)
        ->and(app(WalletService::class)->spendable($org, 'CZK')->minor)->toBe(100000 - 36300);
    $subscription = Subscription::query()->where('service_id', $service->id)->firstOrFail();
    expect($subscription->current_period_start->toDateString())->toBe(now()->toDateString());
});

it('never lifts an abuse or staff hold for money', function () {
    reinstateSwitchOn();
    [$owner, $org] = $this->customerWithOrganization();
    app(WalletService::class)->topup($org, Money::decimal('1000', 'CZK'), 'bank', 'seed', $this->contextFor($owner, $org));
    $service = reinstateCancelled($org, ['holds' => [SuspensionHold::PAYMENT, SuspensionHold::ABUSE]]);
    $this->actingAs($owner, 'sanctum');

    $this->withHeader('Idempotency-Key', 're-abuse-1')->postJson("/v1/services/{$service->id}/reinstate")->assertStatus(409)
        ->assertJsonPath('error', 'reinstatement_refused')->assertJsonPath('reason', 'held');
    expect(reinstateCharges())->toBe(0)->and(Operation::query()->where('service_id', $service->id)->count())->toBe(0)
        ->and(Service::query()->findOrFail($service->id)->terminate_at)->not->toBeNull();
});

it('refuses once the window is over and never touches a purged service', function () {
    reinstateSwitchOn();
    [$owner, $org] = $this->customerWithOrganization();
    app(WalletService::class)->topup($org, Money::decimal('1000', 'CZK'), 'bank', 'seed', $this->contextFor($owner, $org));
    $late = reinstateCancelled($org, ['reason' => 'subscription ended', 'terminate_at' => now()->subHour()]);
    $gone = reinstateCancelled($org, ['reason' => 'subscription ended', 'remote_id' => '1043']);
    $gone->forceFill(['state' => ServiceStateMachine::TERMINATED, 'terminated_at' => now()])->save();
    $gone->delete();
    $this->actingAs($owner, 'sanctum');

    $this->withHeader('Idempotency-Key', 're-late-1')->postJson("/v1/services/{$late->id}/reinstate")->assertStatus(409)->assertJsonPath('reason', 'window_closed');
    $this->withHeader('Idempotency-Key', 're-gone-1')->postJson("/v1/services/{$gone->id}/reinstate")->assertNotFound();
    expect(reinstateCharges())->toBe(0)->and(Operation::query()->count())->toBe(0);
});

it('does not let the purge take a paid service or the sites it carries', function () {
    reinstateSwitchOn();
    [$owner, $org] = $this->customerWithOrganization();
    app(WalletService::class)->topup($org, Money::decimal('1000', 'CZK'), 'bank', 'seed', $this->contextFor($owner, $org));
    $parent = reinstateCancelled($org, ['reason' => 'subscription ended']);
    $site = reinstateCancelled($org, ['hold' => null, 'reason' => 'zrušení služby', 'remote_id' => '1044', 'name' => 'Web 2', 'terminate_at' => now()->subMinute()]);
    $site->forceFill(['tags' => array_merge((array) $site->tags, ['billing' => 'included', 'included' => ['ended_by' => $parent->id, 'ended_at' => now()->subDays(5)->toIso8601String()]])])->save();
    $this->actingAs($owner, 'sanctum');

    Queue::fake(); // the resume is queued and has not run yet when the nightly purge comes first
    $this->withHeader('Idempotency-Key', 're-purge-1')->postJson("/v1/services/{$parent->id}/reinstate")->assertStatus(202)->assertJsonPath('state', 'restoring');
    $this->artisan('onhost:services:purge')->assertSuccessful();

    expect(Service::query()->findOrFail($parent->id)->terminate_at)->toBeNull();
    expect(Operation::query()->where('service_id', $site->id)->count())->toBe(0)->and(Service::query()->find($site->id)?->state)->toBe(ServiceStateMachine::SUSPENDED);
    expect(fn () => app(ServiceService::class)->requestAction($site->fresh(), 'purge', CommandContext::system('cli:services:purge'), 'purge-site-1', ['reason' => 'ochranná lhůta vypršela']))
        ->toThrow(DomainError::class, 'byla obnovena');
});

it('charges once and resumes once however often the request arrives', function () {
    reinstateSwitchOn();
    [$owner, $org] = $this->customerWithOrganization();
    app(WalletService::class)->topup($org, Money::decimal('1000', 'CZK'), 'bank', 'seed', $this->contextFor($owner, $org));
    $service = reinstateCancelled($org, ['reason' => 'subscription ended']);
    $this->actingAs($owner, 'sanctum');

    $first = $this->withHeader('Idempotency-Key', 're-twice')->postJson("/v1/services/{$service->id}/reinstate")->assertStatus(202)->json();
    $second = $this->withHeader('Idempotency-Key', 're-twice')->postJson("/v1/services/{$service->id}/reinstate")->assertStatus(202)->json();
    expect($second['operation_id'])->toBe($first['operation_id']);
    $this->withHeader('Idempotency-Key', 're-other')->postJson("/v1/services/{$service->id}/reinstate")->assertStatus(409)->assertJsonPath('reason', 'not_cancelled');

    expect(reinstateCharges())->toBe(1)->and(Operation::query()->where('service_id', $service->id)->count())->toBe(1)
        ->and(Invoice::query()->where('meta->reinstatement', true)->count())->toBe(1);
});

it('changes nothing while the switch is off', function () {
    [$owner, $org] = $this->customerWithOrganization();
    app(WalletService::class)->topup($org, Money::decimal('1000', 'CZK'), 'bank', 'seed', $this->contextFor($owner, $org));
    $service = reinstateCancelled($org, ['period_end' => now()->addDays(10)]);
    [$invoice] = reinstateDunnedInvoice($org, $service);
    $own = reinstateCancelled($org, ['hold' => null, 'reason' => 'customer request', 'period_end' => now()->addDays(15), 'remote_id' => '1045']);

    app(InvoiceService::class)->markPaid($invoice, $invoice->total(), 'bank', CommandContext::system('test')->withScope($org->id));
    app(OutboxPublisher::class)->relayPending();
    driveOperations();
    expect(Service::query()->findOrFail($service->id)->state)->toBe(ServiceStateMachine::SUSPENDED)->and(Service::query()->findOrFail($service->id)->terminate_at)->not->toBeNull()
        ->and(OutboxMessage::query()->where('name', 'service.reinstated')->exists())->toBeFalse();

    // an undone cancellation resumes as it always did, and its subscription is left as it was
    driveOperation(app(ServiceService::class)->requestAction($own, 'resume', $this->contextFor($owner, $org), 'undo-off-1'));
    app(OutboxPublisher::class)->relayPending();
    expect(Service::query()->findOrFail($own->id)->state)->toBe(ServiceStateMachine::ACTIVE)->and(Subscription::query()->where('service_id', $own->id)->value('state'))->toBe(Subscription::CANCELLED);

    $this->actingAs($owner, 'sanctum');
    $shown = $this->getJson("/v1/services/{$service->id}")->assertOk()->json('data');
    expect($shown['deletion']['pay_to_restore'])->toBeFalse()->and($shown['suspension']['message'])->toContain('může podpora')->not->toContain('Po úhradě ji obnovíme');
    expect($this->getJson("/v1/services/{$service->id}/reinstatement")->assertOk()->json('data'))->toMatchArray(['eligible' => false, 'reason' => 'disabled']);
    $this->withHeader('Idempotency-Key', 're-off-1')->postJson("/v1/services/{$service->id}/reinstate")->assertStatus(409)->assertJsonPath('reason', 'disabled');
    expect(reinstateCharges())->toBe(0);
});

it('keeps the renewal day of a period that was started again', function () {
    Date::setTestNow(Carbon::parse('2026-03-15 10:00:00'));
    reinstateSwitchOn();
    [$owner, $org] = $this->customerWithOrganization();
    app(WalletService::class)->topup($org, Money::decimal('1000', 'CZK'), 'bank', 'seed', $this->contextFor($owner, $org));
    $service = reinstateCancelled($org, ['reason' => 'subscription ended', 'activated_at' => Carbon::parse('2026-01-31 09:00:00'), 'period_end' => Carbon::parse('2026-02-28 09:00:00')]);

    $result = app(ServiceReinstatement::class)->reinstate($service, CommandContext::system('test')->withScope($org->id), 'anchor-1');
    expect($result['state'])->toBe('restoring');
    $subscription = Subscription::query()->where('service_id', $service->id)->firstOrFail();
    expect($subscription->current_period_end->toDateString())->toBe('2026-04-15');

    // the next renewal follows the day the period started again, not the 31st the service was first activated on
    expect(app(SubscriptionService::class)->renew($subscription, $service->fresh(), CommandContext::system('test')->withScope($org->id)))->toBe('renewed');
    expect($subscription->fresh()->current_period_end->toDateString())->toBe('2026-05-15');
    Date::setTestNow();
});

it('lets only who may spend the credit pay for a restore', function () {
    reinstateSwitchOn();
    config(['onhost.orders.credit_approval.enabled' => true]); // the one credit gate (TASK-0027): CreditOrderPolicy decides who spends
    [$owner, $org] = $this->customerWithOrganization();
    app(WalletService::class)->topup($org, Money::decimal('1000', 'CZK'), 'bank', 'seed', $this->contextFor($owner, $org));
    $service = reinstateCancelled($org, ['reason' => 'subscription ended']);
    $organizations = app(OrganizationService::class);
    $admin = $this->customer();
    $organizations->attachMember($org, $admin, 'org_admin', CommandContext::system('test'), true);
    $billing = $this->customer();
    $organizations->attachMember($org, $billing, 'billing_admin', CommandContext::system('test'), true);

    $this->actingAs($admin, 'sanctum');
    $this->withHeader('Idempotency-Key', 're-admin-1')->postJson("/v1/services/{$service->id}/reinstate")->assertForbidden()
        ->assertJsonPath('error', 'credit_spend_not_allowed')->assertJsonPath('permission', 'billing.wallet.spend');
    expect(reinstateCharges())->toBe(0);

    $this->actingAs($billing, 'sanctum');
    $this->withHeader('Idempotency-Key', 're-billing-1')->postJson("/v1/services/{$service->id}/reinstate")->assertStatus(202)->assertJsonPath('state', 'restoring');
    expect(reinstateCharges())->toBe(1);
});

/* ── review round 2 ──────────────────────────────────────────────────────────────────────────────────────────────── */

/** A member bound to `$role` at the organization, or — `$serviceId` given — a guest of that one service. */
function reinstateBind(Organization $org, User $user, string $role, ?string $serviceId = null): void
{
    PolicyBinding::query()->create(['principal_type' => 'user', 'principal_id' => $user->id, 'role_key' => $role,
        'scope_type' => $serviceId === null ? 'organization' : 'resource', 'scope_id' => $serviceId ?? $org->id, 'organization_id' => $org->id]);
}

/** The service is cancelled once more (a new cancellation operation), the payment hold on it again. */
function reinstateCancelAgain(Service $service, string $operationId): Service
{
    $service = $service->fresh(); // as it is now (running again), so every column below is written
    $tags = (array) $service->tags;
    $tags['suspension'] = ['holds' => [SuspensionHold::PAYMENT => ['reason' => 'subscription ended', 'by' => 'system', 'at' => now()->toIso8601String()]]];
    $tags['deletion'] = ['requested_at' => now()->toIso8601String(), 'grace_until' => now()->addDays(30)->toIso8601String(), 'grace_days' => 30, 'reason' => 'subscription ended', 'operation_id' => $operationId, 'subscription' => ['state' => 'active', 'auto_renew' => true]];
    $service->forceFill(['state' => ServiceStateMachine::SUSPENDED, 'suspended_at' => now(), 'suspended_reason' => 'subscription ended', 'terminate_at' => now()->addDays(30), 'tags' => $tags])->save();
    Subscription::query()->where('service_id', $service->id)->update(['state' => Subscription::CANCELLED, 'auto_renew' => false]);

    return $service->refresh();
}

it('lets only who may spend the credit take back a cancellation that bills again, and shows the credit only to who may read it', function () {
    reinstateSwitchOn();
    config(['onhost.orders.credit_approval.enabled' => true]); // the one credit gate (TASK-0027)
    [$owner, $org] = $this->customerWithOrganization();
    $covered = reinstateCancelled($org, ['hold' => null, 'reason' => 'customer request', 'period_end' => now()->addDays(15)]);
    $ended = reinstateCancelled($org, ['hold' => null, 'reason' => 'customer request', 'period_end' => now()->subDay(), 'remote_id' => '1046']);
    $guest = $this->customer();
    reinstateBind($org, $guest, 'svc_manage', $covered->id);
    reinstateBind($org, $guest, 'svc_manage', $ended->id);
    $admin = $this->customer();
    app(OrganizationService::class)->attachMember($org, $admin, 'org_admin', CommandContext::system('test'), true);

    // a guest of the service and an org_admin (no billing.wallet.spend) cannot commit the organization to renewals again
    foreach ([[$guest, 'g'], [$admin, 'a']] as [$who, $tag]) {
        $this->actingAs($who, 'sanctum');
        foreach ([$covered, $ended] as $i => $service) {
            $body = $this->withHeader('Idempotency-Key', "r2-undo-{$tag}-{$i}")->postJson("/v1/services/{$service->id}/actions", ['action' => 'resume'])->assertForbidden()->assertJsonPath('error', 'credit_spend_not_allowed')->json();
            expect(json_encode($body))->not->toContain('wallet_available')->not->toContain('outstanding_invoices');
        }
    }
    $this->flushHeaders();
    expect(Operation::query()->whereIn('service_id', [$covered->id, $ended->id])->count())->toBe(0)
        ->and(Subscription::query()->whereIn('service_id', [$covered->id, $ended->id])->pluck('state')->unique()->values()->all())->toBe([Subscription::CANCELLED])
        ->and(Service::query()->findOrFail($covered->id)->terminate_at)->not->toBeNull();

    // who may spend but not read the credit is told the price and the way to pay, not the organization's balance (asking for a
    // restore is the restore command's own permission, billing.wallet.topup — TASK-0027 review round 1)
    DB::table('roles')->insert(['key' => 'test_spend_only', 'name' => 'Spend only', 'scope_type' => 'organization', 'is_staff' => false, 'assignable' => false, 'created_at' => now(), 'updated_at' => now()]);
    DB::table('role_permissions')->insert([['role_key' => 'test_spend_only', 'permission_key' => 'service.read'], ['role_key' => 'test_spend_only', 'permission_key' => 'service.manage'], ['role_key' => 'test_spend_only', 'permission_key' => 'billing.wallet.spend'], ['role_key' => 'test_spend_only', 'permission_key' => 'billing.wallet.topup']]);
    $spender = $this->customer();
    reinstateBind($org, $spender, 'test_spend_only');
    $this->actingAs($spender, 'sanctum');
    $refused = $this->withHeader('Idempotency-Key', 'r2-undo-s')->postJson("/v1/services/{$ended->id}/actions", ['action' => 'resume'])->assertStatus(402)->assertJsonPath('error', 'reinstatement_payment_required')->json();
    expect($refused['quote']['total_due']['minor'])->toBe(36300)->and($refused['pay'])->toBe("/v1/services/{$ended->id}/reinstate")
        ->and(json_encode($refused))->not->toContain('wallet_available')->not->toContain('outstanding_invoices');

    // the owner sees the whole quote, and takes back the covered cancellation
    $this->actingAs($owner, 'sanctum');
    $full = $this->withHeader('Idempotency-Key', 'r2-undo-o1')->postJson("/v1/services/{$ended->id}/actions", ['action' => 'resume'])->assertStatus(402)->json();
    expect($full['quote'])->toHaveKeys(['wallet_available', 'shortfall', 'outstanding_invoices']);
    $this->withHeader('Idempotency-Key', 'r2-undo-o2')->postJson("/v1/services/{$covered->id}/actions", ['action' => 'resume'])->assertStatus(202);
    $this->flushHeaders();
});

it('forgets a restore request once its cancellation is over: a later cancellation is never paid for by the next top-up', function () {
    reinstateSwitchOn();
    [$owner, $org] = $this->customerWithOrganization();
    app(WalletService::class)->topup($org, Money::decimal('100', 'CZK'), 'bank', 'seed', $this->contextFor($owner, $org));
    $service = reinstateCancelled($org, ['reason' => 'subscription ended', 'operation_id' => 'op_first_cancel']);
    $this->actingAs($owner, 'sanctum');
    $this->withHeader('Idempotency-Key', 'r2-wish-1')->postJson("/v1/services/{$service->id}/reinstate")->assertStatus(202)->assertJsonPath('state', 'awaiting_payment');
    $this->flushHeaders();
    $wish = data_get(Service::query()->findOrFail($service->id)->tags, 'reinstatement');
    expect($wish)->toBeArray();

    // staff bring it back on their own; the request of the old cancellation goes with it
    $staff = $this->staff();
    expect(driveOperation(app(ServiceService::class)->requestAction($service->fresh(), 'resume', $this->contextFor($staff), 'r2-staff-resume', ['reason' => 'rozhodnutí podpory']))->state)->toBe(Operation::SUCCEEDED);
    expect(data_get(Service::query()->findOrFail($service->id)->tags, 'reinstatement'))->toBeNull();

    // cancelled again later — the customer chose it; the credit that arrives next pays for nothing
    $again = reinstateCancelAgain($service, 'op_second_cancel');
    app(WalletService::class)->topup($org, Money::decimal('1000', 'CZK'), 'bank', 'seed-2', $this->contextFor($owner, $org));
    app(OutboxPublisher::class)->relayPending();
    expect(reinstateCharges())->toBe(0)->and(Service::query()->findOrFail($service->id)->terminate_at)->not->toBeNull();

    // even a request that somehow outlived its cancellation is not honoured for the next one: it is dropped
    $again->forceFill(['tags' => array_merge((array) $again->fresh()->tags, ['reinstatement' => $wish])])->save();
    app(WalletService::class)->topup($org, Money::decimal('1000', 'CZK'), 'bank', 'seed-3', $this->contextFor($owner, $org));
    app(OutboxPublisher::class)->relayPending();
    driveOperations();
    $fresh = Service::query()->findOrFail($service->id);
    expect(reinstateCharges())->toBe(0)->and($fresh->terminate_at)->not->toBeNull()->and($fresh->state)->toBe(ServiceStateMachine::SUSPENDED)
        ->and(data_get($fresh->tags, 'reinstatement'))->toBeNull();
});

it('drops a recorded restore request when whoever asked may no longer spend the credit', function () {
    reinstateSwitchOn();
    [$owner, $org] = $this->customerWithOrganization();
    app(WalletService::class)->topup($org, Money::decimal('100', 'CZK'), 'bank', 'seed', $this->contextFor($owner, $org));
    $service = reinstateCancelled($org, ['reason' => 'subscription ended']);
    $billing = $this->customer();
    app(OrganizationService::class)->attachMember($org, $billing, 'billing_admin', CommandContext::system('test'), true);
    $this->actingAs($billing, 'sanctum');
    $this->withHeader('Idempotency-Key', 'r2-who-1')->postJson("/v1/services/{$service->id}/reinstate")->assertStatus(202)->assertJsonPath('state', 'awaiting_payment');
    $this->flushHeaders();

    // the billing admin loses the role; somebody else adds the missing credit
    PolicyBinding::query()->where('principal_id', $billing->id)->delete();
    app(Authorizer::class)->forget($billing);
    app(WalletService::class)->topup($org, Money::decimal('1000', 'CZK'), 'bank', 'seed-2', $this->contextFor($owner, $org));
    app(OutboxPublisher::class)->relayPending();

    $fresh = Service::query()->findOrFail($service->id);
    expect(reinstateCharges())->toBe(0)->and($fresh->terminate_at)->not->toBeNull()->and(data_get($fresh->tags, 'reinstatement'))->toBeNull()
        ->and(OutboxMessage::query()->where('name', 'service.reinstatement.dropped')->where('aggregate_id', $service->id)->exists())->toBeTrue();
});

it('takes nothing when the resume is refused right after the charge', function () {
    reinstateSwitchOn();
    [$owner, $org] = $this->customerWithOrganization();
    app(WalletService::class)->topup($org, Money::decimal('1000', 'CZK'), 'bank', 'seed', $this->contextFor($owner, $org));
    $service = reinstateCancelled($org, ['reason' => 'subscription ended']);
    app(FreezeSwitch::class)->freeze('incident', 'test'); // the resume is refused on the spot
    $this->actingAs($owner, 'sanctum');

    $this->withHeader('Idempotency-Key', 'r2-frozen')->postJson("/v1/services/{$service->id}/reinstate")->assertStatus(409)
        ->assertJsonPath('error', 'reinstatement_refused')->assertJsonPath('reason', 'resume_refused');
    $this->flushHeaders();
    app(FreezeSwitch::class)->thaw();

    $fresh = Service::query()->findOrFail($service->id);
    expect(reinstateCharges())->toBe(0)->and(app(WalletService::class)->spendable($org, 'CZK')->minor)->toBe(100000)
        ->and(Invoice::query()->where('meta->reinstatement', true)->count())->toBe(0)
        ->and($fresh->terminate_at)->not->toBeNull()->and(Subscription::query()->where('service_id', $service->id)->value('state'))->toBe(Subscription::CANCELLED);
});

/* ── review round 3: auto-renew is never switched on without the customer ─────────────────────────────────────────── */

/**
 * The customer's subscription ends the way `tick()` ends it — auto-renew switched off, or the end asked for with the
 * period — and the terminate saga cancels the service. The saga records the customer's choice only for a subscription
 * that is not CANCELLED yet, so an expired one leaves no record (`deletion.subscription` missing). An earlier, undone
 * cancellation had recorded auto-renew on.
 *
 * @param  array<string,mixed>  $choice  the subscription columns as the customer left them
 */
function reinstateExpiredByChoice(Service $service, array $choice): Service
{
    $cancelled = (array) $service->fresh()->tags;
    $service->forceFill(['state' => ServiceStateMachine::ACTIVE, 'terminate_at' => null, 'suspended_at' => null, 'suspended_reason' => null, 'tags' => array_diff_key($cancelled, ['deletion' => 1, 'suspension' => 1])])->save();
    Subscription::query()->where('service_id', $service->id)->update(['state' => Subscription::ACTIVE] + $choice);
    expect(app(SubscriptionService::class)->tick()['cancelled'])->toBe(1);
    $expired = Subscription::query()->where('service_id', $service->id)->firstOrFail();
    expect($expired->state)->toBe(Subscription::CANCELLED)->and($expired->auto_renew)->toBeFalse();
    Operation::query()->where('service_id', $service->id)->update(['state' => Operation::SUCCEEDED]); // the terminate saga ran …
    $deletion = (array) $cancelled['deletion'];
    unset($deletion['subscription']); // … and found no subscription that was not CANCELLED already
    $tags = array_replace($cancelled, ['deletion' => $deletion, 'deletion_cancelled' => ['operation_id' => 'op_older', 'cancelled_at' => now()->subMonths(2)->toIso8601String(), 'subscription' => ['state' => 'active', 'auto_renew' => true]]]);
    $service->forceFill(['state' => ServiceStateMachine::SUSPENDED, 'suspended_at' => now(), 'suspended_reason' => 'subscription ended', 'terminate_at' => now()->addDays(20), 'tags' => $tags])->save();

    return $service->refresh();
}

it('never switches auto-renew back on when a customer who had switched it off pays to restore', function () {
    reinstateSwitchOn();
    [$owner, $org] = $this->customerWithOrganization();
    $org->forceFill(['auto_renew_default' => true])->save();
    app(WalletService::class)->topup($org, Money::decimal('1000', 'CZK'), 'bank', 'seed', $this->contextFor($owner, $org));
    $service = reinstateExpiredByChoice(reinstateCancelled($org, ['reason' => 'subscription ended']), ['auto_renew' => false, 'cancel_at_period_end' => false]);
    $this->actingAs($owner, 'sanctum');

    $this->withHeader('Idempotency-Key', 'r3-off-1')->postJson("/v1/services/{$service->id}/reinstate")->assertStatus(202)->assertJsonPath('state', 'restoring');
    $subscription = Subscription::query()->where('service_id', $service->id)->firstOrFail();
    expect(reinstateCharges())->toBe(1)->and($subscription->state)->toBe(Subscription::ACTIVE)->and($subscription->auto_renew)->toBeFalse();

    // the paid period runs out: it ends as the customer chose, the next one is never charged from the credit
    $spendable = app(WalletService::class)->spendable($org, 'CZK')->minor;
    $this->travelTo($subscription->current_period_end->copy()->addDay());
    app(SubscriptionService::class)->tick();
    expect(Subscription::query()->where('service_id', $service->id)->value('state'))->toBe(Subscription::CANCELLED)
        ->and(app(WalletService::class)->spendable($org, 'CZK')->minor)->toBe($spendable)
        ->and(Invoice::query()->where('type', 'statement')->where('meta->subscription_id', $subscription->id)->count())->toBe(1);
});

it('keeps a subscription that was to end with its period ending after a paid restore', function () {
    reinstateSwitchOn();
    [$owner, $org] = $this->customerWithOrganization();
    $org->forceFill(['auto_renew_default' => true])->save();
    app(WalletService::class)->topup($org, Money::decimal('1000', 'CZK'), 'bank', 'seed', $this->contextFor($owner, $org));
    $service = reinstateCancelled($org, ['reason' => 'subscription ended']);
    $subscription = Subscription::query()->where('service_id', $service->id)->firstOrFail();
    $subscription->forceFill(['state' => Subscription::ACTIVE, 'auto_renew' => true])->save();
    app(SubscriptionService::class)->cancelAtPeriodEnd($subscription, true, $this->contextFor($owner, $org)); // the customer's own "end it with the period"
    $service = reinstateExpiredByChoice($service, []);

    $result = app(ServiceReinstatement::class)->reinstate($service, $this->contextFor($owner, $org), 'r3-cape-1');
    expect($result['state'])->toBe('restoring')->and(reinstateCharges())->toBe(1);
    $back = Subscription::query()->where('service_id', $service->id)->firstOrFail();
    expect($back->state)->toBe(Subscription::ACTIVE)->and($back->auto_renew)->toBeFalse()->and($back->cancel_at_period_end)->toBeFalse();
});

it('keeps auto-renew off when a customer takes back a cancellation with nothing recorded', function () {
    reinstateSwitchOn();
    [$owner, $org] = $this->customerWithOrganization();
    $org->forceFill(['auto_renew_default' => true])->save();
    $service = reinstateCancelled($org, ['hold' => null, 'reason' => 'customer request', 'period_end' => now()->addDays(15)]);
    $tags = (array) $service->tags;
    unset($tags['deletion']['subscription']); // a cancellation from before the saga recorded the choice
    $service->forceFill(['tags' => $tags])->save();

    driveOperation(app(ServiceService::class)->requestAction($service->fresh(), 'resume', $this->contextFor($owner, $org), 'r3-undo-1'));
    app(OutboxPublisher::class)->relayPending();

    $subscription = Subscription::query()->where('service_id', $service->id)->firstOrFail();
    expect($subscription->state)->toBe(Subscription::ACTIVE)->and($subscription->auto_renew)->toBeFalse()->and(reinstateCharges())->toBe(0);
});

/* ── TASK-0027: one gate for spending the credit ─────────────────────────────────────────────────────────────────────
 * Paying for a restore, taking back a cancellation that bills again and a recorded restore request all spend the
 * organization's credit. They ask the same gate as every other credit payment (Orders\CreditOrderPolicy, TASK-0021): open to
 * whoever may pay from the credit while `onhost.orders.credit_approval.enabled` is off, only the owner and the billing admin
 * once it is on, refused with the same `credit_spend_not_allowed`.
 */

function reinstateOrgAdmin(Organization $org): User
{
    $admin = test()->customer();
    app(OrganizationService::class)->attachMember($org, $admin, 'org_admin', CommandContext::system('test'), true);

    return $admin;
}

it('pays for a restore under the one credit gate: an org_admin may while credit approval is off, not once it is on', function () {
    reinstateSwitchOn();
    [$owner, $org] = $this->customerWithOrganization();
    app(WalletService::class)->topup($org, Money::decimal('1000', 'CZK'), 'bank', 'seed', $this->contextFor($owner, $org));
    $first = reinstateCancelled($org, ['reason' => 'subscription ended']);
    $second = reinstateCancelled($org, ['reason' => 'subscription ended', 'remote_id' => '1046']);
    $this->actingAs(reinstateOrgAdmin($org), 'sanctum');

    config(['onhost.orders.credit_approval.enabled' => false]); // as an invoice paid from the credit: whoever may pay one
    $this->withHeader('Idempotency-Key', 'g1-off')->postJson("/v1/services/{$first->id}/reinstate")->assertStatus(202)->assertJsonPath('state', 'restoring');
    expect(reinstateCharges())->toBe(1);

    config(['onhost.orders.credit_approval.enabled' => true]);
    $this->withHeader('Idempotency-Key', 'g1-on')->postJson("/v1/services/{$second->id}/reinstate")->assertForbidden()
        ->assertJsonPath('error', 'credit_spend_not_allowed')->assertJsonPath('permission', 'billing.wallet.spend');
    $this->flushHeaders();
    expect(reinstateCharges())->toBe(1)->and(Service::query()->findOrFail($second->id)->terminate_at)->not->toBeNull();

    // a member who may not pay from the credit at all (a developer) is refused by the bus whatever the switch says
    $developer = $this->customer();
    reinstateBind($org, $developer, 'developer');
    $this->actingAs($developer, 'sanctum');
    config(['onhost.orders.credit_approval.enabled' => false]);
    $this->withHeader('Idempotency-Key', 'g1-dev')->postJson("/v1/services/{$second->id}/reinstate")->assertForbidden();
    $this->flushHeaders();
    expect(reinstateCharges())->toBe(1);
});

it('takes back a cancellation that bills again under the one credit gate, the switch off or on', function () {
    reinstateSwitchOn();
    [, $org] = $this->customerWithOrganization();
    $off = reinstateCancelled($org, ['hold' => null, 'reason' => 'customer request', 'period_end' => now()->addDays(15)]);
    $on = reinstateCancelled($org, ['hold' => null, 'reason' => 'customer request', 'period_end' => now()->addDays(15), 'remote_id' => '1046']);
    $this->actingAs(reinstateOrgAdmin($org), 'sanctum');

    config(['onhost.orders.credit_approval.enabled' => false]);
    $this->withHeader('Idempotency-Key', 'g2-off')->postJson("/v1/services/{$off->id}/actions", ['action' => 'resume'])->assertStatus(202);

    config(['onhost.orders.credit_approval.enabled' => true]);
    $this->withHeader('Idempotency-Key', 'g2-on')->postJson("/v1/services/{$on->id}/actions", ['action' => 'resume'])->assertForbidden()
        ->assertJsonPath('error', 'credit_spend_not_allowed')->assertJsonPath('permission', 'billing.wallet.spend');
    $this->flushHeaders();
    expect(Operation::query()->where('service_id', $on->id)->count())->toBe(0)->and(Service::query()->findOrFail($on->id)->terminate_at)->not->toBeNull()
        ->and(Subscription::query()->where('service_id', $on->id)->value('state'))->toBe(Subscription::CANCELLED);
});

it('pays a recorded restore request under the one credit gate: fulfilled while credit approval is off, dropped once it is on', function () {
    reinstateSwitchOn();
    config(['onhost.orders.credit_approval.enabled' => false]);
    [$owner, $org] = $this->customerWithOrganization();
    app(WalletService::class)->topup($org, Money::decimal('100', 'CZK'), 'bank', 'seed', $this->contextFor($owner, $org));
    $kept = reinstateCancelled($org, ['reason' => 'subscription ended', 'terminate_at' => now()->addDays(10)]);
    $dropped = reinstateCancelled($org, ['reason' => 'subscription ended', 'terminate_at' => now()->addDays(20), 'remote_id' => '1046']);
    $this->actingAs(reinstateOrgAdmin($org), 'sanctum');
    foreach ([$kept, $dropped] as $i => $service) {
        $this->withHeader('Idempotency-Key', "g3-wish-{$i}")->postJson("/v1/services/{$service->id}/reinstate")->assertStatus(202)->assertJsonPath('state', 'awaiting_payment');
    }
    $this->flushHeaders();

    // enough for one: the earlier window is paid for while the switch is off, the other still waits
    app(WalletService::class)->topup($org, Money::decimal('300', 'CZK'), 'bank', 'seed-2', $this->contextFor($owner, $org));
    app(OutboxPublisher::class)->relayPending();
    driveOperations();
    expect(reinstateCharges())->toBe(1)->and(Service::query()->findOrFail($kept->id)->terminate_at)->toBeNull()
        ->and(data_get(Service::query()->findOrFail($dropped->id)->tags, 'reinstatement'))->toBeArray();

    // the switch goes on before the rest arrives: whoever asked (an org_admin) may no longer spend the credit
    config(['onhost.orders.credit_approval.enabled' => true]);
    app(WalletService::class)->topup($org, Money::decimal('1000', 'CZK'), 'bank', 'seed-3', $this->contextFor($owner, $org));
    app(OutboxPublisher::class)->relayPending();
    $fresh = Service::query()->findOrFail($dropped->id);
    expect(reinstateCharges())->toBe(1)->and($fresh->terminate_at)->not->toBeNull()->and(data_get($fresh->tags, 'reinstatement'))->toBeNull()
        ->and(OutboxMessage::query()->where('name', 'service.reinstatement.dropped')->where('aggregate_id', $dropped->id)->exists())->toBeTrue();
});

/* ── TASK-0027: a restore keeps the auto-renew the subscription had ──────────────────────────────────────────────────
 * Whoever triggers the restore — a payment, staff, the customer — auto-renew stays exactly what it was before the
 * cancellation: the terminate saga's record for this subscription, else the cancelled row's own value, nothing = off. Only a
 * holder of the credit switches it on (TASK-0021).
 */

it('keeps auto-renew off when staff bring back a service whose paid period had ended', function () {
    reinstateSwitchOn();
    [$owner, $org] = $this->customerWithOrganization();
    $org->forceFill(['auto_renew_default' => true])->save();
    app(WalletService::class)->topup($org, Money::decimal('1000', 'CZK'), 'bank', 'seed', $this->contextFor($owner, $org));
    $service = reinstateCancelled($org, ['hold' => null, 'reason' => 'customer request', 'period_end' => now()->subDay(), 'auto_renew' => false]);

    $staff = $this->staff();
    expect(driveOperation(app(ServiceService::class)->requestAction($service, 'resume', $this->contextFor($staff), 'c2-staff-1', ['reason' => 'rozhodnutí podpory']))->state)->toBe(Operation::SUCCEEDED);
    app(OutboxPublisher::class)->relayPending();

    $subscription = Subscription::query()->where('service_id', $service->id)->firstOrFail();
    expect($subscription->state)->toBe(Subscription::ACTIVE)->and($subscription->auto_renew)->toBeFalse()->and(reinstateCharges())->toBe(0);

    // the renewal pass never charges the credit for a period nobody agreed to
    $spendable = app(WalletService::class)->spendable($org, 'CZK')->minor;
    app(SubscriptionService::class)->tick();
    expect(app(WalletService::class)->spendable($org, 'CZK')->minor)->toBe($spendable)
        ->and(OutboxMessage::query()->where('name', 'subscription.renewed')->where('aggregate_id', $subscription->id)->exists())->toBeFalse();
});

it('keeps auto-renew on when staff bring back a service whose customer had it on', function () {
    reinstateSwitchOn();
    [, $org] = $this->customerWithOrganization();
    $service = reinstateCancelled($org, ['hold' => null, 'reason' => 'customer request', 'period_end' => now()->subDay(), 'auto_renew' => true]);

    driveOperation(app(ServiceService::class)->requestAction($service, 'resume', $this->contextFor($this->staff()), 'c2-staff-on', ['reason' => 'rozhodnutí podpory']));
    app(OutboxPublisher::class)->relayPending();

    expect(Subscription::query()->where('service_id', $service->id)->value('auto_renew'))->toBeTrue();
});

it('keeps auto-renew off when a paid invoice brings back a service dunning cancelled', function () {
    reinstateSwitchOn();
    [, $org] = $this->customerWithOrganization();
    $org->forceFill(['auto_renew_default' => true])->save();
    $service = reinstateCancelled($org, ['period_end' => now()->addDays(10), 'auto_renew' => false]);
    [$invoice] = reinstateDunnedInvoice($org, $service);

    app(InvoiceService::class)->markPaid($invoice, $invoice->total(), 'bank', CommandContext::system('test')->withScope($org->id));
    app(OutboxPublisher::class)->relayPending();
    driveOperations();
    app(OutboxPublisher::class)->relayPending();

    $subscription = Subscription::query()->where('service_id', $service->id)->firstOrFail();
    expect(Service::query()->findOrFail($service->id)->terminate_at)->toBeNull()
        ->and($subscription->state)->toBe(Subscription::ACTIVE)->and($subscription->auto_renew)->toBeFalse();
});

it('takes the cancelled row\'s own auto-renew when the cancellation recorded none, and off when neither says so', function () {
    reinstateSwitchOn();
    [$owner, $org] = $this->customerWithOrganization();
    $org->forceFill(['auto_renew_default' => false])->save();
    $kept = reinstateCancelled($org, ['hold' => null, 'reason' => 'customer request', 'period_end' => now()->addDays(15)]);
    $unset = reinstateCancelled($org, ['hold' => null, 'reason' => 'customer request', 'period_end' => now()->addDays(15), 'remote_id' => '1046']);
    foreach ([$kept, $unset] as $service) {
        $tags = (array) $service->tags;
        unset($tags['deletion']['subscription']); // a cancellation that recorded nothing
        $service->forceFill(['tags' => $tags])->save();
    }
    Subscription::query()->where('service_id', $kept->id)->update(['auto_renew' => true]); // the row itself still says on

    foreach ([$kept, $unset] as $i => $service) {
        driveOperation(app(ServiceService::class)->requestAction($service->fresh(), 'resume', $this->contextFor($owner, $org), "c2-row-{$i}"));
    }
    app(OutboxPublisher::class)->relayPending();

    expect(Subscription::query()->where('service_id', $kept->id)->value('auto_renew'))->toBeTrue()
        ->and(Subscription::query()->where('service_id', $unset->id)->value('auto_renew'))->toBeFalse();
});

/* ── TASK-0027 review round 1 ──────────────────────────────────────────────────────────────────────────────────────────
 * Taking back a cancellation that bills again asks first whether the actor may ask for a restore at all (the command's own
 * permission, `billing.wallet.topup`, as a recorded request does), then the one credit gate. With credit approval off a
 * developer, a guest of the one service, their `services:power` token and an assistant are refused as they were before the
 * gate was unified; an org_admin still may. A service staff brought back whose customer had auto-renew off ends again at the
 * next renewal pass — also when an earlier expiry of the same subscription had already asked for a termination.
 */

/** A service action as the bus runs it for `$context` (the staff console and the customer portal send the same command). @return array<string,mixed> */
function reinstateServiceAction(Service $service, string $action, CommandContext $context, string $key, array $params = []): array
{
    return app(CommandBus::class)->dispatch(new ServiceActionCommand($service->organization_id, $key, ['service_id' => $service->id, 'action' => $action, 'params' => $params]), $context);
}

it('refuses a developer, a guest of the service, their token and an assistant the undo of a cancellation while credit approval is off', function () {
    reinstateSwitchOn();
    config(['onhost.orders.credit_approval.enabled' => false]);
    [$owner, $org] = $this->customerWithOrganization();
    $service = reinstateCancelled($org, ['hold' => null, 'reason' => 'customer request', 'period_end' => now()->addDays(15)]);
    $guest = $this->customer();
    reinstateBind($org, $guest, 'svc_manage', $service->id);
    $developer = $this->customer();
    reinstateBind($org, $developer, 'developer');

    foreach ([[$guest, 'g'], [$developer, 'd']] as [$who, $tag]) {
        $this->actingAs($who, 'sanctum');
        $this->withHeader('Idempotency-Key', "rr1-undo-{$tag}")->postJson("/v1/services/{$service->id}/actions", ['action' => 'resume'])->assertForbidden()
            ->assertJsonPath('error', 'credit_spend_not_allowed')->assertJsonPath('permission', 'billing.wallet.topup');
    }
    $this->flushHeaders();

    $pat = $developer->createToken('ci', ['services:read', 'services:power']);
    $pat->accessToken->forceFill(['organization_id' => $org->id])->save();
    app('auth')->forgetGuards();
    $this->withToken($pat->plainTextToken)->postJson("/v1/services/{$service->id}/actions", ['action' => 'resume'], ['X-Organization' => $org->id, 'Idempotency-Key' => 'rr1-undo-t'])
        ->assertForbidden()->assertJsonPath('error', 'credit_spend_not_allowed');
    app('auth')->forgetGuards();
    $this->flushHeaders();

    $assistant = new CommandContext('ai', 'run_rr1', $org->id, onBehalfOfUserId: $owner->id);
    expect(fn () => app(ServiceReinstatement::class)->assertCustomerMayResume($service->fresh(), $assistant))->toThrow(DomainError::class);

    expect(Operation::query()->where('service_id', $service->id)->count())->toBe(0)
        ->and(Service::query()->findOrFail($service->id)->terminate_at)->not->toBeNull()
        ->and(Subscription::query()->where('service_id', $service->id)->value('state'))->toBe(Subscription::CANCELLED);

    // an org_admin may pay the organization's invoices from its credit, and so may take the cancellation back
    $this->actingAs(reinstateOrgAdmin($org), 'sanctum');
    $this->withHeader('Idempotency-Key', 'rr1-undo-a')->postJson("/v1/services/{$service->id}/actions", ['action' => 'resume'])->assertStatus(202);
    $this->flushHeaders();
});

it('lets staff pay for a customer\'s restore while credit approval is on, as a recorded request of staff is paid', function () {
    reinstateSwitchOn();
    config(['onhost.orders.credit_approval.enabled' => true]);
    [$owner, $org] = $this->customerWithOrganization();
    app(WalletService::class)->topup($org, Money::decimal('1000', 'CZK'), 'bank', 'seed', $this->contextFor($owner, $org));
    $service = reinstateCancelled($org, ['reason' => 'subscription ended']);

    $result = app(ServiceReinstatement::class)->reinstate($service, $this->contextFor($this->staff('cloud_vps_admin')), 'rr1-staff-pay');

    expect($result['state'])->toBe('restoring')->and(reinstateCharges())->toBe(1);
});

it('ends a restored service again at the renewal pass even when an earlier expiry of its subscription asked for a termination', function () {
    reinstateSwitchOn();
    [, $org] = $this->customerWithOrganization();
    $org->forceFill(['auto_renew_default' => true])->save();
    // the customer switched auto-renew off; tick() expired the subscription and asked for the termination (`sub_expire:…`)
    $service = reinstateExpiredByChoice(reinstateCancelled($org, ['hold' => null, 'reason' => 'subscription ended']), ['auto_renew' => false, 'cancel_at_period_end' => false]);
    $subscription = Subscription::query()->where('service_id', $service->id)->firstOrFail();
    expect(Operation::query()->where('service_id', $service->id)->where('idempotency_key', 'like', 'sub_expire:%')->count())->toBe(1);

    // staff bring it back: billing restarts today with auto-renew off, and staff are told it will end again
    $answer = reinstateServiceAction($service, 'resume', $this->contextFor($this->staff(), $org), 'rr1-staff-resume', ['reason' => 'rozhodnutí podpory']);
    expect(data_get($answer, 'warning.code'))->toBe('restore_ends_at_renewal');
    driveOperation(Operation::query()->findOrFail($answer['operation_id']));
    app(OutboxPublisher::class)->relayPending();
    $restarted = Subscription::query()->findOrFail($subscription->id);
    expect($restarted->state)->toBe(Subscription::ACTIVE)->and($restarted->auto_renew)->toBeFalse()
        ->and(Service::query()->findOrFail($service->id)->state)->toBe(ServiceStateMachine::ACTIVE);

    // the renewal pass ends it again: a new termination, not the earlier one handed back
    $this->travel(1)->minutes();
    app(SubscriptionService::class)->tick();
    expect(Subscription::query()->findOrFail($subscription->id)->state)->toBe(Subscription::CANCELLED)
        ->and(Operation::query()->where('service_id', $service->id)->where('idempotency_key', 'like', 'sub_expire:%')->count())->toBe(2);
    driveOperations();
    expect(Service::query()->findOrFail($service->id)->state)->not->toBe(ServiceStateMachine::ACTIVE);
});

it('does not warn staff when a restore keeps a paid period or the auto-renew the customer had on', function () {
    reinstateSwitchOn();
    [, $org] = $this->customerWithOrganization();
    $covered = reinstateCancelled($org, ['hold' => null, 'reason' => 'customer request', 'period_end' => now()->addDays(15), 'auto_renew' => false]);
    $renews = reinstateCancelled($org, ['hold' => null, 'reason' => 'customer request', 'period_end' => now()->subDay(), 'auto_renew' => true, 'remote_id' => '1046']);
    $staff = $this->staff();
    foreach ([$covered, $renews] as $i => $service) {
        expect(reinstateServiceAction($service, 'resume', $this->contextFor($staff, $org), "rr1-nowarn-{$i}", ['reason' => 'rozhodnutí podpory']))->not->toHaveKey('warning');
    }
});
