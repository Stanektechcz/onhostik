<?php

declare(strict_types=1);

use App\Http\Presenters\Presenters;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Illuminate\Support\Facades\Http;

/*
 * H3 (phase H, TASK-0121): the quote a customer reads shows of each line's `config` only the keys a configured list allows
 * (`onhost.quote.customer_line_config`). G6 removed `executor` by name — a deny-list: every key a later change writes into a line
 * (the platform's own bookkeeping, a vendor detail, a value the customer sent and should not see echoed) reached the customer
 * until somebody remembered to remove it too. The stored quote keeps everything; only the answer is filtered.
 */

beforeEach(function () {
    Http::preventStrayRequests();
});

it('keeps only the allowed keys of a line\'s config and leaves the rest of the line alone', function () {
    config()->set('onhost.quote.customer_line_config', ['line_id', 'parent_line_id', 'options']);
    $lines = Presenters::quoteLines([
        ['sku' => 'vps-compute-4', 'net' => 44900, 'config' => ['line_id' => 'l1', 'executor' => 'proxmox', 'sla_class' => 'standard', 'node_hint' => 'prg1-n2', 'options' => ['nvme_gb' => 40]]],
        ['sku' => 'backup-plus-backup-7', 'net' => 4900, 'config' => ['line_id' => 'l2', 'parent_line_id' => 'l1', 'internal_cost_minor' => 1200]],
        ['sku' => 'no-config', 'net' => 1],
    ]);

    expect($lines[0]['config'])->toBe(['line_id' => 'l1', 'options' => ['nvme_gb' => 40]])
        ->and($lines[0]['sku'])->toBe('vps-compute-4')->and($lines[0]['net'])->toBe(44900)
        ->and($lines[1]['config'])->toBe(['line_id' => 'l2', 'parent_line_id' => 'l1'])
        ->and($lines[2])->toBe(['sku' => 'no-config', 'net' => 1]);
});

it('never shows the executor even when somebody puts it on the list', function () {
    config()->set('onhost.quote.customer_line_config', ['line_id', 'executor', 'entitlements']);
    $lines = Presenters::quoteLines([['sku' => 'x', 'config' => ['line_id' => 'l1', 'executor' => 'ispconfig', 'entitlements' => ['sites' => 1]]]]);

    expect($lines[0]['config'])->toBe(['line_id' => 'l1']); // vendor neutrality and the plan's internals are not configurable
});

it('answers a real cart quote with no key outside the list, and with the keys the cart reads', function () {
    $this->seed([CatalogSeeder::class, TaxRuleSeeder::class, LegalEntitySeeder::class]);
    [$owner] = $this->customerWithOrganization();
    $this->actingAs($owner, 'sanctum');
    $this->withHeader('Idempotency-Key', 'h3-cart')->putJson('/v1/cart', ['items' => [
        ['line_id' => 'l1', 'product_key' => 'vps', 'plan_key' => 'compute-4', 'qty' => 1, 'config' => ['label' => 'uzel', 'secret_note' => 'nic']],
        ['line_id' => 'l2', 'product_key' => 'backup-plus', 'plan_key' => 'backup-7', 'qty' => 1, 'config' => ['parent_line_id' => 'l1']],
    ], 'commit_months' => 1, 'currency' => 'CZK'])->assertOk();

    $lines = $this->withHeader('Idempotency-Key', 'h3-quote')->postJson('/v1/cart/quote')->assertOk()->json('data.lines');
    $allowed = (array) config('onhost.quote.customer_line_config');
    foreach ($lines as $line) {
        expect(array_diff(array_keys((array) ($line['config'] ?? [])), $allowed))->toBe([]);
    }
    expect($lines[0]['config'])->not->toHaveKey('executor')->not->toHaveKey('secret_note')
        ->and($lines[0]['config']['label'])->toBe('uzel')
        ->and($lines[0]['config'])->toHaveKey('periods_billed')
        ->and($lines[1]['config']['parent_line_id'])->toBe('l1');
});

it('keeps inside the nested values only what the customer may read: no cost, no margin, no staff approval (review L)', function () {
    $lines = Presenters::quoteLines([[
        'sku' => 'x', 'config' => [
            'line_id' => 'l1',
            'options_priced' => [['key' => 'nvme_gb', 'label' => 'Disk', 'qty' => 2, 'unit_net' => 100, 'net' => 200, 'cost_minor' => 40, 'margin_pct' => 60]],
            'limit_raise' => ['metric' => 'mailboxes', 'units' => 5, 'list_net_minor' => 500, 'purchase_cost_minor' => 120, 'waived' => ['approval_ids' => ['apr_1'], 'by' => 'user:usr_staff', 'reason' => 'goodwill']],
            'loyalty' => ['points' => 300, 'value_minor' => 30000, 'margin_minor' => 999],
            'plan_change' => ['from_plan' => 'start', 'to_plan' => 'standard', 'new_net_minor' => 100, 'cost_minor' => 50],
        ],
    ]]);
    $config = $lines[0]['config'];

    expect($config['options_priced'][0])->toBe(['key' => 'nvme_gb', 'label' => 'Disk', 'qty' => 2, 'unit_net' => 100, 'net' => 200])
        ->and($config['limit_raise'])->toBe(['metric' => 'mailboxes', 'units' => 5, 'list_net_minor' => 500, 'waived' => true])
        ->and($config['loyalty'])->toBe(['points' => 300, 'value_minor' => 30000])
        ->and($config['plan_change'])->toBe(['from_plan' => 'start', 'to_plan' => 'standard', 'new_net_minor' => 100]);
});
