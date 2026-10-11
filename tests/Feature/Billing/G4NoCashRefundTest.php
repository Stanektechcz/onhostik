<?php

declare(strict_types=1);

use Database\Seeders\ContentSeeder;
use Database\Seeders\LegalEntitySeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Onhost\Domain\Notifications\Webhooks\WebhookEvents;
use Onhost\Domain\Payments\Commands\PaymentRefundCommand;
use Onhost\Domain\Payments\Models\PaymentIntent;
use Onhost\Domain\Payments\Models\PaymentRefund;
use Onhost\Domain\Payments\PaymentService;
use Onhost\Domain\WalletLedger\LedgerService;
use Onhost\Domain\WalletLedger\Models\LedgerTransaction;
use Onhost\Domain\WalletLedger\Models\WalletRefund;
use Onhost\Domain\WalletLedger\RefundableCredit;
use Onhost\Domain\WalletLedger\WalletService;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Money\Currency;
use Onhost\Platform\Money\Money;
use Onhost\Platform\Outbox\OutboxMessage;

/*
 * G4 — owner decision G-R4 (2026-10-05): credit is never paid out in cash. Nothing in the code base turns the balance of the
 * account credit into money on a card or a bank account: there is no wallet refund, no "refundable balance" that a screen,
 * the API, a staff route or a console command could offer, and no webhook announcing a payout that can no longer happen.
 *
 * The one statutory exception is not a payout of credit: a consumer who withdraws within fourteen days and does not agree to
 * the refund to the credit gets the PAYMENT for the withdrawn contract back the way it was paid (§ 1831 OZ). That is a refund
 * of an order's payment through PaymentService, never of a top-up — see ROZHODNUTI.md G-R4 and the open owner question there.
 *
 * G-R3 stays: the spend order of the credit is purchased credit first (`RefundableCredit::replay`, unchanged), pinned in
 * RefundableBalanceTest.
 */

/** Source files (relative paths) under the given roots that contain every one of the needles. */
function g4FilesContaining(array $roots, array $needles): array
{
    $hits = [];
    foreach ($roots as $root) {
        $dir = base_path($root);
        $files = is_file($dir) ? [new SplFileInfo($dir)] : iterator_to_array(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)));
        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $source = (string) file_get_contents($file->getPathname());
            if (array_filter($needles, fn (string $n) => ! str_contains($source, $n)) === []) {
                $hits[] = str_replace('\\', '/', substr($file->getPathname(), strlen(base_path()) + 1));
            }
        }
    }
    sort($hits);

    return $hits;
}

/**
 * Every call of a `refund(` on something other than `$this` (a private step of the same class) in the application code, the
 * provider adapters and PaymentService's own call of its provider excepted — i.e. who could reach PaymentService::refund.
 *
 * @return list<string> file:line
 */
function g4RefundCallers(): array
{
    $hits = [];
    foreach (['app', 'routes', 'domains', 'platform'] as $root) {
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(base_path($root), FilesystemIterator::SKIP_DOTS)) as $file) {
            $path = str_replace(DIRECTORY_SEPARATOR, '/', substr($file->getPathname(), strlen(base_path()) + 1));
            if ($file->getExtension() !== 'php' || $path === 'domains/Payments/PaymentService.php') {
                continue;
            }
            foreach (preg_split('/\R/', (string) file_get_contents($file->getPathname())) ?: [] as $i => $line) {
                if (preg_match('/(?<!\$this)->refund\(|::refund\(/', $line) === 1) {
                    $hits[] = $path.':'.($i + 1);
                }
            }
        }
    }

    return $hits;
}
it('has no way in the wallet to pay credit out: no refund, no refundable balance, no ledger refund posting', function () {
    expect(method_exists(WalletService::class, 'refund'))->toBeFalse()
        ->and(method_exists(WalletService::class, 'refundableBalance'))->toBeFalse();
    // a payout of credit would be a ledger `refund` debiting the main wallet; nothing in the domains posts one any more
    expect(g4FilesContaining(['domains', 'app', 'platform'], ["->post('refund'"]))->toBe([])
        ->and(g4FilesContaining(['domains', 'app', 'platform'], ['WalletRefund::query()->create']))->toBe([]);
});

