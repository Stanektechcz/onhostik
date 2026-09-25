<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Billing\Models\ChargebackRequest;
use Onhost\Domain\Billing\Models\Subscription;
use Onhost\Domain\Billing\Models\Withdrawal;
use Onhost\Domain\Catalog\CatalogService;
use Onhost\Domain\Identity\StepUp\StepUpService;
use Onhost\Domain\Invoicing\Models\Invoice;
use Onhost\Domain\Invoicing\Models\InvoiceLine;
use Onhost\Domain\Notifications\Models\MailOutbox;
use Onhost\Domain\Notifications\Models\Notification;
use Onhost\Domain\Notifications\NotificationService;
use Onhost\Domain\Orders\CheckoutService;
use Onhost\Domain\Orders\Models\Consent;
use Onhost\Domain\Orders\Models\Order;
use Onhost\Domain\Orders\Models\OrderItem;
use Onhost\Domain\Orders\OrderStateMachine;
use Onhost\Domain\Orders\QuoteService;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Provisioning\AutomationLedger;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Domain\Services\SuspensionHold;
use Onhost\Domain\WalletLedger\LedgerService;
use Onhost\Domain\WalletLedger\WalletService;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Money\Money;
use Onhost\Platform\Outbox\OutboxMessage;
use Onhost\Platform\Outbox\OutboxPublisher;

/*
 * A consumer's withdrawal from a distance contract within fourteen days (TASK-0025, owner decision 17): the service is
 * switched off first, then the paid, unused part comes back to the account credit as a credit note of the paid lines
 * (never a top-up, never the list price), then the service is cancelled through the ordinary saga — and it cannot be
 * resumed for free afterwards. Businesses and registered domains are out; everything is behind the default-off rule
 * `billing.withdrawal`, and one order line is withdrawn once.
 */

beforeEach(function () {
    $this->seed([CatalogSeeder::class, TaxRuleSeeder::class, LegalEntitySeeder::class]);
    Http::preventStrayRequests();
});

function withdrawalSwitchOn(bool $on = true): void
{
    app(AutomationLedger::class)->setEnabled('billing.withdrawal', $on);
}

/** The game panel as the saga uses it: suspend, the final backup, the collaborators, the node. */
function withdrawalPteroFake(?bool &$suspendRefused = null): void
{
    $suspendRefused ??= false;
    Http::fake(function (Request $request) use (&$suspendRefused) {
        if ($suspendRefused && preg_match('#/api/application/servers/\d+/suspend$#', (string) parse_url($request->url(), PHP_URL_PATH)) === 1) {
            return Http::response(['errors' => [['code' => 'BadRequestHttpException', 'status' => '400', 'detail' => 'The server cannot be suspended right now.']]], 400);
        }
        if (str_contains($request->url(), 'wings.test/download')) {
            return Http::response(str_repeat('game archive', 40));
        }
        if (! str_starts_with($request->url(), PTERO)) {
            return null;
        }
        $path = (string) parse_url($request->url(), PHP_URL_PATH);
        $server = ['object' => 'server', 'attributes' => ['id' => 77, 'uuid' => 'u', 'identifier' => 'e4c1abcd', 'name' => 'mc-liga', 'suspended' => false, 'status' => null, 'user' => 9, 'node' => 2, 'allocation' => 11, 'nest' => 1, 'egg' => 3, 'limits' => ['memory' => 8192, 'disk' => 61440, 'cpu' => 300], 'feature_limits' => ['databases' => 2, 'allocations' => 2, 'backups' => 5], 'container' => ['installed' => 1, 'environment' => []]]];
        $backup = ['object' => 'backup', 'attributes' => ['uuid' => 'bk-final', 'name' => 'final', 'is_successful' => true, 'is_locked' => false, 'bytes' => 1024, 'completed_at' => now()->toIso8601String(), 'created_at' => now()->toIso8601String()]];

        return match (true) {
            preg_match('#^/api/application/servers/\d+$#', $path) === 1 && $request->method() === 'GET' => Http::response($server),
            preg_match('#^/api/application/servers/\d+/suspend$#', $path) === 1 => Http::response('', 204),
            str_ends_with($path, '/backups') && $request->method() === 'POST' => Http::response(['object' => 'backup', 'attributes' => ['is_successful' => false, 'completed_at' => null] + $backup['attributes']]),
            str_ends_with($path, '/backups') && $request->method() === 'GET' => Http::response(['object' => 'list', 'data' => [$backup]]),
            str_ends_with($path, '/backups/bk-final') => Http::response($backup),
            str_ends_with($path, '/backups/bk-final/download') => Http::response(['object' => 'signed_url', 'attributes' => ['url' => 'https://wings.test/download/backup?token=abc']]),
            $path === '/api/application/nodes' => Http::response(['object' => 'list', 'data' => [['object' => 'node', 'attributes' => ['id' => 2, 'name' => 'games01', 'fqdn' => 'wings.test']]], 'meta' => ['pagination' => ['total_pages' => 1]]]),
            str_ends_with($path, '/users') && $request->method() === 'GET' => Http::response(['object' => 'list', 'data' => []]),
            default => Http::response(['errors' => [['code' => 'NotFoundHttpException', 'status' => '404', 'detail' => "no fake for {$path}"]]], 404),
        };
    });
}

