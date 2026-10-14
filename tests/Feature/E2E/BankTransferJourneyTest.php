<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification as LaravelNotification;
use Illuminate\Testing\TestResponse;
use Onhost\Domain\Billing\Models\DunningCase;
use Onhost\Domain\Billing\Models\Subscription;
use Onhost\Domain\Invoicing\Models\Invoice;
use Onhost\Domain\Orders\Models\Order;
use Onhost\Domain\Orders\OrderStateMachine;
use Onhost\Domain\Payments\Models\BankStatementLine;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Domain\WalletLedger\LedgerService;
use Onhost\Platform\Outbox\OutboxPublisher;

require_once __DIR__.'/../../Support/E2E/E2EHelpers.php';

/*
 * P15a (phase R) — a web hosting paid by bank transfer, from the order to the resumed site, the way Česká spořitelna's statement
 * files reach the platform (camt.053 XML and ABO/GPC, J-R7 option B: finance uploads the file in the staff console).
 *
 * sign-up → cart → order "by transfer" → proforma with a variable symbol → finance imports the statement → the line is matched
 * by VS and amount, the order is paid and settled → the saga provisions the site on a stateful ISPConfig → ACTIVE → the documents
 * of the sale → the same file again changes nothing → no credit when the renewal falls due → reminders → suspension of the site,
 * its cron job and its FTP account → the customer asks for a top-up by transfer → finance imports the next statement (the other
 * format) → the period is renewed from the credit and everything comes back → the customer cancels at the end of the period (the
 * end itself, archive and purge, is CancellationFlowTest's E10; the card is BillingDunningFlowTest's E6). Each journey runs once
 * with camt.053 first and once with GPC first.
 *
 * Only the edges are doubles (the hosting panel; statement files built from the published formats, synthetic numbers). The
 * clock goes first: seeded prices are dated now()->subDay() (TASK-0192). Asserts do not depend on row order.
 */

const BTJ_IBAN = 'CZ6508000000002000145399';

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-10-12 12:00:00', 'UTC'));
    e2eSeedPlatform();
    e2eWebInfrastructure();
    config()->set('onhost.dns.parking_ipv4', '89.187.160.1');
    Http::preventStrayRequests();
});

/** One camt.053 statement with one credit entry. */
function btjCamt(string $ref, int $minor, string $vs, string $day): string
{
    $amount = number_format($minor / 100, 2, '.', '');

    return '<?xml version="1.0" encoding="UTF-8"?>'."\n"
        .'<Document xmlns="urn:iso:std:iso:20022:tech:xsd:camt.053.001.02"><BkToCstmrStmt><GrpHdr><MsgId>'.$ref.'</MsgId><CreDtTm>'.$day.'T08:00:00</CreDtTm></GrpHdr>'
        .'<Stmt><Id>'.$ref.'</Id><Acct><Id><IBAN>'.BTJ_IBAN.'</IBAN></Id></Acct>'
        ."<Ntry><Amt Ccy=\"CZK\">{$amount}</Amt><CdtDbtInd>CRDT</CdtDbtInd><Sts>BOOK</Sts><BookgDt><Dt>{$day}</Dt></BookgDt><AcctSvcrRef>{$ref}</AcctSvcrRef>"
        ."<NtryDtls><TxDtls><Refs><AcctSvcrRef>{$ref}-A</AcctSvcrRef><EndToEndId>NOTPROVIDED</EndToEndId></Refs><RltdPties><Dbtr><Nm>Skladomat s.r.o.</Nm></Dbtr></RltdPties>"
        ."<RmtInf><Strd><CdtrRefInf><Ref>{$vs}</Ref></CdtrRefInf></Strd></RmtInf></TxDtls></NtryDtls></Ntry>"
        .'</Stmt></BkToCstmrStmt></Document>';
}

/** One ABO/GPC statement with one credit line (075, 128 bytes at the positions of the format). */
function btjGpc(string $doc, int $minor, string $vs, string $day): string
{
    $date = Carbon::parse($day)->format('dmy');
    $name = substr(str_pad((string) iconv('UTF-8', 'CP1250', 'Skladomat s.r.o.'), 20), 0, 20);
    $header = str_pad('074'.'0000002000145399'.'ONHOST S.R.O.       '.$date, 128, '0');
    $line = '075'.'0000002000145399'.'0000001234567890'.str_pad($doc, 13, '0', STR_PAD_LEFT).str_pad((string) $minor, 12, '0', STR_PAD_LEFT).'2'
        .str_pad($vs, 10, '0', STR_PAD_LEFT).'0008000308'.'0000000000'.$date.$name.'0'.'0203'.$date;

    return $header."\r\n".$line."\r\n";
}