it('offers no API, staff or console path that pays credit out or names a refundable balance', function () {
    $routes = collect(Route::getRoutes()->getRoutes())->map(fn ($r) => $r->methods()[0].' '.$r->uri())->values();
    expect($routes->filter(fn (string $r) => preg_match('#(wallet|credit)\S*/(refund|payout|cash)|refundable#i', $r) === 1)->all())->toBe([]);
    $commands = array_keys(Artisan::all());
    expect(array_values(array_filter($commands, fn (string $c) => preg_match('#(wallet|credit).*(refund|payout)|(refund|payout).*(wallet|credit)#i', $c) === 1)))->toBe([]);

    // the only refund of money to a card is PaymentService::refund, and its only caller is G6's refund of a withdrawn ORDER payment
    // (OrderPaymentRefunds, behind the staff step-up of PaymentRefundCommand), which refuses a top-up before anything else
    $callers = array_values(array_unique(array_map(fn (string $hit) => explode(':', $hit)[0], g4RefundCallers())));
    expect($callers)->toBe(['domains/Payments/OrderPaymentRefunds.php']);
    $guarded = (string) file_get_contents(base_path('domains/Payments/OrderPaymentRefunds.php'));
    expect($guarded)->toContain("'topup_not_refundable'")->toContain("\$intent->purpose !== 'order'")
        ->and((new PaymentRefundCommand('g4', ['op' => 'refund.withdrawal', 'amount_minor' => 1, 'currency' => 'CZK']))->requiresStepUp())->toBeTrue();
    // and no presenter or controller says "refundable" to anybody
    expect(g4FilesContaining(['app/Http'], ['refundable']))->toBe([])
        ->and(g4FilesContaining(['app/Http'], ['refundableBalance']))->toBe([]);
});

it('shows the customer their credit without any refundable amount, and staff see none either', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $wallets = app(WalletService::class);
    $wallets->topup($org, Money::decimal('1000', 'CZK'), 'card', 'g4-top', $this->contextFor($owner, $org), 'pi_g4', bankProvider: 'comgate');
    $this->actingAs($owner, 'sanctum');

    $wallet = $this->getJson('/v1/wallet')->assertOk()->json('data');
    expect($wallet['balances']['available']['minor'])->toBe(100000)
        ->and(json_encode($wallet))->not->toContain('refundable');
    $transactions = $this->getJson('/v1/wallet/transactions')->assertOk()->getContent();
    expect($transactions)->not->toContain('refundable');
});

it('no longer offers a webhook for a credit payout that cannot happen', function () {
    expect(WebhookEvents::catalog())->not->toContain('wallet.refund.requested')
        ->and(WebhookEvents::fields('wallet.refund.requested'))->toBeNull();
    // a new subscription naming it is told so (docs/api/CHANGELOG.md); the wallet family still subscribes as before
    expect(fn () => WebhookEvents::normalize(['wallet.refund.requested']))->toThrow(DomainError::class, 'Not an event a webhook can carry')
        ->and(WebhookEvents::normalize(['wallet.topup.completed']))->toBe(['wallet.topup.completed']);
});