/**
 * A game server a consumer ordered `$o['days_ago']` days ago (default 4: today is the fifth day) and paid from the credit:
 * the order, its line, the waiver consent, the subscription and the paid statement for the first month.
 *
 * @param  array<string,mixed>  $o
 */
function withdrawalConsumerService(Organization $org, array $o = []): Service
{
    $service = featureGameService($org, [], (int) ($o['remote_id'] ?? 77), (string) ($o['identifier'] ?? 'e4c1abcd'));
    $daysAgo = (int) ($o['days_ago'] ?? 4);
    $placed = now()->subDays($daysAgo);
    $meta = array_key_exists('class', $o) ? ($o['class'] === null ? [] : ['customer_class' => $o['class']]) : ['customer_class' => 'b2c'];
    $order = Order::query()->create(['number' => 'ON-W'.substr(uniqid(), -6), 'organization_id' => $org->id, 'state' => OrderStateMachine::ACTIVE, 'currency' => 'CZK', 'subtotal_minor' => 30000, 'tax_minor' => 6300, 'total_minor' => 36300,
        'payment_mode' => 'wallet', 'idempotency_key' => 'wdr-test-'.uniqid(), 'placed_at' => $placed, 'paid_at' => $placed, 'meta' => $meta]);
    $item = OrderItem::query()->create(['order_id' => $order->id, 'sku' => 'game-8', 'product_key' => 'game', 'name' => 'Herní server 8 GB', 'qty' => 1, 'unit_net_minor' => 30000, 'tax_rate' => '21', 'tax_minor' => 6300, 'total_minor' => 36300, 'period' => 'month', 'config' => ['family' => 'game'], 'service_id' => $service->id, 'state' => 'active']);
    if (($o['waiver'] ?? true) === true) {
        Consent::query()->create(['organization_id' => $org->id, 'order_id' => $order->id, 'kind' => 'withdrawal_waiver', 'document_key' => 'withdrawal_waiver', 'document_version' => '2026-09', 'accepted_at' => $placed]);
    }
    $plan = app(CatalogService::class)->resolve('game', 'game-8', 'CZK', 'month');
    Subscription::query()->create(['organization_id' => $org->id, 'service_id' => $service->id, 'plan_version_id' => $plan['version']->id, 'price_id' => $plan['price']->id, 'currency' => 'CZK', 'period' => 'month', 'amount_minor' => 30000, 'state' => Subscription::ACTIVE,
        'current_period_start' => $placed, 'current_period_end' => $placed->copy()->addDays(30), 'next_renewal_at' => $placed->copy()->addDays(30), 'auto_renew' => true, 'renewal_priority' => 'normal']);
    $statement = chargebackPaidStatement($org, $service, (int) ($o['gross'] ?? 36300), (int) ($o['tax'] ?? 6300), -$daysAgo, (int) ($o['to'] ?? 29 - $daysAgo), (string) ($o['type'] ?? 'statement'), (bool) ($o['paid'] ?? true));
    InvoiceLine::query()->where('invoice_id', $statement->id)->update(['order_item_id' => $item->id]);
    $statement->forceFill(['order_id' => $order->id])->save();

    return $service->refresh();
}

/** Drive the service's operations and deliver the events until the withdrawal stops moving. */
function withdrawalSettle(): void
{
    for ($i = 0; $i < 4; $i++) {
        driveOperations();
        app(OutboxPublisher::class)->relayPending();
    }
}

