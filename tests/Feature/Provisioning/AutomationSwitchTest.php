<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Notifications\Models\Notification;
use Onhost\Domain\Orders\OrderRiskService;
use Onhost\Domain\Orders\QuoteService;
use Onhost\Domain\Provisioning\AutomationLedger;
use Onhost\Domain\Provisioning\Jobs\QueueHeartbeat;
use Onhost\Domain\Provisioning\OperationService;
use Onhost\Domain\Provisioning\Workflows\GameMigrationWorkflow;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Outbox\OutboxMessage;
use Onhost\Platform\Outbox\OutboxPublisher;
use Onhost\Platform\Settings\SettingsStore;

/*
 * Automation switches and liveness (audit §5g-6/7): staff switch a rule off from the console and it records skips
 * instead of going quiet; the two rules the platform cannot live without have no switch; a queue worker that stops
 * answering its heartbeat becomes one internal alert per half hour and a failing doctor check.
 */

beforeEach(fn () => $this->seed([CatalogSeeder::class, TaxRuleSeeder::class, LegalEntitySeeder::class]));

it('switches a rule off and on from the console; a switched-off rule records skips and the order check stops scoring', function () {
    $staff = $this->staff('platform_owner');
    $this->actingAs($staff, 'sanctum');
    $this->putJson('/v1/staff/automation/usage.watch', ['enabled' => false, 'reason' => 'capacity work'])->assertOk()->assertJsonPath('enabled', false)->assertJsonPath('key', 'usage.watch');
    expect(app(AutomationLedger::class)->enabled('usage.watch'))->toBeFalse()->and(app(SettingsStore::class)->get(AutomationLedger::SETTING))->toBe(['usage.watch']);
    $this->artisan('onhost:services:usage-watch')->expectsOutputToContain('switched off')->assertSuccessful();
    expect(app(AutomationLedger::class)->last('usage.watch')['stats'])->toBe(['skipped' => 1]);
    $rules = collect($this->getJson('/v1/staff/automation')->assertOk()->json('data'))->keyBy('key');
    expect($rules->get('usage.watch')['enabled'])->toBeFalse()->and($rules->get('usage.watch')['last']['stats']['skipped'])->toBe(1)->and($rules->get('provisioning.tick')['switchable'])->toBeFalse()->and($rules->get('renewal.guard')['enabled'])->toBeTrue();

    // no switch on the rules the platform needs; unknown rules are 404; the switch goes back
    $this->putJson('/v1/staff/automation/provisioning.tick', ['enabled' => false])->assertStatus(422)->assertJsonPath('error', 'automation_rule_required');
    $this->putJson('/v1/staff/automation/nope', ['enabled' => false])->assertNotFound();
    $this->putJson('/v1/staff/automation/usage.watch', ['enabled' => true])->assertOk()->assertJsonPath('enabled', true);
    expect(app(SettingsStore::class)->get(AutomationLedger::SETTING))->toBe([]);

    // the order intake check honours its switch: the same suspicious quote is held with it on and passes with it off
    [$owner, $org] = $this->customerWithOrganization(['email' => 'x@mailinator.com'], ['type' => 'company', 'billing_email' => 'x@mailinator.com']);
    $ctx = $this->contextFor($owner, $org);
    $quote = app(QuoteService::class)->quote([['product_key' => 'web-hosting', 'plan_key' => 'start']], 'CZK', ['country' => $org->country, 'customer_class' => $org->customer_class, 'vat_status' => $org->vat_status], 1, null, $org);
    expect(app(OrderRiskService::class)->assess($quote, $org, $owner, $ctx)['hold'])->toBeTrue();
    app(AutomationLedger::class)->setEnabled('order.risk', false, 'test');
    expect(app(OrderRiskService::class)->assess($quote, $org, $owner, $ctx)['hold'])->toBeFalse();

    $this->actingAs($owner, 'sanctum')->putJson('/v1/staff/automation/usage.watch', ['enabled' => false])->assertForbidden();
});

