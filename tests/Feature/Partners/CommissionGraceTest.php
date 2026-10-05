<?php

declare(strict_types=1);

use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Invoicing\InvoiceService;
use Onhost\Domain\Invoicing\Models\Invoice;
use Onhost\Domain\Notifications\Models\Notification;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Partners\Commands\PartnerCommand;
use Onhost\Domain\Partners\CommissionGrace;
use Onhost\Domain\Partners\Models\Partner;
use Onhost\Domain\Partners\Models\PartnerCommission;
use Onhost\Domain\Partners\Models\PartnerPayout;
use Onhost\Domain\Partners\PartnerService;
use Onhost\Platform\Commands\CommandBus;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Money\Money;
use Onhost\Platform\Outbox\OutboxMessage;
use Onhost\Platform\Outbox\OutboxPublisher;

/*
 * Owner decision R7 (docs/audit/2026-10-full-readiness/ROZHODNUTI.md): a partner commission becomes payable only 30 days
 * after the client paid the invoice, and one commission per (invoice, kind) is kept by a unique index. Before, a commission
 * was payable the moment the invoice was paid: a client could pay, have the partner paid out the same day, and get the money
 * back by a credit note, a withdrawal or a chargeback refund afterwards — ONhost paid a commission on money it returned.
 *
 *  - inside the window a credit note (refund, withdrawal, chargeback refund — all of them write one) cancels the commission,
 *    or reduces it in proportion; once matured the commission is locked and a later credit note is a negative adjustment
 *    against the next payouts;
 *  - only a matured commission is in a payout, and the maturing job flips each commission once however often it runs.
 */

beforeEach(function () {
    $this->seed([TaxRuleSeeder::class, LegalEntitySeeder::class]);
    Http::preventStrayRequests();
    config(['onhost.vies.enabled' => false]);
});

/** @return array{0: Partner, 1: Organization, 2: Organization} an active share partner (15 %), its attributed client, the partner org */
function cgrPartnerWithClient(object $test, string $partnerName, string $clientName): array
{
    $organizationOf = fn (string $name): Organization => (fn () => $this->customerWithOrganization([], ['name' => $name])[1])->call($test);
    $partnerOrg = $organizationOf($partnerName);
    User::query()->whereKey($partnerOrg->owner_user_id)->update(['email_verified_at' => now()]); // R5: payouts need a verified owner
    $client = $organizationOf($clientName);
    $partners = app(PartnerService::class);
    $partner = $partners->approve($partners->apply($partnerOrg, ['model' => 'share'], CommandContext::system('test')), CommandContext::system('test'));
    $partners->attribute($client, $partner->code, CommandContext::system('test'));

    return [$partner->fresh(), $client->fresh(), $partnerOrg];
}

/** Issue and pay a client invoice of `$net` minor units net (21 % VAT on top), and deliver its events. */
function cgrPaidInvoice(Organization $client, int $net): Invoice
{
    $ctx = CommandContext::system('test');
    $invoices = app(InvoiceService::class);
    $tax = (int) round($net * 0.21);
    $draft = $invoices->draft($client, 'invoice', 'CZK', [[
        'sku' => 'vps-4-8', 'description' => 'VPS 4/8', 'qty' => 1, 'unit' => 'ks', 'unit_net' => $net, 'discount' => 0, 'net' => $net, 'tax_rate' => '21', 'tax_category' => 'S', 'tax' => $tax, 'total' => $net + $tax,
    ]], $ctx, null, ['postpaid' => true]);
    $invoice = $invoices->issue($draft, $ctx);
    $invoices->markPaid($invoice, $invoice->total(), 'bank', $ctx);
    app(OutboxPublisher::class)->relayPending();

    return $invoice->fresh();
}

/** The maturing job as the scheduler runs it: through the bus, as the system. @return array<string,mixed> */
function cgrMature(string $key): array
{
    return (array) app(CommandBus::class)->dispatch(new PartnerCommand('partner.commissions.mature:'.$key, ['op' => 'commissions.mature']), CommandContext::system('cli:partners:mature-commissions'));
}