it('switches the service off, returns the unused days of what was PAID to the credit, then cancels it — and it cannot be resumed', function () {
    withdrawalSwitchOn();
    withdrawalPteroFake();
    [$owner, $org] = $this->customerWithOrganization([], ['type' => 'person', 'name' => 'Jana Nováková']);
    $service = withdrawalConsumerService($org); // 363 Kč paid for thirty days, today is the fifth: 25 days unused
    $wallets = app(WalletService::class);
    $this->actingAs($owner, 'sanctum');

    $info = $this->getJson("/v1/services/{$service->id}/withdrawal")->assertOk()->json('data');
    expect($info)->toMatchArray(['enabled' => true, 'eligible' => true, 'reason' => null, 'customer_class' => 'b2c'])
        ->and($info['estimate']['refund']['minor'])->toBe(30250)->and($info['deadline'])->toStartWith(now()->subDays(4)->addDays(14)->toDateString());

    // the express agreement to a refund to the credit is part of the notice; ending a contract is a fresh step-up
    $this->withHeader('Idempotency-Key', 'wd-0')->postJson("/v1/services/{$service->id}/withdrawal", [])->assertStatus(422);
    $this->withHeader('Idempotency-Key', 'wd-1')->postJson("/v1/services/{$service->id}/withdrawal", ['confirm_refund_to_credit' => true])->assertStatus(403)->assertJsonPath('error', 'step_up_required');
    app(StepUpService::class)->grant($owner, 'totp', null, '127.0.0.1');
    $accepted = $this->withHeader('Idempotency-Key', 'wd-2')->postJson("/v1/services/{$service->id}/withdrawal", ['confirm_refund_to_credit' => true, 'statement' => 'Služba nám nevyhovuje.'])->assertStatus(202)->json();
    $this->flushHeaders();
    expect($accepted['state'])->toBe('suspending')->and($accepted['suspend_operation_id'])->not->toBeNull();
    $withdrawal = Withdrawal::query()->findOrFail($accepted['id']);
    expect($withdrawal->subject_key)->toStartWith('item:')->and(Consent::query()->whereKey($withdrawal->refund_consent_id)->value('kind'))->toBe('withdrawal_refund_to_credit')
        ->and(Subscription::query()->where('service_id', $service->id)->value('auto_renew'))->toBeFalse(); // nothing renews while it is being unwound

    withdrawalSettle();

    $withdrawal->refresh();
    $fresh = Service::query()->findOrFail($service->id);
    expect($withdrawal->state)->toBe(Withdrawal::COMPLETED)->and($withdrawal->refund_minor)->toBe(30250)->and($withdrawal->to_credit_minor)->toBe(30250)
        ->and($fresh->state)->toBe(ServiceStateMachine::SUSPENDED)->and($fresh->terminate_at)->not->toBeNull()->and(SuspensionHold::holds($fresh))->toContain(SuspensionHold::WITHDRAWAL);
    // suspend first, then the credit note, then the cancellation
    $suspended = Operation::query()->findOrFail($withdrawal->suspend_operation_id);
    $terminate = Operation::query()->findOrFail($withdrawal->terminate_operation_id);
    $note = Invoice::query()->where('type', 'credit_note')->sole();
    expect($suspended->state)->toBe(Operation::SUCCEEDED)->and($fresh->suspended_at->lte($withdrawal->refunded_at))->toBeTrue()->and($withdrawal->refunded_at->lte($terminate->queued_at))->toBeTrue();
    expect($terminate->idempotency_key)->toBe("withdrawal:{$withdrawal->id}:terminate")->and($terminate->desired['final_backup'] ?? null)->toBeTrue();
    // a credit note of exactly the unused part of the paid line, VAT split in the line's proportion, back on the credit as money that cannot be paid out
    expect($note->total_minor)->toBe(-30250)->and($note->tax_minor)->toBe(-5250)->and($wallets->balances($org, 'CZK')['posted']->minor)->toBe(30250)
        ->and($wallets->refundableBalance($org->id, 'CZK')->minor)->toBe(0)->and(app(LedgerService::class)->verifyInvariant()['balanced'])->toBeTrue();

    // no free way back: the customer's resume is refused with the withdrawal hold
    $this->withHeader('Idempotency-Key', 'wd-resume')->postJson("/v1/services/{$service->id}/actions", ['action' => 'resume'])->assertStatus(409)->assertJsonPath('error', 'service_suspension_held')->assertJsonPath('hold', 'withdrawal');
    $this->flushHeaders();

    // the confirmation of receipt is a mandatory legal notice on a durable medium; finance sees the refund
    expect(NotificationService::TEMPLATE_KINDS['withdrawal-accepted'])->toBe('legal.notice');
    expect(OutboxMessage::query()->where('name', 'withdrawal.accepted')->count())->toBe(1)
        ->and(Notification::query()->where('organization_id', $org->id)->where('title', 'like', 'Odstoupení od smlouvy přijato%')->exists())->toBeTrue()
        ->and(Notification::query()->where('audience', 'internal')->where('title', 'like', 'Odstoupení spotřebitele: vráceno%')->exists())->toBeTrue()
        ->and(MailOutbox::query()->where('template_key', 'withdrawal-accepted')->exists())->toBeTrue();
});