it('reports the scheduler and the worker heartbeat, alerts once when the worker stalls, and the doctor flags it', function () {
    Http::fake();
    $ledger = app(AutomationLedger::class);
    $live = $ledger->liveness();
    expect($live['scheduler']['alive'])->toBeFalse()->and($live['worker']['alive'])->toBeTrue()->and($live['worker']['driver'])->toBe('sync'); // sync driver: the request is the worker

    $this->artisan('onhost:provisioning:tick')->assertSuccessful();
    expect($ledger->liveness()['scheduler']['alive'])->toBeTrue();

    // a real queue with nobody answering: one internal alert per half hour, the health record says so, the doctor warns (fails in production)
    config()->set('queue.default', 'database');
    expect($ledger->liveness()['worker']['alive'])->toBeFalse();
    $this->artisan('onhost:integrations:health')->assertSuccessful();
    $this->artisan('onhost:integrations:health')->assertSuccessful();
    expect(OutboxMessage::query()->where('name', 'platform.queue.stalled')->count())->toBe(1)->and($ledger->last('integrations.health')['stats']['worker'])->toBe('stalled');
    app(OutboxPublisher::class)->relayPending();
    expect(Notification::query()->where('audience', 'internal')->where('title', 'like', 'Fronta úloh stojí%')->exists())->toBeTrue();
    expect(Artisan::call('onhost:doctor', ['--json' => true]))->toBe(0);
    $checks = collect(json_decode(trim(Artisan::output()), true, 512, JSON_THROW_ON_ERROR)['checks']);
    expect($checks->firstWhere('check', 'queue worker alive')['status'])->toBe('WARN')->and($checks->firstWhere('check', 'scheduler running')['status'])->toBe('OK')->and($checks->firstWhere('check', 'no rule switched off')['status'])->toBe('OK');

    // the heartbeat job stamps the cache and the worker counts as alive again; the console shows both machines and the queued heartbeat in the plan
    QueueHeartbeat::dispatchSync();
    expect(QueueHeartbeat::lastSeenAt(Cache::store()))->not->toBeNull()->and($ledger->liveness()['worker']['alive'])->toBeTrue();
    $jobs = $this->actingAs($this->staff('platform_owner'), 'sanctum')->getJson('/v1/staff/jobs')->assertOk()->json('data');
    expect($jobs['liveness']['worker']['alive'])->toBeTrue()->and($jobs['liveness']['scheduler']['alive'])->toBeTrue()->and($jobs['bulk_actions'])->toContain('backup')
        ->and(collect($jobs['scheduler'])->first(fn ($e) => $e['command'] === 'job:QueueHeartbeat'))->not->toBeNull()->and($jobs['backlog'])->toMatchArray(['stale' => 0, 'alert' => false]);

    // operations due for longer than the age limit pile up (§5h-5): the console counts them per queue, the health rule alerts once, the doctor warns, the metrics carry the gauge
    config()->set('onhost.provisioning.backlog.threshold', 2);
    $operations = app(OperationService::class);
    foreach (['bl-1', 'bl-2'] as $key) {
        $operations->start(GameMigrationWorkflow::class, $key, ['target_node_id' => null], CommandContext::system('test'), null, null, null, null, null, false)->forceFill(['next_run_at' => now()->subMinutes(10)])->save();
    }
    $backlog = $ledger->backlog();
    expect($backlog)->toMatchArray(['stale' => 2, 'alert' => true, 'threshold' => 2])->and($backlog['by_queue'])->toBe(['provider-pterodactyl' => 2]);
    $this->artisan('onhost:integrations:health')->assertSuccessful();
    $this->artisan('onhost:integrations:health')->assertSuccessful();
    expect(OutboxMessage::query()->where('name', 'platform.queue.backlog')->count())->toBe(1)->and($ledger->last('integrations.health')['stats']['backlog'])->toBe(2);
    app(OutboxPublisher::class)->relayPending();
    expect(Notification::query()->where('audience', 'internal')->where('title', 'like', 'Fronta operací se hromadí: 2 čeká déle než 5 min%')->exists())->toBeTrue();
    expect(Artisan::call('onhost:doctor', ['--json' => true]))->toBe(0);
    $checks = collect(json_decode(trim(Artisan::output()), true, 512, JSON_THROW_ON_ERROR)['checks']);
    expect($checks->firstWhere('check', 'operation backlog'))->toMatchArray(['status' => 'WARN']);
    expect($this->get('/metrics')->assertOk()->getContent())->toContain('onhost_operations_backlog{queue="provider-pterodactyl"} 2')->toContain('onhost_operations_backlog_threshold 2');
});

it('lists every rule a scheduled command can be switched off by', function () {
    $console = (string) file_get_contents(base_path('routes/console.php'));
    preg_match_all("/->off\('([a-z0-9_.]+)'\)/", $console, $m);
    $used = array_values(array_unique($m[1]));
    $listed = array_column(AutomationLedger::RULES, 'key');

    // a rule the console asks about but never lists is one staff cannot switch off — the console shows what it can
    expect(count($used))->toBeGreaterThan(10)
        ->and(array_values(array_diff($used, $listed)))->toBe([], 'add these to AutomationLedger::RULES: '.implode(', ', array_diff($used, $listed)));
});