function cgrRequestPayout(Partner $partner, string $amount): PartnerPayout
{
    return app(PartnerService::class)->requestPayout($partner->fresh(), Money::decimal($amount, 'CZK'), null, CommandContext::system('test')->withScope($partner->organization_id), 'offset');
}

/** The error slug a refused call answered with, or null when it went through. */
function cgrRefusal(Closure $call): ?string
{
    try {
        $call();
    } catch (DomainError $e) {
        return $e->error;
    }

    return null;
}

function cgrEvents(string $name): int
{
    return OutboxMessage::query()->where('name', $name)->count();
}

it('keeps a commission out of every payout until thirty days after the client paid (R7)', function () {
    [$partner, $client] = cgrPartnerWithClient($this, 'Agentura Lhůta s.r.o.', 'Klient Lhůta s.r.o.');
    $invoice = cgrPaidInvoice($client, 1000000);
    $commission = PartnerCommission::query()->where('invoice_id', $invoice->id)->firstOrFail();
    $partners = app(PartnerService::class);

    expect($commission->state)->toBe('pending')->and($commission->amount_minor)->toBe(150000)
        ->and($commission->payable_at?->toIso8601String())->toBe($invoice->paid_at->copy()->addDays(30)->toIso8601String());
    expect($partners->balance($partner)['payable']->minor)->toBe(0)->and($partners->balance($partner)['pending']->minor)->toBe(150000);
    expect(cgrRefusal(fn () => cgrRequestPayout($partner, '1500')))->toBe('payout_exceeds_balance');

    $this->travelTo($invoice->paid_at->copy()->addDays(30)->subMinute());
    expect(cgrMature('day-29')['matured'])->toBe(0);
    expect($commission->fresh()->state)->toBe('pending');

    $this->travelTo($invoice->paid_at->copy()->addDays(30));
    expect(cgrMature('day-30')['matured'])->toBe(1);
    expect($commission->fresh()->state)->toBe('payable')->and($partners->balance($partner)['payable']->minor)->toBe(150000);
    expect(cgrEvents('partner.commissions.matured'))->toBe(1)
        ->and(Notification::query()->where('organization_id', $partner->organization_id)->where('event', 'partner.commissions.matured')->exists())->toBeTrue();

    $payout = cgrRequestPayout($partner, '1500');
    expect($payout->amount_minor)->toBe(150000)->and($commission->fresh()->state)->toBe('allocated')->and($commission->fresh()->payout_id)->toBe($payout->id);
});

it('cancels the commission when the client gets the whole invoice back inside the window, once however often it is told', function () {
    [$partner, $client] = cgrPartnerWithClient($this, 'Agentura Vratka s.r.o.', 'Klient Vratka s.r.o.');
    $invoice = cgrPaidInvoice($client, 1000000);
    $note = app(InvoiceService::class)->creditNote($invoice, 'Vrácení peněz klientovi', CommandContext::system('test'));
    app(OutboxPublisher::class)->relayPending();
    app(OutboxPublisher::class)->relayPending(); // the cancellation's own event, published while the credit note was relayed

    $commission = PartnerCommission::query()->where('invoice_id', $invoice->id)->firstOrFail();
    $reversal = PartnerCommission::query()->where('invoice_id', $note->id)->where('kind', 'reversal')->firstOrFail();
    expect($commission->state)->toBe('cancelled')->and($reversal->state)->toBe('cancelled')->and($reversal->amount_minor)->toBe(-150000)
        ->and(cgrEvents('partner.commission.cancelled'))->toBe(1)
        ->and(Notification::query()->where('organization_id', $partner->organization_id)->where('event', 'partner.commission.cancelled')->exists())->toBeTrue();

    // the credit note delivered again (a retried outbox message) changes nothing
    expect(app(PartnerService::class)->reverseForCreditNote($note->fresh()))->toBeNull();
    expect(PartnerCommission::query()->where('partner_id', $partner->id)->count())->toBe(2);

    $this->travel(31)->days();
    expect(cgrMature('after-cancel')['matured'])->toBe(0);
    expect(app(PartnerService::class)->balance($partner)['payable']->minor)->toBe(0)
        ->and(cgrRefusal(fn () => cgrRequestPayout($partner, '1000')))->toBe('payout_exceeds_balance');
});