it('spends credit and returns corrections to the credit, and none of it ever leaves as money', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $wallets = app(WalletService::class);
    $ctx = $this->contextFor($owner, $org);
    $wallets->topup($org, Money::decimal('1000', 'CZK'), 'card', 'g4-flow-top', $ctx, 'pi_g4_flow', bankProvider: 'comgate');
    $wallets->charge($org, Money::decimal('800', 'CZK'), 'web', 'g4-flow-spend', $ctx, 'subscription', 'g4-flow-spend', Money::zero('CZK'));
    $gross = Money::decimal('800', 'CZK');
    $returned = $wallets->returnToCredit($org, $gross, WalletService::revenueReturn($gross, Money::zero('CZK')), 'g4-flow-return', $ctx, 'service', 'g4-flow');

    // the return is credit to spend, marked as never payable, and the purchased-first order (G-R3) keeps only 200 of the 1000 bought
    expect($returned->source)->toBe('return')->and((bool) $returned->refundable)->toBeFalse()
        ->and($wallets->balances($org, 'CZK')['available']->minor)->toBe(100000)
        ->and(RefundableCredit::of($org->id, Currency::CZK))->toBe(20000);
    // nothing anywhere recorded money leaving the credit
    expect(WalletRefund::query()->where('organization_id', $org->id)->count())->toBe(0)
        ->and(PaymentRefund::query()->count())->toBe(0)
        ->and(LedgerTransaction::query()->where('organization_id', $org->id)->where('kind', 'refund')->count())->toBe(0)
        ->and(OutboxMessage::query()->whereIn('name', ['wallet.refund.requested', 'payment.refunded'])->count())->toBe(0)
        ->and(app(LedgerService::class)->verifyInvariant()['balanced'])->toBeTrue();
});

it('tells customers in the terms and the knowledge base that credit is not refunded in cash', function () {
    $this->seed([ContentSeeder::class, LegalEntitySeeder::class]);
    $terms = (string) file_get_contents(resource_path('legal/terms.md'));
    expect($terms)->toContain('Kredit nelze vrátit v hotovosti ani vyplatit na bankovní účet či platební kartu')
        ->not->toContain('Nevyčerpaný kredit se vrací pouze v případech stanovených zákonem nebo výslovnou dohodou');

    // the knowledge-base article "Firemní faktury, DPH a kredit", as the public API serves it, in both languages
    $section = function (string $query, string $heading): string {
        $body = $this->getJson('/v1/kb/faktury-dph'.$query)->assertOk()->json('data.body');

        return (string) (collect($body)->first(fn ($pair) => is_array($pair) && ($pair[0] ?? null) === $heading)[1] ?? '');
    };
    expect($section('', 'Kredit'))->toContain('Kredit v hotovosti nevracíme')->not->toContain('vracíme ho na požádání')
        ->and($section('?locale=en', 'Credit'))->toContain('Credit is not refunded in cash')->not->toContain('refundable on request');

    // the published terms themselves
    $this->get('/dokumenty/vop')->assertOk()->assertSee('Kredit nelze vrátit v hotovosti', false);
});

it('refuses to give a credit top-up back to the card: that would be credit paid out in cash', function () {
    Http::preventStrayRequests();
    [, $org] = $this->customerWithOrganization();
    $intent = PaymentIntent::query()->create(['organization_id' => $org->id, 'provider' => 'comgate', 'provider_id' => 'g4-topup-'.uniqid(), 'purpose' => 'topup', 'reference_type' => 'wallet',
        'reference_id' => $org->id, 'amount_minor' => 100000, 'currency' => 'CZK', 'state' => 'SUCCEEDED', 'idempotency_key' => 'g4-pi-'.uniqid(), 'paid_at' => now()]);

    expect(fn () => app(PaymentService::class)->refund($intent, Money::minor(100000, 'CZK'), 'customer request', 'g4-topup-refund', CommandContext::system('test')->withScope($org->id)))
        ->toThrow(DomainError::class, 'A top-up became credit');
    try {
        app(PaymentService::class)->refund($intent, Money::minor(1, 'CZK'), 'customer request', 'g4-topup-refund-2', CommandContext::system('test')->withScope($org->id));
    } catch (DomainError $e) {
        expect($e->error)->toBe('topup_not_refundable')->and($e->status)->toBe(422);
    }
    expect(PaymentRefund::query()->count())->toBe(0)->and((int) $intent->fresh()->refunded_minor)->toBe(0);
});