it('gives nothing back while the service still runs: a suspension the panel refused is asked for again, then the refund follows', function () {
    withdrawalSwitchOn();
    $refused = true;
    withdrawalPteroFake($refused);
    [$owner, $org] = $this->customerWithOrganization([], ['type' => 'person', 'name' => 'Jana Nováková']);
    $service = withdrawalConsumerService($org);
    $this->actingAs($owner, 'sanctum');
    app(StepUpService::class)->grant($owner, 'totp', null, '127.0.0.1');

    $this->withHeader('Idempotency-Key', 'wd-run')->postJson("/v1/services/{$service->id}/withdrawal", ['confirm_refund_to_credit' => true])->assertStatus(202);
    $this->flushHeaders();
    withdrawalSettle();
    Artisan::call('onhost:withdrawals:finish');
    withdrawalSettle();

    $withdrawal = Withdrawal::query()->sole();
    expect(Service::query()->findOrFail($service->id)->state)->toBe(ServiceStateMachine::ACTIVE)->and($withdrawal->state)->toBe(Withdrawal::SUSPENDING)
        ->and($withdrawal->refunded_at)->toBeNull()->and(Invoice::query()->where('type', 'credit_note')->count())->toBe(0)->and(app(WalletService::class)->balances($org, 'CZK')['posted']->minor)->toBe(0);

    $refused = false; // the panel takes the suspension now
    Artisan::call('onhost:withdrawals:finish');
    withdrawalSettle();

    expect($withdrawal->refresh()->state)->toBe(Withdrawal::COMPLETED)->and($withdrawal->refund_minor)->toBe(30250)->and(Invoice::query()->where('type', 'credit_note')->count())->toBe(1)
        ->and(Operation::query()->where('idempotency_key', 'like', "withdrawal:{$withdrawal->id}:suspend%")->count())->toBeGreaterThan(1);
});

it('returns a share of what a promo-discounted twelve-month prepayment cost, never the list price', function () {
    withdrawalSwitchOn();
    [$owner, $org] = $this->customerWithOrganization([], ['type' => 'person', 'name' => 'Jana Nováková']);
    // twelve months paid in advance with a discount (1 000 Kč instead of 4 356 Kč), ordered nine days ago
    $service = withdrawalConsumerService($org, ['days_ago' => 9, 'gross' => 100000, 'tax' => 17355, 'to' => 364 - 9]);
    $this->actingAs($owner, 'sanctum');

    $estimate = $this->getJson("/v1/services/{$service->id}/withdrawal")->assertOk()->json('data.estimate');

    expect($estimate['refund']['minor'])->toBe((int) round(100000 * 355 / 365))->and($estimate['lines'][0])->toMatchArray(['days' => 365, 'days_left' => 355]);
});

it('keeps the fourteen days by the date the notice was sent: the panel refuses day 17, staff record a letter sent on day 15 behind four eyes', function () {
    withdrawalSwitchOn();
    withdrawalPteroFake();
    [$owner, $org] = $this->customerWithOrganization([], ['type' => 'person', 'name' => 'Jana Nováková']);
    $service = withdrawalConsumerService($org, ['days_ago' => 16, 'to' => 13]);
    $this->actingAs($owner, 'sanctum');
    app(StepUpService::class)->grant($owner, 'totp', null, '127.0.0.1');

    $this->withHeader('Idempotency-Key', 'wd-late')->postJson("/v1/services/{$service->id}/withdrawal", ['confirm_refund_to_credit' => true])->assertStatus(409)->assertJsonPath('error', 'withdrawal_period_over');
    $this->flushHeaders();
    expect(Withdrawal::query()->count())->toBe(0);

    // a letter posted on the last day (the order day + 14) arrives two days later: staff record it with the day it was sent
    $sentAt = now()->subDays(2)->setTime(12, 0)->toIso8601String();
    $support = $this->staff('support_l1');
    $this->actingAs($support, 'sanctum');
    app(StepUpService::class)->grant($support, 'totp', null, '127.0.0.1');
    $body = ['organization_id' => $org->id, 'service_id' => $service->id, 'sent_at' => $sentAt, 'refund_to_credit_agreed' => true, 'reason' => 'Dopis ze dne podání, doručen poštou.'];
    $this->postJson('/v1/staff/withdrawals', $body)->assertForbidden(); // refunds are finance's

    $finance = $this->staff('billing_finance_admin');
    $this->actingAs($finance, 'sanctum');
    app(StepUpService::class)->grant($finance, 'totp', null, '127.0.0.1');
    $approval = $this->postJson('/v1/staff/withdrawals', $body)->assertForbidden()->assertJsonPath('error', 'approval_required')->json('approval_id');
    $recorded = $this->postJson('/v1/staff/withdrawals', $body + ['approval_ids' => [secondPersonApproves($approval)]])->assertStatus(202)->json();
    expect($recorded['channel'])->toBe('staff')->and($recorded['sent_at'])->toStartWith(substr($sentAt, 0, 10));

    withdrawalSettle();

    // prorated as of the day the letter was sent: of 30 days (day -16 … day 13) 15 were still ahead then
    $withdrawal = Withdrawal::query()->findOrFail($recorded['id']);
    expect($withdrawal->state)->toBe(Withdrawal::COMPLETED)->and($withdrawal->refund_minor)->toBe((int) round(36300 * 15 / 30));
    expect($this->getJson('/v1/staff/withdrawals')->assertOk()->json('data.rows.0.id'))->toBe($withdrawal->id);
});