it('reduces the commission in proportion when a chargeback refund gives part of the invoice back inside the window, and cancels it with the rest', function () {
    [$partner, $client] = cgrPartnerWithClient($this, 'Agentura Půlka s.r.o.', 'Klient Půlka s.r.o.');
    $invoice = cgrPaidInvoice($client, 1000000);
    $line = $invoice->lines()->firstOrFail();
    $invoices = app(InvoiceService::class);
    $commission = PartnerCommission::query()->where('invoice_id', $invoice->id)->firstOrFail();

    // the cancellation refund of the unused half (ChargebackService::settle gives it back exactly like this)
    $half = $invoices->giveBack($invoice, [$line->id => 605000], 'Zrušení služby — vráceno 50 % nevyužitého období', CommandContext::system('test'))['credit_note'];
    app(OutboxPublisher::class)->relayPending();
    $reduction = PartnerCommission::query()->where('invoice_id', $half->id)->where('kind', 'reversal')->firstOrFail();
    expect($reduction->amount_minor)->toBe(-75000)->and($reduction->state)->toBe('pending')
        ->and($reduction->payable_at?->toIso8601String())->toBe($commission->payable_at->toIso8601String())
        ->and($commission->fresh()->state)->toBe('pending')
        ->and(app(PartnerService::class)->balance($partner)['pending']->minor)->toBe(75000)
        ->and(cgrEvents('partner.commission.reduced'))->toBe(1);
    expect(app(PartnerService::class)->reverseForCreditNote($half->fresh()))->toBeNull(); // idempotent

    // the rest comes back too: nothing of the commission is left
    $rest = $invoices->giveBack($invoice->fresh(), null, 'Odstoupení od smlouvy — zbytek', CommandContext::system('test'))['credit_note'];
    app(OutboxPublisher::class)->relayPending();
    $last = PartnerCommission::query()->where('invoice_id', $rest->id)->where('kind', 'reversal')->firstOrFail();
    expect($last->amount_minor)->toBe(-75000)->and($last->state)->toBe('cancelled')
        ->and($commission->fresh()->state)->toBe('cancelled')->and($reduction->fresh()->state)->toBe('cancelled')
        ->and((int) PartnerCommission::query()->where('partner_id', $partner->id)->sum('amount_minor'))->toBe(0);

    $this->travel(31)->days();
    expect(cgrMature('after-rest')['matured'])->toBe(0)->and(app(PartnerService::class)->balance($partner)['payable']->minor)->toBe(0);
});

it('pays a matured commission once, and turns a later credit note into a negative adjustment against the next payout', function () {
    [$partner, $client] = cgrPartnerWithClient($this, 'Agentura Zámek s.r.o.', 'Klient Zámek s.r.o.');
    $partners = app(PartnerService::class);
    $invoice = cgrPaidInvoice($client, 1000000);
    $this->travel(30)->days();
    cgrMature('lock-1');
    $payout = cgrRequestPayout($partner, '1500');
    $partners->approvePayout($payout, CommandContext::system('test'));
    $partners->markPayoutPaid($payout, 'BANK-R7-1', CommandContext::system('test'));
    expect(cgrRefusal(fn () => $partners->markPayoutPaid($payout->fresh(), 'BANK-R7-2', CommandContext::system('test'))))->toBe('payout_not_approved');
    $commission = PartnerCommission::query()->where('invoice_id', $invoice->id)->firstOrFail();
    expect($commission->state)->toBe('paid');

    // the client gets its money back after the window: the paid commission is not touched, the next payouts carry the minus
    $note = app(InvoiceService::class)->creditNote($invoice, 'Reklamace po lhůtě', CommandContext::system('test'));
    app(OutboxPublisher::class)->relayPending();
    $adjustment = PartnerCommission::query()->where('invoice_id', $note->id)->where('kind', 'reversal')->firstOrFail();
    expect($commission->fresh()->state)->toBe('paid')->and($adjustment->state)->toBe('payable')->and($adjustment->amount_minor)->toBe(-150000)
        ->and($partners->balance($partner)['payable']->minor)->toBe(-150000)
        ->and(cgrEvents('partner.commission.adjusted'))->toBe(1);

    // the next commission first pays the adjustment off
    cgrPaidInvoice($client, 1000000);
    $this->travel(30)->days();
    cgrMature('lock-2');
    expect($partners->balance($partner)['payable']->minor)->toBe(0)
        ->and(cgrRefusal(fn () => cgrRequestPayout($partner, '1500')))->toBe('payout_exceeds_balance')
        ->and(PartnerPayout::query()->where('partner_id', $partner->id)->where('state', 'paid')->count())->toBe(1);
});

