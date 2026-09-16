<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Billing\ChargebackService;
use Onhost\Domain\Billing\Models\ChargebackRequest;
use Onhost\Domain\Billing\Models\Subscription;
use Onhost\Domain\Catalog\CatalogService;
use Onhost\Domain\Identity\StepUp\StepUpService;
use Onhost\Domain\Notifications\Models\Notification;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\WalletLedger\WalletService;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Outbox\OutboxPublisher;

/*
 * Chargeback in credit: the customer asks to leave a paid service early, technical support decides, the customer
 * cancels after the approval and the share staff set (default 70 %) of the unused, already paid period comes back as
 * wallet credit once the termination is confirmed — never as money, never twice, never without the approval.
 */

beforeEach(function () {
    $this->seed([CatalogSeeder::class, TaxRuleSeeder::class, LegalEntitySeeder::class]);
    Http::preventStrayRequests();
});

it('requests, approves, cancels and refunds the configured share of the unused period as credit', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $service = featureGameService($org);
    $plan = app(CatalogService::class)->resolve('game', 'game-8', 'CZK', 'month');
    Subscription::query()->create([
        'organization_id' => $org->id, 'service_id' => $service->id, 'plan_version_id' => $plan['version']->id, 'price_id' => $plan['price']->id, 'currency' => 'CZK', 'period' => 'month', 'amount_minor' => 30000,
        'state' => Subscription::ACTIVE, 'current_period_start' => now()->subDays(20), 'current_period_end' => now()->addDays(10), 'next_renewal_at' => now()->addDays(10), 'auto_renew' => true, 'renewal_priority' => 'normal',
    ]);
    Http::fake(function (Request $request) {
        if (str_contains($request->url(), 'wings.test/download')) { // the signed archive download of the final backup
            return Http::response(str_repeat('game archive', 40));
        }
        if (! str_starts_with($request->url(), PTERO)) {
            return null;
        }
        $path = (string) parse_url($request->url(), PHP_URL_PATH);

        return match (true) {
            $path === '/api/application/servers/77' => Http::response(['object' => 'server', 'attributes' => ['id' => 77, 'uuid' => 'u', 'identifier' => 'e4c1abcd', 'name' => 'mc-liga', 'suspended' => false, 'status' => null, 'user' => 9, 'node' => 2, 'allocation' => 11, 'nest' => 1, 'egg' => 3, 'limits' => ['memory' => 8192, 'disk' => 61440, 'cpu' => 300], 'feature_limits' => ['databases' => 2, 'allocations' => 2, 'backups' => 5], 'container' => ['installed' => 1, 'environment' => []]]]),
            $path === '/api/client/servers/e4c1abcd/backups' && $request->method() === 'POST' => Http::response(['object' => 'backup', 'attributes' => ['uuid' => 'bk-final', 'name' => 'final', 'is_successful' => false, 'is_locked' => false, 'bytes' => 0, 'completed_at' => null, 'created_at' => now()->toIso8601String()]]),
            $path === '/api/client/servers/e4c1abcd/backups' && $request->method() === 'GET' => Http::response(['object' => 'list', 'data' => [['object' => 'backup', 'attributes' => ['uuid' => 'bk-final', 'name' => 'final', 'is_successful' => true, 'is_locked' => false, 'bytes' => 1024, 'completed_at' => now()->toIso8601String(), 'created_at' => now()->toIso8601String()]]]]),
            $path === '/api/client/servers/e4c1abcd/backups/bk-final' => Http::response(['object' => 'backup', 'attributes' => ['uuid' => 'bk-final', 'name' => 'final', 'is_successful' => true, 'is_locked' => false, 'bytes' => 1024, 'completed_at' => now()->toIso8601String(), 'created_at' => now()->toIso8601String()]]),
            $path === '/api/client/servers/e4c1abcd/backups/bk-final/download' => Http::response(['object' => 'signed_url', 'attributes' => ['url' => 'https://wings.test/download/backup?token=abc']]),
            $path === '/api/application/servers/77/suspend' => Http::response('', 204), // the cancellation deactivates first, the removal comes after the restore window
            $path === '/api/application/servers/77/force' => Http::response('', 204),
            default => Http::response(['errors' => [['code' => 'NotFoundHttpException', 'status' => '404', 'detail' => "no fake for {$path}"]]], 404),
        };
    });
    $chargebacks = app(ChargebackService::class);
    expect($chargebacks->percent())->toBe(70);
    $this->actingAs($owner, 'sanctum');

    // what a cancellation now would return: 10 of 30 days unused of 300 Kč → 100 Kč × 70 % = 70 Kč
    $before = $this->getJson("/v1/services/{$service->id}/chargeback")->assertOk()->json('data');
    expect($before['request'])->toBeNull()->and($before['estimate'])->toMatchArray(['unused_minor' => 10000, 'percent' => 70, 'refund_minor' => 7000, 'currency' => 'CZK']);

    // the request: a reason is required, one open request per service, support hears about it, the customer gets an acknowledgement
    $this->withHeader('Idempotency-Key', 'cb-1')->postJson("/v1/services/{$service->id}/chargeback", ['reason' => 'moc'])->assertStatus(422);
    $requested = $this->withHeader('Idempotency-Key', 'cb-2')->postJson("/v1/services/{$service->id}/chargeback", ['reason' => 'Přecházíme na vlastní hardware, server už nepotřebujeme.'])->assertCreated()->json();
    $this->flushHeaders();
    expect($requested['state'])->toBe('requested')->and($requested['refund']['minor'])->toBe(7000);
    $this->withHeader('Idempotency-Key', 'cb-3')->postJson("/v1/services/{$service->id}/chargeback", ['reason' => 'Ještě jednou, prosím.'])->assertStatus(409)->assertJsonPath('error', 'chargeback_already_open');
    $this->flushHeaders();
    $this->postJson("/v1/services/{$service->id}/chargeback/cancel")->assertStatus(403)->assertJsonPath('error', 'step_up_required'); // cancelling destroys a service: a fresh step-up first, always
    app(OutboxPublisher::class)->relayPending();
    expect(Notification::query()->where('audience', 'internal')->where('title', 'like', 'Žádost o vrácení kreditu: mc-liga%')->exists())->toBeTrue()
        ->and(Notification::query()->where('organization_id', $org->id)->where('title', 'Žádost o vrácení kreditu přijata')->exists())->toBeTrue();

    // support: the queue, the share (staff set it to 50 % before approving), the approval fixes the share
    $staff = $this->staff('platform_owner');
    $this->actingAs($staff, 'sanctum');
    $queue = $this->getJson('/v1/staff/chargebacks?state=open')->assertOk()->json('data');
    expect($queue['rows'])->toHaveCount(1)->and($queue['rows'][0])->toMatchArray(['state' => 'requested', 'organization' => 'Test s.r.o.'])->and($queue['rows'][0]['service']['label'])->toBe('mc-liga')->and($queue['open'])->toBe(1);
    $this->withHeader('Idempotency-Key', 'cb-set-1')->putJson('/v1/staff/chargebacks/settings', ['percent' => 50])->assertOk()->assertJsonPath('percent', 50);
    $this->flushHeaders();
    $this->putJson('/v1/staff/chargebacks/settings', ['percent' => 120])->assertStatus(422);
    $this->postJson("/v1/staff/chargebacks/{$requested['id']}/decide", ['decision' => 'maybe'])->assertStatus(422);
    $approved = $this->withHeader('Idempotency-Key', 'cb-dec-1')->postJson("/v1/staff/chargebacks/{$requested['id']}/decide", ['decision' => 'approve', 'reason' => 'Rozumíme, přejeme hodně štěstí.'])->assertOk()->json();
    $this->flushHeaders();
    expect($approved)->toMatchArray(['state' => 'approved', 'percent' => 50])->and($approved['refund']['minor'])->toBe(5000);
    $this->postJson("/v1/staff/chargebacks/{$requested['id']}/decide", ['decision' => 'reject'])->assertStatus(409)->assertJsonPath('error', 'chargeback_not_pending');
    app(OutboxPublisher::class)->relayPending();
    expect(Notification::query()->where('organization_id', $org->id)->where('title', 'Vrácení kreditu za mc-liga schváleno')->value('body'))->toContain('50 Kč')->toContain('50 %');

    // a later change of the share does not touch an approved request; the customer cancels (fresh step-up), the service terminates with a final backup, the credit lands once the termination is confirmed
    $this->withHeader('Idempotency-Key', 'cb-set-2')->putJson('/v1/staff/chargebacks/settings', ['percent' => 90])->assertOk();
    $this->flushHeaders();
    $this->actingAs($owner, 'sanctum');
    app(StepUpService::class)->grant($owner, 'totp', null, '127.0.0.1');
    $cancelling = $this->withHeader('Idempotency-Key', 'cb-cancel-1')->postJson("/v1/services/{$service->id}/chargeback/cancel")->assertStatus(202)->json();
    $this->flushHeaders();
    expect($cancelling['state'])->toBe('cancelling')->and($cancelling['refund']['minor'])->toBe(5000)->and($cancelling['operation_id'])->not->toBeNull();
    $operation = driveOperation(Operation::query()->findOrFail($cancelling['operation_id']));
    $cancelled = Service::query()->withTrashed()->findOrFail($service->id);
    expect($operation->state)->toBe(Operation::SUCCEEDED)->and($cancelled->state)->toBe('SUSPENDED')->and($cancelled->terminate_at)->not->toBeNull(); // deactivated with the archive done; the removal follows the restore window
    app(OutboxPublisher::class)->relayPending(); // service.deactivated → the chargeback settles right away, the customer does not wait 30 days for the credit
    $request = ChargebackRequest::query()->findOrFail($requested['id']);
    expect($request->state)->toBe('refunded')->and($request->refunded_at)->not->toBeNull()->and($request->refund_minor)->toBe(5000);
    expect(app(WalletService::class)->balances($org, 'CZK')['posted']->minor)->toBe(5000);
    app(OutboxPublisher::class)->relayPending();
    expect(Notification::query()->where('organization_id', $org->id)->where('title', 'Kredit za zrušenou službu připsán')->exists())->toBeTrue();
    expect($chargebacks->settleForService($service->id, CommandContext::system('again')))->toBeNull(); // settled once, never twice
    expect($this->actingAs($staff, 'sanctum')->getJson('/v1/staff/chargebacks?state=refunded')->assertOk()->json('data.rows.0.state'))->toBe('refunded');

    // a rejection leaves the service and the credit alone; a cancel without an approval is refused even with a step-up
    $other = featureGameService($org, [], 78, 'e4c1abce');
    Subscription::query()->create(['organization_id' => $org->id, 'service_id' => $other->id, 'plan_version_id' => $plan['version']->id, 'price_id' => $plan['price']->id, 'currency' => 'CZK', 'period' => 'month', 'amount_minor' => 30000, 'state' => Subscription::ACTIVE, 'current_period_start' => now()->subDays(1), 'current_period_end' => now()->addDays(29), 'next_renewal_at' => now()->addDays(29), 'auto_renew' => true, 'renewal_priority' => 'normal']);
    $second = $this->withHeader('Idempotency-Key', 'cb-4')->postJson("/v1/services/{$other->id}/chargeback", ['reason' => 'Nechci platit celý měsíc.'])->assertCreated()->json();
    $this->flushHeaders();
    $this->actingAs($staff, 'sanctum')->withHeader('Idempotency-Key', 'cb-dec-2')->postJson("/v1/staff/chargebacks/{$second['id']}/decide", ['decision' => 'reject', 'reason' => 'Služba běží podle smlouvy; výpověď platí ke konci období.'])->assertOk()->assertJsonPath('state', 'rejected');
    $this->flushHeaders();
    expect(Service::query()->findOrFail($other->id)->state)->toBe('ACTIVE')->and(app(WalletService::class)->balances($org, 'CZK')['posted']->minor)->toBe(5000);
    $this->actingAs($owner, 'sanctum')->withHeader('Idempotency-Key', 'cb-cancel-2')->postJson("/v1/services/{$other->id}/chargeback/cancel")->assertStatus(409)->assertJsonPath('error', 'chargeback_not_approved');
    $this->flushHeaders();
    app(OutboxPublisher::class)->relayPending();
    expect(Notification::query()->where('organization_id', $org->id)->where('title', 'Vrácení kreditu za mc-liga jsme nemohli schválit')->value('body'))->toContain('ke konci období');
});