it('is for consumers: an order placed as a business is refused, a consumer who adds a company id later keeps the right', function () {
    withdrawalSwitchOn();
    [$owner, $org] = $this->customerWithOrganization([], ['type' => 'person', 'name' => 'Jana Nováková']);
    $business = withdrawalConsumerService($org, ['class' => 'b2b', 'waiver' => false]);
    $consumer = withdrawalConsumerService($org, ['remote_id' => 78, 'identifier' => 'e4c1abce']);
    $org->forceFill(['ico' => '12345678', 'customer_class' => 'b2b'])->save();
    $this->actingAs($owner, 'sanctum');
    app(StepUpService::class)->grant($owner, 'totp', null, '127.0.0.1');

    $this->withHeader('Idempotency-Key', 'wd-b2b')->postJson("/v1/services/{$business->id}/withdrawal", ['confirm_refund_to_credit' => true])->assertStatus(403)->assertJsonPath('error', 'withdrawal_consumers_only');
    $this->flushHeaders();
    expect($this->getJson("/v1/services/{$consumer->id}/withdrawal")->assertOk()->json('data'))->toMatchArray(['eligible' => true, 'customer_class' => 'b2c']);
});

it('never withdraws a registered domain, and says so at checkout', function () {
    withdrawalSwitchOn();
    [$owner, $org] = $this->customerWithOrganization([], ['type' => 'person', 'name' => 'Jana Nováková']);
    $domain = withdrawalConsumerService($org);
    $domain->forceFill(['product_key' => 'domain', 'family' => 'domain'])->save();
    $this->actingAs($owner, 'sanctum');
    app(StepUpService::class)->grant($owner, 'totp', null, '127.0.0.1');

    $this->withHeader('Idempotency-Key', 'wd-dom')->postJson("/v1/services/{$domain->id}/withdrawal", ['confirm_refund_to_credit' => true])->assertStatus(422)->assertJsonPath('error', 'withdrawal_not_applicable')->assertJsonPath('why', 'domain_registered');
    $this->flushHeaders();
    expect(Invoice::query()->where('type', 'credit_note')->count())->toBe(0);

    // the cart tells a consumer before the order: the registration cannot be withdrawn once it is done
    $this->putJson('/v1/cart', ['items' => [['product_key' => 'domain', 'qty' => 1, 'config' => ['fqdn' => 'jana-novakova.cz', 'period_years' => 1, 'action' => 'register']]]])->assertOk();
    $notice = $this->postJson('/v1/cart/quote')->json('data.withdrawal_notice');
    expect($notice)->not->toBeNull()->and($notice['text'])->toContain('domén')->and($notice['terms_url'])->toBe('/dokumenty/odstoupeni');
});