it('matures each commission once when two maturing runs overlap, and a retried run changes nothing', function () {
    [$partnerA, $clientA] = cgrPartnerWithClient($this, 'Agentura Souběh A s.r.o.', 'Klient Souběh A s.r.o.');
    [$partnerB, $clientB] = cgrPartnerWithClient($this, 'Agentura Souběh B s.r.o.', 'Klient Souběh B s.r.o.');
    cgrPaidInvoice($clientA, 1000000);
    cgrPaidInvoice($clientA, 200000);
    cgrPaidInvoice($clientB, 400000);
    $this->travel(31)->days();

    // the second run arrives the moment the first reads what is due (the overlapping scheduler on another server)
    $state = ['fired' => false, 'second' => null];
    DB::listen(function (QueryExecuted $query) use (&$state): void {
        $sql = strtolower($query->sql);
        if ($state['fired'] || ! str_contains($sql, 'from "partner_commissions"') || ! str_contains($sql, '"payable_at" <=')) {
            return;
        }
        $state['fired'] = true;
        $state['second'] = cgrMature('run-b');
    });
    $first = cgrMature('run-a');

    expect($state['fired'])->toBeTrue()->and($first['matured'] + $state['second']['matured'])->toBe(3)
        ->and(PartnerCommission::query()->where('state', 'payable')->count())->toBe(3)
        ->and(cgrEvents('partner.commissions.matured'))->toBe(2); // one per partner, from whichever run flipped its rows
    $amounts = OutboxMessage::query()->where('name', 'partner.commissions.matured')->get()->mapWithKeys(fn (OutboxMessage $m) => [$m->organization_id => (int) data_get($m->payload, 'amount.minor')]);
    expect($amounts->all())->toEqualCanonicalizing([$partnerA->organization_id => 180000, $partnerB->organization_id => 60000]);

    expect(cgrMature('run-a'))->toBe($first); // the same run retried is answered from the bus's memory
    expect(cgrMature('run-c')['matured'])->toBe(0)->and(cgrEvents('partner.commissions.matured'))->toBe(2);
});

it('keeps one commission per invoice and kind in the database, and still lets a payout split one', function () {
    [$partner, $client] = cgrPartnerWithClient($this, 'Agentura Index s.r.o.', 'Klient Index s.r.o.');
    $invoice = cgrPaidInvoice($client, 1000000);
    $partners = app(PartnerService::class);
    expect($partners->accrueForInvoice($invoice))->toBeNull(); // the paid event delivered twice
    $commission = PartnerCommission::query()->where('invoice_id', $invoice->id)->firstOrFail();

    expect(fn () => DB::transaction(fn () => PartnerCommission::query()->create(array_merge($commission->only(['partner_id', 'organization_id', 'invoice_id', 'period', 'kind', 'base_minor', 'rate_pct', 'amount_minor', 'currency', 'invoice_paid_at']), ['state' => 'payable']))))
        ->toThrow(UniqueConstraintViolationException::class);

    cgrPaidInvoice($client, 1000000);
    $this->travel(30)->days();
    cgrMature('index');
    cgrRequestPayout($partner, '2000'); // 1 500 + 500 of the second: the second is split
    $second = PartnerCommission::query()->where('partner_id', $partner->id)->where('invoice_id', '!=', $invoice->id)->orderBy('created_at')->get();
    expect($second)->toHaveCount(2)->and($second->pluck('state')->sort()->values()->all())->toBe(['allocated', 'payable'])
        ->and((int) $second->sum('amount_minor'))->toBe(150000);
});

