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
use Onhost\Domain\Invoicing\Models\Invoice;
use Onhost\Domain\Invoicing\Models\InvoiceLine;
use Onhost\Domain\Notifications\Models\Notification;
use Onhost\Domain\Orders\Models\OrderItem;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Domain\WalletLedger\LedgerService;
use Onhost\Domain\WalletLedger\Models\LedgerAccount;
use Onhost\Domain\WalletLedger\WalletService;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Money\Money;
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

/** Request → approval → the service is gone → the cancellation settles at once. */
function chargebackThrough(Service $service, Organization $org): ChargebackRequest
{
    $chargebacks = app(ChargebackService::class);
    $ctx = CommandContext::system('test')->withScope($org->id);
    $request = $chargebacks->decide($chargebacks->request($service, null, 'Službu už nepotřebujeme.', $ctx), 'approve', null, $ctx);
    $service->forceFill(['state' => ServiceStateMachine::TERMINATED])->save();

    return $chargebacks->cancelService($request, $ctx)->refresh();
}

it('returns a share of what was PAID — twelve months in advance, a discount, the VAT — not of the price list', function () {
    [, $org] = $this->customerWithOrganization();
    $plan = app(CatalogService::class)->resolve('game', 'game-8', 'CZK', 'month');
    $subscription = fn (Service $s, int $from, int $to) => Subscription::query()->create(['organization_id' => $org->id, 'service_id' => $s->id, 'plan_version_id' => $plan['version']->id, 'price_id' => $plan['price']->id, 'currency' => 'CZK', 'period' => 'month', 'amount_minor' => 30000,
        'state' => Subscription::ACTIVE, 'current_period_start' => now()->addDays($from), 'current_period_end' => now()->addDays($to), 'next_renewal_at' => now()->addDays($to), 'auto_renew' => true, 'renewal_priority' => 'normal']);
    $chargebacks = app(ChargebackService::class);

    // twelve months paid in advance (12 × 363 Kč), one month used: the old rule returned a share of ONE month's list price without VAT
    $year = featureGameService($org);
    $subscription($year, -30, 334);
    chargebackPaidStatement($org, $year, 435600, 75600, -30, 334);
    $estimate = $chargebacks->estimate($year);
    expect($estimate['lines'][0])->toMatchArray(['days' => 365, 'days_left' => 334])->and($estimate['unused_minor'])->toBe((int) round(435600 * 334 / 365))->and($estimate['refund_minor'])->toBe((int) round($estimate['unused_minor'] * 0.7))
        ->and($estimate['refund_minor'])->toBeGreaterThan(270000); // it was 19 215: 70 % of 334/365 of 300 Kč

    // bought with an 80 % code: what comes back is a share of what was paid, never more than that
    $cheap = featureGameService($org, [], 78, 'e4c1abce');
    $subscription($cheap, -1, 29);
    chargebackPaidStatement($org, $cheap, 7260, 1260, -1, 28);
    $estimate = $chargebacks->estimate($cheap);
    expect($estimate['unused_minor'])->toBe((int) round(7260 * 28 / 30))->and($estimate['refund_minor'])->toBeLessThan(7260); // it was 70 % of 29/30 of the 300 Kč list price: 203 Kč for 72,60 Kč paid

    // a change of the billing period was priced minus the unused rest of the month before it: that month does not come back a second time
    $changed = featureGameService($org, [], 80, 'e4c1abd0');
    $subscription($changed, 0, 365);
    $month = chargebackPaidStatement($org, $changed, 36300, 6300, -10, 19);
    InvoiceLine::query()->where('invoice_id', $month->id)->update(['created_at' => now()->subDays(10)]);
    $item = OrderItem::query()->create(['order_id' => 'ord_period_change', 'product_key' => 'game', 'sku' => 'game-8', 'name' => 'Změna období: Herní server 8 GB (ročně)', 'qty' => 1, 'period' => 'year',
        'unit_net_minor' => 340000, 'discount_minor' => 0, 'tax_rate' => '21', 'tax_minor' => 71400, 'total_minor' => 411400, 'state' => 'active', 'service_id' => $changed->id, 'config' => ['upgrade_of' => $changed->id, 'plan_change' => ['period_change' => true]]]);
    $yearly = chargebackPaidStatement($org, $changed, 411400, 71400, 0, 364);
    InvoiceLine::query()->where('invoice_id', $yearly->id)->update(['order_item_id' => $item->id, 'service_id' => null]);
    $estimate = $chargebacks->estimate($changed);
    expect($estimate['lines'])->toHaveCount(1)->and($estimate['lines'][0]['number'])->toBe($yearly->number);

    // nothing paid, nothing to return: a service staff created without an order has a subscription and no document
    $free = featureGameService($org, [], 79, 'e4c1abcf');
    $subscription($free, -1, 29);
    expect($chargebacks->estimate($free)['refund_minor'])->toBe(0);
    expect(fn () => $chargebacks->request($free, null, 'Službu už nepotřebujeme.', CommandContext::system('test')))->toThrow(DomainError::class, 'nothing to refund');
});

