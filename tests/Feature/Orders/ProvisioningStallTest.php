<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Onhost\Domain\Orders\CheckoutService;
use Onhost\Domain\Orders\Models\Order;
use Onhost\Domain\Orders\Models\OrderItem;
use Onhost\Domain\Orders\OrderStateMachine;
use Onhost\Domain\Orders\QuoteService;
use Onhost\Domain\Provisioning\Models\Operation;

/*
 * When a node answers a fulfilment with a transient error the operation waits and retries; the order tells the panel
 * so (provisioning.stalled) and the web-order banner stops promising the usual 90 seconds.
 */

beforeEach(fn () => $this->seed([CatalogSeeder::class, TaxRuleSeeder::class, LegalEntitySeeder::class]));

it('reports a stalled fulfilment on the order while an operation waits on a transient node error', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $ctx = $this->contextFor($owner, $org);
    $consents = ['terms' => ['version' => '4.0'], 'privacy' => ['version' => '4.0'], 'withdrawal_waiver' => ['version' => '4.0'], 'dpa' => ['version' => '4.0'], 'sla' => ['version' => '4.0']];
    $quote = app(QuoteService::class)->quote([['product_key' => 'web-hosting', 'plan_key' => 'start']], 'CZK', ['country' => 'CZ', 'customer_class' => 'b2c'], 1, null, $org);
    $order = app(CheckoutService::class)->placeOrder($quote, $org, $owner, $consents, ['mode' => 'bank'], 'stall-test:q1', $ctx)['order'];
    $this->actingAs($owner, 'sanctum');

    // unpaid: nothing to report
    expect($this->getJson("/v1/orders/{$order->id}")->assertOk()->json('data.provisioning'))->toBeNull();

    // paid and provisioning, the node reset the connection: the operation waits and retries
    $item = OrderItem::query()->where('order_id', $order->id)->firstOrFail();
    Order::query()->whereKey($order->id)->update(['state' => OrderStateMachine::PROVISIONING]);
    $op = Operation::query()->create([
        'organization_id' => $org->id, 'order_item_id' => $item->id, 'kind' => 'provision.website', 'workflow' => 'provision.website', 'state' => Operation::WAITING, 'queue' => 'provider-ispconfig',
        'idempotency_key' => 'stall-op', 'attempts' => 1, 'queued_at' => now()->subMinutes(2), 'next_run_at' => now()->addSeconds(30), 'error' => ['message' => '[ispconfig:TRANSIENT] Connection failed', 'retryable' => true],
    ]);
    $report = $this->getJson("/v1/orders/{$order->id}")->assertOk()->json('data.provisioning');
    expect($report)->toMatchArray(['active' => 1, 'stalled' => true])->and($report['since'])->not->toBeNull()->and($report['next_run_at'])->not->toBeNull();
    expect(collect($this->getJson('/v1/orders')->assertOk()->json('data'))->firstWhere('id', $order->id)['provisioning']['stalled'])->toBeTrue();

    // the operation runs again: no longer stalled
    $op->forceFill(['state' => Operation::RUNNING, 'error' => null])->save();
    expect($this->getJson("/v1/orders/{$order->id}")->assertOk()->json('data.provisioning'))->toMatchArray(['active' => 1, 'stalled' => false]);

    // the panel seams: the banner consults the store, the store maps the flag, the workbench names the wait
    $panel = $this->get('/panel')->assertOk()->getContent();
    expect($panel)->toContain("window.OnhostStore.orderStalled(ho.id)) ? ' · nasazení trvá déle než obvykle, zkoušíme znovu'")->not->toContain("' · nasazujeme, obvykle do 90 sekund') : '',");
    $store = (string) file_get_contents((string) $this->get('/surfaces/api/onhost-store.api.js')->assertOk()->baseResponse->getFile());
    expect($store)->toContain('stalled: !!(o.provisioning && o.provisioning.stalled)')->toContain('orderStalled: function (number)');
    $workbench = (string) file_get_contents((string) $this->get('/surfaces/api/onhost-panel-workbench.api.js')->assertOk()->baseResponse->getFile());
    expect($workbench)->toContain("_('trvá déle než obvykle · zkoušíme znovu', 'taking longer than usual · retrying')");
});