it('changes nothing while the rule is off', function () {
    withdrawalPteroFake();
    [$owner, $org] = $this->customerWithOrganization([], ['type' => 'person', 'name' => 'Jana Nováková']);
    $service = withdrawalConsumerService($org);
    $this->actingAs($owner, 'sanctum');
    app(StepUpService::class)->grant($owner, 'totp', null, '127.0.0.1');

    expect($this->getJson("/v1/services/{$service->id}/withdrawal")->assertOk()->json('data'))->toMatchArray(['enabled' => false, 'eligible' => false, 'reason' => 'withdrawal_disabled']);
    $this->withHeader('Idempotency-Key', 'wd-off')->postJson("/v1/services/{$service->id}/withdrawal", ['confirm_refund_to_credit' => true])->assertStatus(409)->assertJsonPath('error', 'withdrawal_disabled');
    $this->flushHeaders();

    expect(Withdrawal::query()->count())->toBe(0)->and(Operation::query()->count())->toBe(0)->and(Service::query()->findOrFail($service->id)->state)->toBe(ServiceStateMachine::ACTIVE)
        ->and(Subscription::query()->where('service_id', $service->id)->value('auto_renew'))->toBeTrue();
});

it('withdraws one order line once: a second request, a replayed event and a retried finish change nothing', function () {
    withdrawalSwitchOn();
    withdrawalPteroFake();
    [$owner, $org] = $this->customerWithOrganization([], ['type' => 'person', 'name' => 'Jana Nováková']);
    $service = withdrawalConsumerService($org);
    $this->actingAs($owner, 'sanctum');
    app(StepUpService::class)->grant($owner, 'totp', null, '127.0.0.1');

    $first = $this->withHeader('Idempotency-Key', 'wd-a')->postJson("/v1/services/{$service->id}/withdrawal", ['confirm_refund_to_credit' => true])->assertStatus(202)->json();
    $this->withHeader('Idempotency-Key', 'wd-a')->postJson("/v1/services/{$service->id}/withdrawal", ['confirm_refund_to_credit' => true])->assertStatus(202)->assertJsonPath('id', $first['id']);
    $this->withHeader('Idempotency-Key', 'wd-b')->postJson("/v1/services/{$service->id}/withdrawal", ['confirm_refund_to_credit' => true])->assertStatus(409)->assertJsonPath('error', 'withdrawal_already_recorded');
    $this->flushHeaders();
    withdrawalSettle();
    // the service's events delivered again, and the finish command run twice
    foreach (OutboxMessage::query()->where('aggregate_id', $service->id)->whereIn('name', ['service.suspended', 'service.deactivated'])->get() as $message) {
        event('onhost.'.$message->name, [$message]);
    }
    Artisan::call('onhost:withdrawals:finish');
    Artisan::call('onhost:withdrawals:finish');

    expect(Withdrawal::query()->count())->toBe(1)->and(Invoice::query()->where('type', 'credit_note')->count())->toBe(1)
        ->and(Operation::query()->where('service_id', $service->id)->where('idempotency_key', 'like', 'withdrawal:%:terminate')->count())->toBe(1)
        ->and(app(WalletService::class)->balances($org, 'CZK')['posted']->minor)->toBe(30250);
});

it('takes an open chargeback over and does not run beside one that is already cancelling', function () {
    withdrawalSwitchOn();
    withdrawalPteroFake();
    [$owner, $org] = $this->customerWithOrganization([], ['type' => 'person', 'name' => 'Jana Nováková']);
    $service = withdrawalConsumerService($org);
    $open = ChargebackRequest::query()->create(['organization_id' => $org->id, 'service_id' => $service->id, 'state' => ChargebackRequest::APPROVED, 'reason' => 'Nechceme.', 'percent' => 70, 'currency' => 'CZK']);
    $other = withdrawalConsumerService($org, ['remote_id' => 78, 'identifier' => 'e4c1abce']);
    ChargebackRequest::query()->create(['organization_id' => $org->id, 'service_id' => $other->id, 'state' => ChargebackRequest::CANCELLING, 'reason' => 'Nechceme.', 'percent' => 70, 'currency' => 'CZK']);
    $this->actingAs($owner, 'sanctum');
    app(StepUpService::class)->grant($owner, 'totp', null, '127.0.0.1');

    $this->withHeader('Idempotency-Key', 'wd-cb1')->postJson("/v1/services/{$other->id}/withdrawal", ['confirm_refund_to_credit' => true])->assertStatus(409)->assertJsonPath('error', 'chargeback_in_progress');
    $this->withHeader('Idempotency-Key', 'wd-cb2')->postJson("/v1/services/{$service->id}/withdrawal", ['confirm_refund_to_credit' => true])->assertStatus(202);
    $this->flushHeaders();
    withdrawalSettle();

    expect($open->refresh()->state)->toBe(ChargebackRequest::WITHDRAWN)->and(Invoice::query()->where('type', 'credit_note')->count())->toBe(1)
        ->and(Invoice::query()->where('type', 'credit_note')->sole()->total_minor)->toBe(-30250); // the full unused part, not the chargeback's 70 %, and once
});