it('touches only the partner whose client got the money back, and shows each partner only its own commissions', function () {
    [$partnerA, $clientA, $orgA] = cgrPartnerWithClient($this, 'Agentura Alfa s.r.o.', 'Klient Alfa s.r.o.');
    [$partnerB, $clientB, $orgB] = cgrPartnerWithClient($this, 'Agentura Beta s.r.o.', 'Klient Beta s.r.o.');
    $invoiceA = cgrPaidInvoice($clientA, 1000000);
    $invoiceB = cgrPaidInvoice($clientB, 1000000);

    app(InvoiceService::class)->creditNote($invoiceB, 'Vrácení peněz klientovi Beta', CommandContext::system('test'));
    app(OutboxPublisher::class)->relayPending();
    // a credit note of another organization naming A's invoice never reaches A's commission
    $forged = (new Invoice)->forceFill(['id' => 'inv_forged_r7', 'type' => 'credit_note', 'organization_id' => $clientB->id, 'corrects_invoice_id' => $invoiceA->id, 'subtotal_minor' => -1000000, 'discount_minor' => 0, 'currency' => 'CZK', 'number' => 'DB-FORGED']);
    expect(app(PartnerService::class)->reverseForCreditNote($forged))->toBeNull();
    expect(DB::table('audit_events')->where('action', 'partner.commission.reverse')->where('result', 'denied')->where('resource_id', PartnerCommission::query()->where('invoice_id', $invoiceA->id)->value('id'))->exists())->toBeTrue();

    expect(PartnerCommission::query()->where('invoice_id', $invoiceA->id)->value('state'))->toBe('pending')
        ->and(PartnerCommission::query()->where('invoice_id', $invoiceB->id)->value('state'))->toBe('cancelled')
        ->and(PartnerCommission::query()->where('partner_id', $partnerA->id)->count())->toBe(1);

    $this->travel(31)->days();
    expect(cgrMature('isolation')['matured'])->toBe(1);

    $ownerA = $orgA->owner_user_id;
    $this->actingAs(User::query()->findOrFail($ownerA), 'sanctum');
    $a = $this->withHeader('X-Organization', $orgA->id)->getJson('/v1/partner/commissions')->assertOk();
    expect($a->json('data.balance.payable.minor'))->toBe(150000)
        ->and(collect($a->json('data.months'))->flatMap(fn ($m) => collect($m['lines'])->pluck('invoice_id'))->all())->toBe([$invoiceA->id]);
    $this->actingAs(User::query()->findOrFail($orgB->owner_user_id), 'sanctum');
    $b = $this->withHeader('X-Organization', $orgB->id)->getJson('/v1/partner/commissions')->assertOk();
    expect($b->json('data.balance.payable.minor'))->toBe(0)
        ->and(collect($b->json('data.months'))->flatMap(fn ($m) => collect($m['lines'])->pluck('invoice_id'))->contains($invoiceA->id))->toBeFalse();
    expect(cgrRefusal(fn () => cgrRequestPayout($partnerB, '1000')))->toBe('payout_exceeds_balance')
        ->and(cgrRefusal(fn () => cgrRequestPayout($partnerA, '3000')))->toBe('payout_exceeds_balance');
});

it('keeps the one-off bonus for the first paid invoice that stays paid, when the first one came back inside the window', function () {
    [$partner, $client] = cgrPartnerWithClient($this, 'Agentura Bonus s.r.o.', 'Klient Bonus s.r.o.');
    $partner->forceFill(['model' => 'oneoff'])->save();
    $refunded = cgrPaidInvoice($client, 100000);
    app(InvoiceService::class)->creditNote($refunded, 'Vrácení peněz klientovi', CommandContext::system('test'));
    app(OutboxPublisher::class)->relayPending();
    expect(PartnerCommission::query()->where('invoice_id', $refunded->id)->value('state'))->toBe('cancelled');

    $kept = cgrPaidInvoice($client, 100000);
    $bonus = PartnerCommission::query()->where('invoice_id', $kept->id)->firstOrFail();
    expect($bonus->kind)->toBe('oneoff')->and($bonus->state)->toBe('pending');
});