/** Finance uploads a statement file in the staff console (POST /v1/staff/payments/bank/statements). */
function btjImport(object $test, string $format, string $ref, int $minor, string $vs, string $label): TestResponse
{
    $day = now()->toDateString();
    [$content, $name] = $format === 'camt' ? [btjCamt($ref, $minor, $vs, $day), "vypis-{$ref}.xml"] : [btjGpc(preg_replace('/\D/', '', $ref) ?: '1', $minor, $vs, $day), "vypis-{$ref}.gpc"];
    $test->flushSession();
    app('auth')->forgetGuards();
    $test->actingAs(test()->steppedUpStaff('billing_operator'), 'sanctum');

    return $test->post('/v1/staff/payments/bank/statements', ['file' => UploadedFile::fake()->createWithContent($name, $content)], ['Idempotency-Key' => 'btj-'.$label, 'Accept' => 'application/json']);
}

/** The cron of one day at noon UTC: renewals, overdue and dunning, then the outbox and the operations queue. */
function btjDay(object $test, string $day): void
{
    $test->travelTo(Carbon::parse("{$day} 12:20:00", 'UTC'));
    foreach (['onhost:billing:renewals', 'onhost:billing:overdue', 'onhost:billing:dunning'] as $command) {
        expect(Artisan::call($command))->toBe(0);
    }
    btjSettle();
}

function btjLogin(object $test, string $email, string $password): void
{
    $test->flushSession();
    app('auth')->forgetGuards();
    $test->withHeaders(e2eHeaders('login-'.uniqid()))->postJson('/v1/auth/login', ['email' => $email, 'password' => $password])->assertOk();
}

function btjSettle(): void
{
    app(OutboxPublisher::class)->relayPending();
    driveOperations();
    app(OutboxPublisher::class)->relayPending();
}