it('makes an unpaid postpaid invoice smaller instead of paying out money that never came', function () {
    withdrawalSwitchOn();
    withdrawalPteroFake();
    [$owner, $org] = $this->customerWithOrganization([], ['type' => 'person', 'name' => 'Jana Nováková']);
    $service = withdrawalConsumerService($org, ['type' => 'invoice', 'paid' => false]);
    $invoice = Invoice::query()->where('type', 'invoice')->sole();
    $this->actingAs($owner, 'sanctum');
    app(StepUpService::class)->grant($owner, 'totp', null, '127.0.0.1');

    $this->withHeader('Idempotency-Key', 'wd-pp')->postJson("/v1/services/{$service->id}/withdrawal", ['confirm_refund_to_credit' => true])->assertStatus(202);
    $this->flushHeaders();
    withdrawalSettle();

    $withdrawal = Withdrawal::query()->sole();
    expect($withdrawal->off_document_minor)->toBe(30250)->and($withdrawal->to_credit_minor)->toBe(0)
        ->and(app(WalletService::class)->balances($org, 'CZK')['available']->minor)->toBe(0)->and($invoice->refresh()->outstanding()->minor)->toBe(36300 - 30250);
});

it('keeps the refund when the cancellation is refused (legal hold) and requests it once the finish command may', function () {
    withdrawalSwitchOn();
    withdrawalPteroFake();
    [$owner, $org] = $this->customerWithOrganization([], ['type' => 'person', 'name' => 'Jana Nováková']);
    $service = withdrawalConsumerService($org);
    $service->forceFill(['legal_hold' => true])->save();
    $this->actingAs($owner, 'sanctum');
    app(StepUpService::class)->grant($owner, 'totp', null, '127.0.0.1');

    $this->withHeader('Idempotency-Key', 'wd-lh')->postJson("/v1/services/{$service->id}/withdrawal", ['confirm_refund_to_credit' => true])->assertStatus(202);
    $this->flushHeaders();
    withdrawalSettle();

    $withdrawal = Withdrawal::query()->sole();
    expect($withdrawal->state)->toBe(Withdrawal::REFUNDED)->and($withdrawal->error)->toBe('legal_hold')->and($withdrawal->refund_minor)->toBe(30250)
        ->and(OutboxMessage::query()->where('name', 'withdrawal.stalled')->count())->toBe(1);
    Artisan::call('onhost:withdrawals:finish');
    expect($withdrawal->refresh()->state)->toBe(Withdrawal::REFUNDED); // still held: nothing moves, nothing is refunded again

    $service->forceFill(['legal_hold' => false])->save();
    Artisan::call('onhost:withdrawals:finish', ['--dry-run' => true]);
    expect($withdrawal->refresh()->state)->toBe(Withdrawal::REFUNDED);
    Artisan::call('onhost:withdrawals:finish');
    withdrawalSettle();
    expect($withdrawal->refresh()->state)->toBe(Withdrawal::COMPLETED)->and(Invoice::query()->where('type', 'credit_note')->count())->toBe(1);
});

it('cancels a paid order nothing of which was delivered, crediting every line', function () {
    withdrawalSwitchOn();
    [$owner, $org] = $this->customerWithOrganization(['email' => 'x@mailinator.com'], ['type' => 'person', 'name' => 'Jana Nováková', 'billing_email' => 'x@mailinator.com']);
    $ctx = $this->contextFor($owner, $org);
    app(WalletService::class)->topup($org, Money::decimal('5000', 'CZK'), 'card', 'seed', $ctx, bankProvider: 'comgate');
    $quote = app(QuoteService::class)->quote([['product_key' => 'web-hosting', 'plan_key' => 'start']], 'CZK', ['country' => 'CZ', 'customer_class' => 'b2c', 'vat_status' => 'unknown'], 1, null, $org);
    $held = app(CheckoutService::class)->placeOrder($quote, $org, $owner, ['terms' => [], 'privacy' => [], 'withdrawal_waiver' => []], ['mode' => 'wallet'], 'wd-order-1', $ctx)['order']->refresh();
    expect($held->state)->toBe(OrderStateMachine::PAID)->and($held->meta['customer_class'] ?? null)->toBe('b2c'); // the class is kept with the order
    $this->actingAs($owner, 'sanctum');
    app(StepUpService::class)->grant($owner, 'totp', null, '127.0.0.1');

    expect($this->getJson("/v1/orders/{$held->id}/withdrawal")->assertOk()->json('data.eligible'))->toBeTrue();
    $done = $this->withHeader('Idempotency-Key', 'wd-ord')->postJson("/v1/orders/{$held->id}/withdrawal", ['confirm_refund_to_credit' => true])->assertStatus(202)->json();
    $this->flushHeaders();

    expect($done['state'])->toBe(Withdrawal::COMPLETED)->and($held->refresh()->state)->toBe(OrderStateMachine::CANCELLED)
        ->and(Invoice::query()->where('type', 'credit_note')->where('order_id', $held->id)->exists())->toBeTrue()
        ->and(app(WalletService::class)->balances($org, 'CZK')['available']->minor)->toBe(500000);
});