it('books the return against the revenue and the VAT it had earned, as credit that cannot be paid out in money', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $ctx = $this->contextFor($owner, $org);
    $wallets = app(WalletService::class);
    $ledger = app(LedgerService::class);
    // the customer never paid money in: the service was bought with bonus credit
    $wallets->topup($org, Money::minor(36300, 'CZK'), 'promo', 'cb-promo', $ctx, promo: true);
    $service = featureGameService($org);
    $plan = app(CatalogService::class)->resolve('game', 'game-8', 'CZK', 'month');
    Subscription::query()->create(['organization_id' => $org->id, 'service_id' => $service->id, 'plan_version_id' => $plan['version']->id, 'price_id' => $plan['price']->id, 'currency' => 'CZK', 'period' => 'month', 'amount_minor' => 30000, 'state' => Subscription::ACTIVE,
        'current_period_start' => now()->subDays(19), 'current_period_end' => now()->addDays(11), 'next_renewal_at' => now()->addDays(11), 'auto_renew' => true, 'renewal_priority' => 'normal']);
    $hold = $wallets->hold($org, Money::minor(36300, 'CZK'), 'order', 'cb-hold', $ctx); // the bonus credit covers it
    $wallets->capture($hold, 'game', $ctx, Money::minor(36300, 'CZK'), Money::minor(6300, 'CZK'));
    chargebackPaidStatement($org, $service, 36300, 6300, -19, 10);
    expect($ledger->balance(LedgerService::vatAccount('CZK'), 'CZK')->minor)->toBe(6300)->and($wallets->balances($org, 'CZK')['available']->minor)->toBe(0);

    $request = chargebackThrough($service, $org);

    // 70 % of 121 Kč = 84,70 Kč: on the credit, taken from the revenue (70,00) and the VAT (14,70) — not from a bank called "chargeback"
    expect($request->state)->toBe('refunded')->and($request->refund_minor)->toBe(8470)->and($wallets->balances($org, 'CZK')['available']->minor)->toBe(8470)
        ->and($ledger->balance(LedgerService::vatAccount('CZK'), 'CZK')->minor)->toBe(6300 - 1470)->and($ledger->balance(LedgerService::revenueAccount('credit_note', 'CZK'), 'CZK')->minor)->toBe(-7000)
        ->and(LedgerAccount::query()->where('code', 'like', 'asset:bank:chargeback%')->exists())->toBeFalse()->and($ledger->verifyInvariant()['balanced'])->toBeTrue();
    // bonus credit did not turn into cash: nothing can be paid out
    expect($wallets->refundableBalance($org->id, 'CZK')->minor)->toBe(0);

    // settled once: an event delivered again finds nothing to settle, and a second settlement of the same request changes nothing
    expect(app(ChargebackService::class)->settleForService($service->id, CommandContext::system('again')))->toBeNull()->and(Invoice::query()->where('type', 'credit_note')->count())->toBe(1);
});

