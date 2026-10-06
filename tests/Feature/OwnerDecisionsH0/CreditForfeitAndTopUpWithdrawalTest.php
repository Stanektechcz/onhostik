<?php

declare(strict_types=1);

use Onhost\Domain\Billing\Commands\WithdrawalStaffCommand;
use Onhost\Domain\Compliance\ComplianceService;
use Onhost\Domain\Compliance\Models\DataRequest;
use Onhost\Domain\Identity\StepUp\StepUpService;
use Onhost\Domain\Provisioning\AutomationLedger;
use Onhost\Domain\WalletLedger\LedgerService;
use Onhost\Domain\WalletLedger\Models\LedgerTransaction;
use Onhost\Domain\WalletLedger\WalletService;
use Onhost\Platform\Commands\CommandBus;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Money\Money;

/*
 * H0, owner decision H-R5 (2026-10-06): a credit top-up cannot be withdrawn from (it is an advance on services not yet chosen,
 * and credit is never paid out in money — G-R4), and the credit left in an account is forfeited when the account is erased.
 * The customer is told before the erasure is asked for (the preview, and the request itself names the amount until it is
 * acknowledged); the erasure books the forfeit in the ledger, so the books say where the liability went.
 */

function h0ErasureSignIn(object $test, object $owner, object $org): array
{
    app(StepUpService::class)->grant($owner, 'totp', null, '127.0.0.1');
    $test->actingAs($owner, 'sanctum')->withHeaders(['X-Organization' => $org->id]);

    return [$owner, $org];
}

it('warns in the erasure preview that the remaining credit is forfeited, and asks for it to be acknowledged', function () {
    [$owner, $org] = h0ErasureSignIn($this, ...$this->customerWithOrganization());
    app(WalletService::class)->topup($org, Money::decimal('250', 'CZK'), 'bank', 'h0-topup', CommandContext::system('test')->withScope($org->id));
    app(WalletService::class)->topup($org, Money::decimal('50', 'CZK'), 'promo', 'h0-promo', CommandContext::system('test')->withScope($org->id), promo: true);

    $preview = $this->getJson('/v1/data-requests/deletion-preview')->assertOk();
    expect($preview->json('data.deletable'))->toBeTrue()
        ->and($preview->json('data.credit.forfeited'))->toBeTrue()
        ->and($preview->json('data.credit.amounts.0.currency'))->toBe('CZK')
        ->and($preview->json('data.credit.amounts.0.purchased_minor'))->toBe(25000)
        ->and($preview->json('data.credit.amounts.0.promo_minor'))->toBe(5000)
        ->and($preview->json('data.credit.warning'))->toContain('propadne');

    // without the acknowledgement nothing is scheduled; the answer names the amount
    $this->postJson('/v1/data-requests', ['kind' => 'deletion'])->assertUnprocessable()->assertJsonPath('error', 'credit_forfeit_unacknowledged');
    expect(DataRequest::query()->where('kind', 'deletion')->exists())->toBeFalse();

    $id = $this->postJson('/v1/data-requests', ['kind' => 'deletion', 'credit_forfeit_acknowledged' => true])->assertStatus(202)->json('data.id');
    expect(DataRequest::query()->findOrFail($id)->meta['credit_forfeit_acknowledged'] ?? null)->toBeTrue();
});

it('needs no acknowledgement when nothing is left in the account', function () {
    h0ErasureSignIn($this, ...$this->customerWithOrganization());
    expect($this->getJson('/v1/data-requests/deletion-preview')->assertOk()->json('data.credit.forfeited'))->toBeFalse();
    $this->postJson('/v1/data-requests', ['kind' => 'deletion'])->assertStatus(202);
});