it('matures a commission together with its pending reversal, also when the batch would part them', function () {
    [$partner, $client] = cgrPartnerWithClient($this, 'Agentura Dávka s.r.o.', 'Klient Dávka s.r.o.');
    $first = cgrPaidInvoice($client, 1000000);
    $second = cgrPaidInvoice($client, 1000000);
    foreach ([$first, $second] as $invoice) {
        $line = $invoice->lines()->firstOrFail();
        app(InvoiceService::class)->giveBack($invoice, [$line->id => 605000], 'Zrušení služby — vráceno 50 % nevyužitého období', CommandContext::system('test'));
    }
    app(OutboxPublisher::class)->relayPending();
    $this->travel(31)->days();

    $run = app(CommissionGrace::class)->mature(CommandContext::system('test'), null, 1); // a batch of one group
    expect($run['matured'])->toBe(2)
        ->and(app(PartnerService::class)->balance($partner)['payable']->minor)->toBe(75000) // never the commission without its minus
        ->and(app(PartnerService::class)->balance($partner)['pending']->minor)->toBe(75000);
    expect(app(CommissionGrace::class)->mature(CommandContext::system('test'))['matured'])->toBe(2);
});

it('leaves a commission of a partner that no longer exists pending, and says so', function () {
    [, $client] = cgrPartnerWithClient($this, 'Agentura Sirotek s.r.o.', 'Klient Sirotek s.r.o.');
    $invoice = cgrPaidInvoice($client, 1000000);
    PartnerCommission::query()->where('invoice_id', $invoice->id)->update(['partner_id' => 'ptn_gone_r7']);
    $this->travel(31)->days();
    Log::spy();

    expect(cgrMature('orphan')['matured'])->toBe(0)->and(PartnerCommission::query()->where('invoice_id', $invoice->id)->value('state'))->toBe('pending');
    Log::shouldHaveReceived('warning')->withArgs(fn (string $message) => str_contains($message, 'partner'))->atLeast()->once();
});

it('marks only payout remainders as fragments when the index arrives, and builds no index over a genuine double accrual', function () {
    $migration = require database_path('migrations/0001_01_01_000950_partner_commissions_wait_out_their_grace.php');
    $migration->down();
    $row = fn (string $id, string $invoice, int $amount, string $state, ?string $payout, int $minutes) => [
        'id' => $id, 'partner_id' => 'ptn_mig', 'organization_id' => 'org_mig', 'invoice_id' => $invoice, 'period' => '2026-09', 'kind' => 'share', 'base_minor' => 1000000, 'rate_pct' => 20,
        'amount_minor' => $amount, 'currency' => 'CZK', 'state' => $state, 'payout_id' => $payout, 'invoice_paid_at' => now()->subDays(10), 'created_at' => now()->subMinutes($minutes), 'updated_at' => now(),
    ];
    // a commission a payout split (150 000 allocated, the remainder 50 000 left payable) and a genuine double accrual
    DB::table('partner_commissions')->insert([$row('pcm_split_a', 'inv_split', 150000, 'allocated', 'po_mig', 10), $row('pcm_split_b', 'inv_split', 50000, 'payable', null, 5)]);
    DB::table('partner_commissions')->insert([$row('pcm_dbl_a', 'inv_dbl', 200000, 'payable', null, 10), $row('pcm_dbl_b', 'inv_dbl', 200000, 'payable', null, 5)]);

    expect(fn () => $migration->up())->toThrow(RuntimeException::class, 'inv_dbl/share');
    expect(Schema::hasColumn('partner_commissions', 'fragment'))->toBeFalse(); // refused before anything changed

    DB::table('partner_commissions')->where('id', 'pcm_dbl_b')->delete(); // finance resolved the double accrual
    $migration->up();
    expect(DB::table('partner_commissions')->where('fragment', true)->pluck('id')->all())->toBe(['pcm_split_b'])
        ->and(DB::table('partner_commissions')->where('id', 'pcm_split_b')->value('state'))->toBe('payable') // paid 10 days ago and still payable: R7 applies from the deploy on
        ->and(DB::table('partner_commissions')->where('id', 'pcm_dbl_a')->value('payable_at'))->not->toBeNull();
});