it('takes a web hosting paid by bank transfer from the order to a resumed site', function (string $first, string $second) {
    LaravelNotification::fake();
    $panel = [];
    e2eIspSiteExtras($panel);
    e2eIspPanel($panel);
    $email = "prevod.{$first}@example.cz";
    [, $org, $password] = e2eSignUp($this, $email, 'Skladomat s.r.o.');

    // ── 1. the order by transfer: a proforma with a variable symbol, nothing provisioned before the money ──────────────
    $this->withHeaders(e2eHeaders('cart'))->putJson('/v1/cart', ['items' => [['product_key' => 'web-hosting', 'plan_key' => 'start', 'config' => ['fqdn' => "prevod-{$first}.cz"]]], 'commit_months' => 1, 'currency' => 'CZK'])->assertOk();
    $quote = $this->withHeaders(e2eHeaders('quote'))->postJson('/v1/cart/quote')->assertOk();
    $placed = $this->withHeaders(e2eHeaders('order'))->postJson('/v1/orders', ['quote_id' => $quote->json('data.quote_id'), 'consents' => e2eConsents(), 'payment' => ['mode' => 'bank']])->assertCreated();
    $order = Order::query()->findOrFail($placed->json('order_id'));
    $proforma = Invoice::query()->findOrFail($order->invoice_id);
    expect($order->state)->toBe(OrderStateMachine::PENDING_PAYMENT)->and($proforma->type)->toBe('proforma')->and((string) $proforma->payment_reference)->not->toBe('')
        ->and(Service::query()->where('organization_id', $org->id)->count())->toBe(0);

    // ── 2. finance imports the statement: the line is matched by VS and amount, the order is paid and provisioned ───────
    btjImport($this, $first, 'S1'.$first, (int) $order->total_minor, (string) $proforma->payment_reference, 'import-1')->assertSuccessful();
    btjSettle();
    expect($order->refresh()->state)->toBe(OrderStateMachine::ACTIVE)->and($proforma->refresh()->state)->toBe(Invoice::PAID)
        ->and(BankStatementLine::query()->where('variable_symbol', $proforma->payment_reference)->sole()->state)->not->toBe('unmatched');
    $service = Service::query()->where('organization_id', $org->id)->where('product_key', 'web-hosting')->sole();
    expect($service->state)->toBe(ServiceStateMachine::ACTIVE);

    // ── 3. the documents of the sale: the proforma is paid, the payment has its receipt, the sale its invoice ───────────
    $documents = Invoice::query()->where('organization_id', $org->id)->get();
    expect($documents->pluck('type')->sort()->values()->all())->toBe(['invoice', 'proforma', 'receipt'])
        ->and($documents->every(fn (Invoice $d) => $d->state === Invoice::PAID && (int) $d->total_minor === (int) $order->total_minor))->toBeTrue();

    // ── 4. the same statement once more (another upload, another key): no second line, no second payment ────────────────
    btjImport($this, $first, 'S1'.$first, (int) $order->total_minor, (string) $proforma->payment_reference, 'import-1-again')->assertSuccessful();
    btjSettle();
    expect(BankStatementLine::query()->where('variable_symbol', $proforma->payment_reference)->count())->toBe(1)
        ->and(Invoice::query()->where('organization_id', $org->id)->where('type', 'receipt')->count())->toBe(1);

    // ── 5. no credit when the renewal falls due: past due, reminders, suspension of the site, its cron and its FTP ────────
    $subscription = Subscription::query()->where('service_id', $service->id)->sole();
    $siteId = array_key_first($panel['sites']);
    $panel['crons'][501] = ['id' => 501, 'parent_domain_id' => $siteId, 'command' => 'php /var/www/cron.php', 'run_min' => '*/5', 'run_hour' => '*', 'run_mday' => '*', 'run_month' => '*', 'run_wday' => '*', 'active' => 'y'];
    $panel['ftps'][601] = ['ftp_user_id' => 601, 'parent_domain_id' => $siteId, 'username' => 'deploy', 'dir' => '/var/www/web', 'active' => 'y'];
    $states = [];
    for ($day = Carbon::parse('2026-11-05', 'UTC'); $day->lte(Carbon::parse('2026-12-12', 'UTC')); $day->addDay()) {
        btjDay($this, $day->toDateString());
        $states[$day->toDateString()] = [$service->refresh()->state, $subscription->refresh()->state, DunningCase::query()->where('organization_id', $org->id)->value('state')];
    }
    $panelState = function () use (&$panel, $siteId): array { // by reference: the panel double keeps changing
        return [$panel['sites'][$siteId]['active'], $panel['crons'][501]['active'], $panel['ftps'][601]['active']];
    };
    expect($states['2026-11-05'])->toBe([ServiceStateMachine::ACTIVE, Subscription::PAST_DUE, DunningCase::DUE])
        ->and($states['2026-12-11'][0])->toBe(ServiceStateMachine::ACTIVE)
        ->and($states['2026-12-12'])->toBe([ServiceStateMachine::SUSPENDED, Subscription::PAST_DUE, DunningCase::SUSPENDED])
        ->and($panelState())->toBe(['n', 'n', 'n']);
    $case = DunningCase::query()->where('organization_id', $org->id)->sole();
    expect(count((array) $case->notices_sent))->toBe(3);

    // ── 6. the customer asks for a top-up by transfer; finance imports the next statement in the other format ─────────────
    $this->travelTo(Carbon::parse('2026-12-12 14:00:00', 'UTC'));
    btjLogin($this, $email, $password);
    $init = $this->withHeaders(e2eHeaders('topup-bank'))->postJson('/v1/payments/init', ['amount' => 500, 'currency' => 'CZK', 'provider' => 'bank'])->assertCreated();
    $vs = (string) $init->json('instructions.variable_symbol');
    expect($vs)->not->toBe('')->and($service->refresh()->state)->toBe(ServiceStateMachine::SUSPENDED); // asking is not paying
    btjImport($this, $second, 'S2'.$second, 50000, $vs, 'import-2')->assertSuccessful();
    btjSettle();

    // ── 7. the credit pays the period that ran out, the suspension ends with the debt, the site and its tools are back ───
    $ledger = app(LedgerService::class);
    expect($service->refresh()->state)->toBe(ServiceStateMachine::ACTIVE)->and($panelState())->toBe(['y', 'y', 'y'])
        ->and($subscription->refresh()->state)->toBe(Subscription::ACTIVE)->and($case->refresh()->state)->toBe(DunningCase::RESOLVED)
        ->and($ledger->balance(LedgerService::walletAccount($org->id, 'CZK'), 'CZK')->minor)->toBe(50000 - (int) $order->total_minor)
        ->and($ledger->verifyInvariant()['balanced'])->toBeTrue()
        ->and(BankStatementLine::query()->where('variable_symbol', $vs)->sole()->state)->not->toBe('unmatched');

    // ── 8. the customer cancels at the end of the period: nothing stops now (the end itself is E10's) ───────────────────
    btjLogin($this, $email, $password);
    $this->withHeaders(e2eHeaders('cancel'))->postJson("/v1/subscriptions/{$subscription->id}/cancel", ['cancel' => true])->assertOk()
        ->assertJsonPath('data.cancel_at_period_end', true)->assertJsonPath('data.auto_renew', false);
    expect($service->refresh()->state)->toBe(ServiceStateMachine::ACTIVE);
})->with([
    'camt.053 first' => ['camt', 'gpc'],
    'GPC first' => ['gpc', 'camt'],
]);