it('forfeits the credit with a ledger entry when the erasure runs', function () {
    [$owner, $org] = h0ErasureSignIn($this, ...$this->customerWithOrganization());
    app(WalletService::class)->topup($org, Money::decimal('250', 'CZK'), 'bank', 'h0-topup-2', CommandContext::system('test')->withScope($org->id));
    app(WalletService::class)->topup($org, Money::decimal('50', 'CZK'), 'promo', 'h0-promo-2', CommandContext::system('test')->withScope($org->id), promo: true);
    $id = $this->postJson('/v1/data-requests', ['kind' => 'deletion', 'credit_forfeit_acknowledged' => true])->assertStatus(202)->json('data.id');

    expect(app(ComplianceService::class)->processDataRequests(now()->addDays(30))['deleted'])->toBe(1);

    $ledger = app(LedgerService::class);
    expect($ledger->balance(LedgerService::walletAccount($org->id, 'CZK'), 'CZK')->minor)->toBe(0)
        ->and($ledger->balance(LedgerService::walletAccount($org->id, 'CZK', 'promo'), 'CZK')->minor)->toBe(0)
        ->and($ledger->balance(LedgerService::revenueAccount('forfeited_credit', 'CZK'), 'CZK')->minor)->toBe(25000)
        ->and(LedgerTransaction::query()->where('kind', 'credit_forfeit')->where('organization_id', $org->id)->count())->toBe(2)
        ->and($ledger->verifyInvariant()['balanced'])->toBeTrue();
    $request = DataRequest::query()->findOrFail($id);
    expect($request->state)->toBe('completed')->and($request->meta['credit_forfeited'][0]['purchased_minor'] ?? null)->toBe(25000);
    expect(app(WalletService::class)->balances($org->id, 'CZK')['available']->minor)->toBe(0);

    // running the erasure pass again books nothing twice
    app(ComplianceService::class)->processDataRequests(now()->addDays(31));
    expect(LedgerTransaction::query()->where('kind', 'credit_forfeit')->where('organization_id', $org->id)->count())->toBe(2);
});

it('refuses a withdrawal from a credit top-up, also when staff record a notice', function () {
    [$owner, $org] = $this->customerWithOrganization();
    app(AutomationLedger::class)->setEnabled('billing.withdrawal', true);
    $topup = app(WalletService::class)->topup($org, Money::decimal('500', 'CZK'), 'bank', 'h0-topup-3', CommandContext::system('test')->withScope($org->id));

    $finance = $this->staff('billing_finance_admin');
    $body = ['organization_id' => $org->id, 'topup_id' => $topup->id, 'sent_at' => now()->toIso8601String(), 'refund_to_credit_agreed' => true, 'reason' => 'Zákazník odstupuje od dobití kreditu e-mailem'];

    // the staff route says so before a second person is asked for anything
    app(StepUpService::class)->grant($finance, 'totp', null, '127.0.0.1');
    $this->actingAs($finance, 'sanctum')->postJson('/v1/staff/withdrawals', $body, ['Idempotency-Key' => 'h0-withdraw-http'])
        ->assertUnprocessable()->assertJsonPath('error', 'withdrawal_not_applicable')->assertJsonPath('why', 'credit_topup');

    // and the bus refuses it as well, a second person's approval notwithstanding
    $ctx = new CommandContext('user', $finance->id, null, null, '127.0.0.1', 'pest', 'h0-session', stepUpMethod: 'totp', staffMode: true);
    $command = fn () => new WithdrawalStaffCommand('h0-withdraw-topup', ['organization_id' => $org->id, 'topup_id' => $topup->id, 'sent_at' => (string) $body['sent_at'], 'reason' => (string) $body['reason']]);
    $approvalId = null;
    try {
        app(CommandBus::class)->dispatch($command(), $ctx);
    } catch (DomainError $e) {
        $approvalId = (string) ($e->extra['approval_id'] ?? '');
    }
    expect($approvalId)->not->toBe('');
    secondPersonApproves($approvalId);
    try {
        app(CommandBus::class)->dispatch($command(), new CommandContext('user', $finance->id, null, null, '127.0.0.1', 'pest', 'h0-session', stepUpMethod: 'totp', approvalIds: [$approvalId], staffMode: true));
        $this->fail('a withdrawal from a top-up was accepted');
    } catch (DomainError $e) {
        expect($e->error)->toBe('withdrawal_not_applicable')->and($e->extra['why'] ?? null)->toBe('credit_topup');
    }
    expect(app(WalletService::class)->balances($org->id, 'CZK')['available']->minor)->toBe(50000);
});