it('makes an unpaid invoice smaller instead of paying credit out for money that never came', function () {
    [, $org] = $this->customerWithOrganization();
    $service = featureGameService($org);
    $plan = app(CatalogService::class)->resolve('game', 'game-8', 'CZK', 'month');
    Subscription::query()->create(['organization_id' => $org->id, 'service_id' => $service->id, 'plan_version_id' => $plan['version']->id, 'price_id' => $plan['price']->id, 'currency' => 'CZK', 'period' => 'month', 'amount_minor' => 30000, 'state' => Subscription::ACTIVE,
        'current_period_start' => now()->subDays(19), 'current_period_end' => now()->addDays(11), 'next_renewal_at' => now()->addDays(11), 'auto_renew' => true, 'renewal_priority' => 'normal']);
    $invoice = chargebackPaidStatement($org, $service, 36300, 6300, -19, 10, 'invoice', false); // a postpaid renewal nobody paid yet
    $ledger = app(LedgerService::class);
    expect($ledger->balance(LedgerService::receivableAccount($org->id, 'CZK'), 'CZK')->minor)->toBe(36300);

    $request = chargebackThrough($service, $org);

    expect($request->refund_minor)->toBe(8470)->and(data_get($request->basis, 'to_credit_minor'))->toBe(0)->and(data_get($request->basis, 'off_document_minor'))->toBe(8470)
        ->and(app(WalletService::class)->balances($org, 'CZK')['available']->minor)->toBe(0) // it used to be 70 Kč of purchased credit for an invoice that was never paid
        ->and($invoice->refresh()->outstanding()->minor)->toBe(36300 - 8470)->and($ledger->balance(LedgerService::receivableAccount($org->id, 'CZK'), 'CZK')->minor)->toBe(36300 - 8470);
});