it('keeps tenants apart: nobody withdraws from another organization\'s service', function () {
    withdrawalSwitchOn();
    [, $org] = $this->customerWithOrganization([], ['type' => 'person', 'name' => 'Jana Nováková']);
    $service = withdrawalConsumerService($org);
    [$stranger] = $this->customerWithOrganization(['email' => 'stranger@example.test'], ['type' => 'person', 'name' => 'Cizí']);
    $this->actingAs($stranger, 'sanctum');
    app(StepUpService::class)->grant($stranger, 'totp', null, '127.0.0.1');

    $this->getJson("/v1/services/{$service->id}/withdrawal")->assertForbidden();
    $this->withHeader('Idempotency-Key', 'wd-x')->postJson("/v1/services/{$service->id}/withdrawal", ['confirm_refund_to_credit' => true])->assertForbidden();
    expect(Withdrawal::query()->count())->toBe(0);
});

it('carries the paid lines of the add-ons into the refund and cancels them with the service', function () {
    withdrawalSwitchOn();
    withdrawalPteroFake();
    [$owner, $org] = $this->customerWithOrganization([], ['type' => 'person', 'name' => 'Jana Nováková']);
    $service = withdrawalConsumerService($org);
    $addon = Service::query()->create(['organization_id' => $org->id, 'product_key' => 'game-backup', 'family' => 'addon', 'name' => 'Zálohy navíc', 'state' => ServiceStateMachine::ACTIVE, 'region_code' => 'cz1', 'entitlements' => [], 'sla_class' => 'standard', 'activated_at' => now(), 'tags' => ['parent_service_id' => $service->id]]);
    chargebackPaidStatement($org, $addon, 12100, 2100, -4, 25);
    $this->actingAs($owner, 'sanctum');
    app(StepUpService::class)->grant($owner, 'totp', null, '127.0.0.1');

    $this->withHeader('Idempotency-Key', 'wd-addon')->postJson("/v1/services/{$addon->id}/withdrawal", ['confirm_refund_to_credit' => true])->assertStatus(422)->assertJsonPath('why', 'addon');
    $this->withHeader('Idempotency-Key', 'wd-parent')->postJson("/v1/services/{$service->id}/withdrawal", ['confirm_refund_to_credit' => true])->assertStatus(202);
    $this->flushHeaders();
    withdrawalSettle();

    expect(Withdrawal::query()->sole()->refund_minor)->toBe(30250 + (int) round(12100 * 25 / 30))->and(Service::query()->findOrFail($addon->id)->state)->toBe(ServiceStateMachine::SUSPENDED);
});

it('names the withdrawal hold for the platform, and a customer cannot hold their own service with it', function () {
    [$owner, $org] = $this->customerWithOrganization([], ['type' => 'person', 'name' => 'Jana Nováková']);

    expect(SuspensionHold::kindFor(CommandContext::system('withdrawal wdr_1'), 'withdrawal wdr_1'))->toBe(SuspensionHold::WITHDRAWAL)
        ->and(SuspensionHold::kindFor($this->contextFor($owner, $org), 'withdrawal wdr_1'))->toBeNull()
        ->and(SuspensionHold::KINDS)->toContain(SuspensionHold::WITHDRAWAL);
});

it('tells the doctor when the mechanism runs without a lawyer having reviewed it', function () {
    Artisan::call('onhost:doctor', ['--json' => true]);
    $off = collect(json_decode(Artisan::output(), true)['checks'])->firstWhere('check', 'consumer withdrawal reviewed by a lawyer');
    withdrawalSwitchOn();
    Artisan::call('onhost:doctor', ['--json' => true]);
    $on = collect(json_decode(Artisan::output(), true)['checks'])->firstWhere('check', 'consumer withdrawal reviewed by a lawyer');

    expect($off['status'])->toBe('OK')->and($on['status'])->toBe('WARN')->and($on['detail'])->toContain('ONHOST_WITHDRAWAL_LEGAL_REVIEWED');
});