it('requests, approves, cancels and refunds the configured share of the unused period as credit', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $service = featureGameService($org);
    $plan = app(CatalogService::class)->resolve('game', 'game-8', 'CZK', 'month');
    Subscription::query()->create([
        'organization_id' => $org->id, 'service_id' => $service->id, 'plan_version_id' => $plan['version']->id, 'price_id' => $plan['price']->id, 'currency' => 'CZK', 'period' => 'month', 'amount_minor' => 30000,
        'state' => Subscription::ACTIVE, 'current_period_start' => now()->subDays(20), 'current_period_end' => now()->addDays(10), 'next_renewal_at' => now()->addDays(10), 'auto_renew' => true, 'renewal_priority' => 'normal',
    ]);
    $statement = chargebackPaidStatement($org, $service, 36300, 6300, -19, 10); // 300 Kč + 21 % paid for thirty days, ten of them still ahead
    Http::fake(function (Request $request) {
        if (str_contains($request->url(), 'wings.test/download')) { // the signed archive download of the final backup
            return Http::response(str_repeat('game archive', 40));
        }
        if (! str_starts_with($request->url(), PTERO)) {
            return null;
        }
        $path = (string) parse_url($request->url(), PHP_URL_PATH);

        return match (true) {
            $path === '/api/application/servers/77' && $request->method() === 'GET' => Http::response(['object' => 'server', 'attributes' => ['id' => 77, 'uuid' => 'u', 'identifier' => 'e4c1abcd', 'name' => 'mc-liga', 'suspended' => false, 'status' => null, 'user' => 9, 'node' => 2, 'allocation' => 11, 'nest' => 1, 'egg' => 3, 'limits' => ['memory' => 8192, 'disk' => 61440, 'cpu' => 300], 'feature_limits' => ['databases' => 2, 'allocations' => 2, 'backups' => 5], 'container' => ['installed' => 1, 'environment' => []]]]),
            $path === '/api/client/servers/e4c1abcd/backups' && $request->method() === 'POST' => Http::response(['object' => 'backup', 'attributes' => ['uuid' => 'bk-final', 'name' => 'final', 'is_successful' => false, 'is_locked' => false, 'bytes' => 0, 'completed_at' => null, 'created_at' => now()->toIso8601String()]]),
            $path === '/api/client/servers/e4c1abcd/backups' && $request->method() === 'GET' => Http::response(['object' => 'list', 'data' => [['object' => 'backup', 'attributes' => ['uuid' => 'bk-final', 'name' => 'final', 'is_successful' => true, 'is_locked' => false, 'bytes' => 1024, 'completed_at' => now()->toIso8601String(), 'created_at' => now()->toIso8601String()]]]]),
            $path === '/api/client/servers/e4c1abcd/backups/bk-final' => Http::response(['object' => 'backup', 'attributes' => ['uuid' => 'bk-final', 'name' => 'final', 'is_successful' => true, 'is_locked' => false, 'bytes' => 1024, 'completed_at' => now()->toIso8601String(), 'created_at' => now()->toIso8601String()]]),
            $path === '/api/application/nodes' => Http::response(['object' => 'list', 'data' => [['object' => 'node', 'attributes' => ['id' => 2, 'name' => 'games01', 'fqdn' => 'wings.test']]], 'meta' => ['pagination' => ['total_pages' => 1]]]),
            $path === '/api/client/servers/e4c1abcd/backups/bk-final/download' => Http::response(['object' => 'signed_url', 'attributes' => ['url' => 'https://wings.test/download/backup?token=abc']]),
            $path === '/api/client/servers/e4c1abcd/users' && $request->method() === 'GET' => Http::response(['object' => 'list', 'data' => []]), // the cancellation revokes collaborators (H346): none here
            $path === '/api/application/servers/77/suspend' => Http::response('', 204), // the cancellation deactivates first, the removal comes after the restore window
            $path === '/api/application/servers/77' && $request->method() === 'DELETE' => Http::response('', 204), // deleted without force: the panel removes the files and databases, or refuses
            default => Http::response(['errors' => [['code' => 'NotFoundHttpException', 'status' => '404', 'detail' => "no fake for {$path}"]]], 404),
        };
    });
    $chargebacks = app(ChargebackService::class);
    expect($chargebacks->percent())->toBe(70);
    $this->actingAs($owner, 'sanctum');

    // what a cancellation now would return: 10 of 30 days unused of the 363 Kč that were paid → 121 Kč × 70 % = 84,70 Kč
    $before = $this->getJson("/v1/services/{$service->id}/chargeback")->assertOk()->json('data');
    expect($before['request'])->toBeNull()->and($before['estimate'])->toMatchArray(['unused_minor' => 12100, 'percent' => 70, 'refund_minor' => 8470, 'currency' => 'CZK'])->and($before['estimate']['lines'][0]['number'])->toBe($statement->number);

    // the request: a reason is required, one open request per service, support hears about it, the customer gets an acknowledgement
    $this->withHeader('Idempotency-Key', 'cb-1')->postJson("/v1/services/{$service->id}/chargeback", ['reason' => 'moc'])->assertStatus(422);
    $requested = $this->withHeader('Idempotency-Key', 'cb-2')->postJson("/v1/services/{$service->id}/chargeback", ['reason' => 'Přecházíme na vlastní hardware, server už nepotřebujeme.'])->assertCreated()->json();
    $this->flushHeaders();
    expect($requested['state'])->toBe('requested')->and($requested['refund']['minor'])->toBe(8470);
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
    expect($queue['can_set_share'])->toBeTrue();
    // the share is money policy for every customer: finance permission and a fresh step-up (H348)
    $this->putJson('/v1/staff/chargebacks/settings', ['percent' => 50])->assertForbidden()->assertJsonPath('error', 'step_up_required');
    app(StepUpService::class)->grant($staff, 'totp', null, '127.0.0.1');
    $this->withHeader('Idempotency-Key', 'cb-set-1')->putJson('/v1/staff/chargebacks/settings', ['percent' => 50])->assertOk()->assertJsonPath('percent', 50);
    $this->flushHeaders();
    $this->putJson('/v1/staff/chargebacks/settings', ['percent' => 120])->assertStatus(422);
    $this->postJson("/v1/staff/chargebacks/{$requested['id']}/decide", ['decision' => 'maybe'])->assertStatus(422);
    $approved = $this->withHeader('Idempotency-Key', 'cb-dec-1')->postJson("/v1/staff/chargebacks/{$requested['id']}/decide", ['decision' => 'approve', 'reason' => 'Rozumíme, přejeme hodně štěstí.'])->assertOk()->json();
    $this->flushHeaders();
    expect($approved)->toMatchArray(['state' => 'approved', 'percent' => 50])->and($approved['refund']['minor'])->toBe(6050);
    $this->postJson("/v1/staff/chargebacks/{$requested['id']}/decide", ['decision' => 'reject'])->assertStatus(409)->assertJsonPath('error', 'chargeback_not_pending');
    app(OutboxPublisher::class)->relayPending();
    expect(Notification::query()->where('organization_id', $org->id)->where('title', 'Vrácení kreditu za mc-liga schváleno')->value('body'))->toContain('60,5')->toContain('50 %');

    // a later change of the share does not touch an approved request; the customer cancels (fresh step-up), the service terminates with a final backup, the credit lands once the termination is confirmed
    $this->withHeader('Idempotency-Key', 'cb-set-2')->putJson('/v1/staff/chargebacks/settings', ['percent' => 90])->assertOk();
    $this->flushHeaders();
    $this->actingAs($owner, 'sanctum');
    app(StepUpService::class)->grant($owner, 'totp', null, '127.0.0.1');
    $cancelling = $this->withHeader('Idempotency-Key', 'cb-cancel-1')->postJson("/v1/services/{$service->id}/chargeback/cancel")->assertStatus(202)->json();
    $this->flushHeaders();
    expect($cancelling['state'])->toBe('cancelling')->and($cancelling['refund']['minor'])->toBe(6050)->and($cancelling['operation_id'])->not->toBeNull();
    $operation = driveOperation(Operation::query()->findOrFail($cancelling['operation_id']));
    $cancelled = Service::query()->withTrashed()->findOrFail($service->id);
    expect($operation->state)->toBe(Operation::SUCCEEDED)->and($cancelled->state)->toBe('SUSPENDED')->and($cancelled->terminate_at)->not->toBeNull(); // deactivated with the archive done; the removal follows the restore window
    app(OutboxPublisher::class)->relayPending(); // service.deactivated → the chargeback settles right away, the customer does not wait 30 days for the credit
    $request = ChargebackRequest::query()->findOrFail($requested['id']);
    expect($request->state)->toBe('refunded')->and($request->refunded_at)->not->toBeNull()->and($request->refund_minor)->toBe(6050);
    expect(app(WalletService::class)->balances($org, 'CZK')['posted']->minor)->toBe(6050);
    // the return is a credit note for exactly that part of the paid line, and the statement says how much of it is gone
    $note = Invoice::query()->where('corrects_invoice_id', $statement->id)->where('type', 'credit_note')->firstOrFail();
    expect($note->total_minor)->toBe(-6050)->and($note->tax_minor)->toBe(-1050)->and($statement->refresh()->credited_minor)->toBe(6050)->and($statement->state)->toBe(Invoice::PAID)
        ->and(data_get($request->basis, 'credit_notes'))->toBe([$note->number]);
    app(OutboxPublisher::class)->relayPending();
    expect(Notification::query()->where('organization_id', $org->id)->where('title', 'Kredit za zrušenou službu připsán')->exists())->toBeTrue();
    expect($chargebacks->settleForService($service->id, CommandContext::system('again')))->toBeNull(); // settled once, never twice
    expect($this->actingAs($staff, 'sanctum')->getJson('/v1/staff/chargebacks?state=refunded')->assertOk()->json('data.rows.0.state'))->toBe('refunded');

    // a rejection leaves the service and the credit alone; a cancel without an approval is refused even with a step-up
    $other = featureGameService($org, [], 78, 'e4c1abce');
    Subscription::query()->create(['organization_id' => $org->id, 'service_id' => $other->id, 'plan_version_id' => $plan['version']->id, 'price_id' => $plan['price']->id, 'currency' => 'CZK', 'period' => 'month', 'amount_minor' => 30000, 'state' => Subscription::ACTIVE, 'current_period_start' => now()->subDays(1), 'current_period_end' => now()->addDays(29), 'next_renewal_at' => now()->addDays(29), 'auto_renew' => true, 'renewal_priority' => 'normal']);
    chargebackPaidStatement($org, $other, 36300, 6300, -1, 29);
    $second = $this->withHeader('Idempotency-Key', 'cb-4')->postJson("/v1/services/{$other->id}/chargeback", ['reason' => 'Nechci platit celý měsíc.'])->assertCreated()->json();
    $this->flushHeaders();
    $this->actingAs($staff, 'sanctum')->withHeader('Idempotency-Key', 'cb-dec-2')->postJson("/v1/staff/chargebacks/{$second['id']}/decide", ['decision' => 'reject', 'reason' => 'Služba běží podle smlouvy; výpověď platí ke konci období.'])->assertOk()->assertJsonPath('state', 'rejected');
    $this->flushHeaders();
    expect(Service::query()->findOrFail($other->id)->state)->toBe('ACTIVE')->and(app(WalletService::class)->balances($org, 'CZK')['posted']->minor)->toBe(6050);
    $this->actingAs($owner, 'sanctum')->withHeader('Idempotency-Key', 'cb-cancel-2')->postJson("/v1/services/{$other->id}/chargeback/cancel")->assertStatus(409)->assertJsonPath('error', 'chargeback_not_approved');
    $this->flushHeaders();
    app(OutboxPublisher::class)->relayPending();
    expect(Notification::query()->where('organization_id', $org->id)->where('title', 'Vrácení kreditu za mc-liga jsme nemohli schválit')->value('body'))->toContain('ke konci období');
});
