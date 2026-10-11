<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification as LaravelNotification;
use Illuminate\Testing\TestResponse;
use Onhost\Domain\Billing\Models\DunningCase;
use Onhost\Domain\Billing\Models\Subscription;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Identity\StepUp\StepUpService;
use Onhost\Domain\Invoicing\CzkTaxStatement;
use Onhost\Domain\Invoicing\InvoiceService;
use Onhost\Domain\Invoicing\Models\Invoice;
use Onhost\Domain\Notifications\Models\MailOutbox;
use Onhost\Domain\Notifications\Models\Notification;
use Onhost\Domain\Orders\Models\Order;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Domain\Services\Models\Website;
use Onhost\Domain\Services\SuspensionHold;
use Onhost\Domain\WalletLedger\LedgerService;
use Onhost\Domain\WalletLedger\Models\CreditLine;
use Onhost\Platform\Outbox\OutboxMessage;
use Onhost\Platform\Outbox\OutboxPublisher;

require_once __DIR__.'/../../Support/E2E/E2EHelpers.php';

/*
 * E6 — a web hosting's money life, from the renewal invoice to the restored site, as cron would run it.
 *
 * The clock is fixed (Carbon::setTestNow through travelTo) at noon UTC: every scheduled command is run by name, the way the
 * scheduler runs it, on the day it would meet — onhost:billing:renewals, :overdue and :dunning — and the customer acts only
 * through the real HTTP routes (pay by card, top up, credit note). The edges are doubles: the card gateway and the hosting panel
 * (a stateful ISPConfig that also keeps cron jobs and FTP accounts). Everything between is the product.
 *
 *  1. postpaid: renewal invoice → unpaid → reminders on the dunning schedule → suspension (site, cron, FTP off) → the customer pays
 *     the invoice by card (Comgate callback) → the site, the cron job and the FTP account are back; the case is resolved.
 *  2. prepaid: no credit at renewal → past due, a case without a document → reminders → a top-up through the gateway renews the
 *     period from credit and brings the suspended site back.
 *  3. an invoice is paid from credit; a credit note returns exactly the part credited, once.
 *  4. an unpaid invoice that is credited (the only cancellation there is, and it goes through the command bus) ends its case and
 *     the suspension it caused; the customer has no route to void, edit or delete a document, nor to mark one paid.
 *  5. a renewal invoice in euro states its VAT in crowns at the bank rate of the supply day (a bank that is silent never stops it,
 *     the recap is added later), and its credit note keeps the rate of the invoice.
 *
 * The customer's browser session does not outlive the weeks the clock travels, so the flow signs in again (POST /v1/auth/login).
 * The card payment of an invoice is booked the platform's way: credited to the wallet, then spent on the invoice — with no receipt of
 * its own: the invoice is the tax document of the sale (G1, owner decision G-R1). A company's top-up gets a tax receipt (the seller
 * is a VAT payer here); a consumer's would get a payment confirmation, no tax document.
 * Asserts never depend on row order (SQLite and PostgreSQL alike) and nothing the customer reads names a vendor.
 */

const E2E_BILLING_VENDORS = ['ispconfig', 'comgate', 'wedos', 'proxmox', 'pterodactyl'];

beforeEach(function () {
    e2eSeedPlatform();
    e2eWebInfrastructure();
    e2eComgateEnvironment();
    config()->set('onhost.dns.parking_ipv4', '89.187.160.1');
    Http::preventStrayRequests();
    // noon UTC, well clear of the 00:00–06:00 hours in which a calendar day in Prague and in UTC differ
    $this->travelTo(Carbon::parse('2026-10-12 12:00:00', 'UTC'));
});

/** What a customer reads must never name the vendor behind the platform. */
function e2eBillingNoVendors(mixed $payload): void
{
    $text = strtolower(json_encode($payload, JSON_UNESCAPED_UNICODE) ?: '');
    foreach (E2E_BILLING_VENDORS as $vendor) {
        expect($text)->not->toContain($vendor);
    }
}

/**
 * A customer with an ACTIVE web hosting, bought over the real routes (sign-up, cart, quote, order, card callback, saga), and a
 * cron job and an FTP account on its site. @return array{0:User,1:Organization,2:string,3:Service,4:Order}
 */
function e2eBillingActiveWeb(object $test, array &$panel, array &$gate, string $email, string $fqdn): array
{
    LaravelNotification::fake();
    e2eIspSiteExtras($panel);
    e2eIspPanel($panel);
    e2eComgateFake($gate);
    [$user, $org, $password] = e2eSignUp($test, $email, 'Skladomat s.r.o.');
    $test->withHeaders(e2eHeaders('cart'))->putJson('/v1/cart', ['items' => [['product_key' => 'web-hosting', 'plan_key' => 'start', 'config' => ['fqdn' => $fqdn]]], 'commit_months' => 1, 'currency' => 'CZK'])->assertOk();
    $quote = $test->withHeaders(e2eHeaders('quote'))->postJson('/v1/cart/quote')->assertOk();
    $placed = $test->withHeaders(e2eHeaders('order'))->postJson('/v1/orders', ['quote_id' => $quote->json('data.quote_id'), 'consents' => e2eConsents(), 'payment' => ['mode' => 'gateway', 'provider' => 'comgate', 'method' => 'card']])->assertCreated();
    $order = Order::query()->findOrFail($placed->json('order_id'));
    $gate['total'] = $order->total_minor;
    e2eComgateCallback($test, $gate['trans_id'], $order->total_minor)->assertOk()->assertJsonPath('result', 'settled');
    app(OutboxPublisher::class)->relayPending();
    driveOperations();
    $service = Service::query()->where('organization_id', $org->id)->where('product_key', 'web-hosting')->firstOrFail();
    expect($service->state)->toBe(ServiceStateMachine::ACTIVE);
    $site = Website::query()->where('service_id', $service->id)->firstOrFail();
    $panel['crons'][501] = ['id' => 501, 'parent_domain_id' => $site->remote_site_id, 'command' => 'php /var/www/cron.php', 'run_min' => '*/5', 'run_hour' => '*', 'run_mday' => '*', 'run_month' => '*', 'run_wday' => '*', 'active' => 'y'];
    $panel['ftps'][601] = ['ftp_user_id' => 601, 'parent_domain_id' => $site->remote_site_id, 'username' => 'deploy', 'dir' => '/var/www/web', 'active' => 'y'];

    return [$user, $org, $password, $service->refresh(), $order];
}

/**
 * One day of cron at noon UTC, in the order the scheduler meets the commands: renewals (hourly, :20), overdue (daily), dunning
 * (daily), then what the workers do — the outbox, the operations queue (a suspension or a resume is an operation) and the mail.
 */
function e2eBillingDay(object $test, string $day, string $at = '12:20'): void
{
    $test->travelTo(Carbon::parse("{$day} {$at}:00", 'UTC'));
    foreach (['onhost:billing:renewals', 'onhost:billing:overdue', 'onhost:billing:dunning'] as $command) {
        expect(Artisan::call($command))->toBe(0);
    }
    app(OutboxPublisher::class)->relayPending();
    driveOperations();
    app(OutboxPublisher::class)->relayPending();
}

/** The customer's cookie session does not outlive the weeks the clock has travelled: sign in again, as a browser would. */
function e2eBillingLogin(object $test, string $email, string $password): void
{
    $test->flushSession();
    $test->withHeaders(e2eHeaders('login'))->postJson('/v1/auth/login', ['email' => $email, 'password' => $password])->assertOk();
}

/** What the panel double has been asked to do to the site, in order: the `active` flag of every vhost update. @return list<string> */
function e2eBillingSiteSwitches(): array
{
    return collect(Http::recorded())
        ->filter(fn (array $pair) => str_contains($pair[0]->url(), E2E_ISP) && parse_url($pair[0]->url(), PHP_URL_QUERY) === 'sites_web_domain_update')
        ->map(fn (array $pair) => (string) data_get($pair[0]->data(), 'params.active', ''))
        ->filter()->values()->all();
}

/** The panel's view of the one site, its cron job and its FTP account. @return array{site:string,cron:string,ftp:string} */
function e2eBillingPanelState(array $panel): array
{
    return ['site' => (string) $panel['sites'][array_key_first($panel['sites'])]['active'], 'cron' => (string) $panel['crons'][501]['active'], 'ftp' => (string) $panel['ftps'][601]['active']];
}

it('renews by invoice, reminds on the dunning schedule, suspends the unpaid hosting and brings all of it back when the card payment arrives', function () {
    $panel = [];
    $gate = [];
    [$user, $org, $password, $service] = e2eBillingActiveWeb($this, $panel, $gate, 'billing.e2e@example.cz', 'billing-e2e.cz');
    $subscription = Subscription::query()->where('service_id', $service->id)->firstOrFail();
    $periodEnd = $subscription->current_period_end->copy();
    expect($subscription->state)->toBe(Subscription::ACTIVE)->and($subscription->amount_minor)->toBe(8900)->and($subscription->auto_renew)->toBeTrue()
        ->and($periodEnd->toDateString())->toBe('2026-11-12')->and($subscription->next_renewal_at->toDateString())->toBe('2026-11-05'); // renewal opens a week before the period ends
    expect(e2eBillingPanelState($panel))->toBe(['site' => 'y', 'cron' => 'y', 'ftp' => 'y']);

    $setup = count(e2eBillingSiteSwitches()); // what provisioning did to the vhost is not what suspension does

    // a customer on invoice terms: the finance team approved a credit line, so a renewal is invoiced instead of charged to credit
    $org->forceFill(['billing_mode' => 'postpaid'])->save();
    CreditLine::query()->create(['organization_id' => $org->id, 'currency' => 'CZK', 'limit_minor' => 5000000, 'risk_hold_minor' => 0, 'state' => 'approved']);

    // ── the day before the renewal window opens: cron finds nothing to do ────────────────────────────────────────
    e2eBillingDay($this, '2026-11-04');
    expect(Invoice::query()->where('organization_id', $org->id)->where('type', 'invoice')->count())->toBe(0)->and(DunningCase::query()->where('organization_id', $org->id)->count())->toBe(0);

    // ── the renewal: one invoice, issued a week before the paid period ends, at the list price ───────────────────
    $timeline = [];
    for ($day = Carbon::parse('2026-11-05', 'UTC'); $day->lte(Carbon::parse('2026-12-19', 'UTC')); $day->addDay()) {
        e2eBillingDay($this, $day->toDateString()); // renewals + overdue + dunning every day, as the scheduler would; a repeated renewal run is a no-op
        $firstId = Invoice::query()->where('organization_id', $org->id)->where('type', 'invoice')->orderBy('issued_at')->value('id');
        $case = DunningCase::query()->where('organization_id', $org->id)->where('invoice_id', $firstId)->first();
        $timeline[$day->toDateString()] = ['service' => $service->refresh()->state, 'case' => $case?->state, 'notices' => $case?->notices_sent ?? [], 'panel' => e2eBillingPanelState($panel)];
    }
    $invoices = Invoice::query()->where('organization_id', $org->id)->where('type', 'invoice')->orderBy('issued_at')->get();
    expect($invoices)->toHaveCount(2); // the second period is invoiced on 5 December: one invoice per period, never two for the same
    [$first, $second] = [$invoices[0], $invoices[1]];
    expect($first->issued_at->toDateString())->toBe('2026-11-05')->and($first->issued_at->lt($periodEnd))->toBeTrue()
        ->and($first->due_at->toDateString())->toBe('2026-11-19')->and($first->subtotal_minor)->toBe(8900)->and((int) $first->discount_minor)->toBe(0)
        ->and($first->tax_minor)->toBe(1869)->and($first->total_minor)->toBe(10769) // list price + 21 % VAT, no discount nobody approved
        ->and($second->issued_at->toDateString())->toBe('2026-12-05')->and($second->subtotal_minor)->toBe(8900)->and((int) $second->discount_minor)->toBe(0);
    expect($subscription->refresh()->current_period_end->toDateString())->toBe('2027-01-12'); // two periods billed: 12 Nov → 12 Dec → 12 Jan

    // ── the dunning schedule: reminders after 3, 7 and 14 days overdue, grace, suspension at 30 ──────────────────
    expect($timeline['2026-11-19']['case'])->toBe(DunningCase::DUE)->and($timeline['2026-11-19']['notices'])->toBe([]) // due today is not late
        ->and($timeline['2026-11-21']['notices'])->toBe([])->and($timeline['2026-11-22']['notices'])->toBe([3])->and($timeline['2026-11-22']['case'])->toBe(DunningCase::OVERDUE_NOTICE)
        ->and($timeline['2026-11-25']['notices'])->toBe([3])->and($timeline['2026-11-26']['notices'])->toBe([3, 7])
        ->and($timeline['2026-12-02']['notices'])->toBe([3, 7])->and($timeline['2026-12-02']['case'])->toBe(DunningCase::OVERDUE_NOTICE)
        ->and($timeline['2026-12-03']['notices'])->toBe([3, 7, 14])->and($timeline['2026-12-03']['case'])->toBe(DunningCase::GRACE);
    foreach (['2026-11-22', '2026-12-03', '2026-12-18'] as $running) { // reminders and grace do not touch the site
        expect($timeline[$running]['service'])->toBe(ServiceStateMachine::ACTIVE)->and($timeline[$running]['panel'])->toBe(['site' => 'y', 'cron' => 'y', 'ftp' => 'y']);
    }
    expect($timeline['2026-12-18']['case'])->toBe(DunningCase::GRACE)->and($timeline['2026-12-19']['case'])->toBe(DunningCase::SUSPENDED);

    // ── suspended: the vhost, the cron job and the FTP account are off at the panel — and remembered, so they can come back ─
    expect($timeline['2026-12-19']['service'])->toBe(ServiceStateMachine::SUSPENDED)->and($timeline['2026-12-19']['panel'])->toBe(['site' => 'n', 'cron' => 'n', 'ftp' => 'n']);
    $service->refresh();
    expect($service->suspended_reason)->toBe('dunning')->and(SuspensionHold::holds($service))->toContain(SuspensionHold::PAYMENT)
        ->and(array_slice(e2eBillingSiteSwitches(), $setup))->toBe(['n']);
    $events = OutboxMessage::query()->where('organization_id', $org->id)->pluck('name')->countBy()->all();
    expect($events['dunning.notice'])->toBe(3)->and($events['dunning.grace'])->toBe(1)->and($events['dunning.suspended'])->toBe(1)->and($events['service.suspended'])->toBe(1)
        ->and($events['invoice.overdue'])->toBe(1)->and($events['dunning.opened'])->toBe(2)->and($events['subscription.renewed'])->toBe(2);
    expect(OutboxMessage::query()->whereNull('published_at')->count())->toBe(0);
    $reminders = MailOutbox::query()->where('organization_id', $org->id)->where('template_key', 'dunning-notice')->get();
    expect($reminders)->toHaveCount(3)->and($reminders->pluck('to')->unique()->all())->toBe(['billing.e2e@example.cz'])
        ->and(MailOutbox::query()->where('organization_id', $org->id)->where('template_key', 'dunning-suspended')->count())->toBe(1)
        ->and(MailOutbox::query()->where('organization_id', $org->id)->where('template_key', 'invoice-overdue')->count())->toBe(1);
    $titles = Notification::query()->where('organization_id', $org->id)->where('audience', 'customer')->pluck('title')->countBy()->all();
    expect($titles['Upomínka — neuhrazený doklad'])->toBe(3)->and($titles['Služba byla pozastavena pro neplacení'])->toBe(1);

    // ── the customer comes back, sees what is owed, and pays the oldest invoice by card ──────────────────────────
    $this->travelTo(Carbon::parse('2026-12-19 14:00:00', 'UTC'));
    e2eBillingLogin($this, 'billing.e2e@example.cz', $password);
    $detail = $this->withHeaders(e2eHeaders('service'))->getJson("/v1/services/{$service->id}")->assertOk();
    expect($detail->json('data.state'))->toBe(ServiceStateMachine::SUSPENDED);
    e2eBillingNoVendors($detail->json());
    $listed = $this->withHeaders(e2eHeaders('invoices'))->getJson('/v1/invoices')->assertOk();
    expect(collect($listed->json('data'))->where('type', 'invoice')->pluck('state')->sort()->values()->all())->toBe(['ISSUED', 'OVERDUE']);
    $gate['trans_id'] = 'E2E-INV-'.bin2hex(random_bytes(3));
    $gate['total'] = $first->total_minor;
    $pay = $this->withHeaders(e2eHeaders('pay'))->postJson("/v1/invoices/{$first->id}/pay", ['method' => 'card'])->assertOk();
    expect($pay->json('amount.minor'))->toBe(10769)->and($pay->json('payment_state'))->toBe('PENDING_CUSTOMER')->and((string) $pay->json('redirect_url'))->toContain($gate['trans_id']);
    expect($first->refresh()->state)->toBe(Invoice::OVERDUE)->and($service->refresh()->state)->toBe(ServiceStateMachine::SUSPENDED); // a redirect is not a payment
    e2eComgateCallback($this, $gate['trans_id'], $first->total_minor)->assertOk()->assertJsonPath('result', 'settled');
    e2eComgateCallback($this, $gate['trans_id'], $first->total_minor)->assertOk()->assertJsonPath('result', 'duplicate'); // a repeated callback changes nothing
    app(OutboxPublisher::class)->relayPending();
    driveOperations();
    app(OutboxPublisher::class)->relayPending();

    // ── paid: the invoice, the case, the site, the cron job and the FTP account — and nothing else ───────────────
    expect($first->refresh()->state)->toBe(Invoice::PAID)->and($first->paid_minor)->toBe(10769)->and($second->refresh()->state)->toBe(Invoice::ISSUED); // the newer invoice is not paid by it
    $case = DunningCase::query()->where('invoice_id', $first->id)->firstOrFail();
    expect($case->state)->toBe(DunningCase::RESOLVED)->and(DunningCase::query()->where('invoice_id', $second->id)->value('state'))->toBe(DunningCase::DUE);
    $service->refresh();
    expect($service->state)->toBe(ServiceStateMachine::ACTIVE)->and(SuspensionHold::holds($service))->toBe([])->and(e2eBillingPanelState($panel))->toBe(['site' => 'y', 'cron' => 'y', 'ftp' => 'y'])
        ->and(array_slice(e2eBillingSiteSwitches(), $setup))->toBe(['n', 'y']); // one suspension and one resume, not a flapping vhost
    expect($case->actions()->pluck('action')->all())->toContain('resolve')->toContain('resume');
    $events = OutboxMessage::query()->where('organization_id', $org->id)->pluck('name')->countBy()->all();
    expect($events['dunning.resolved'])->toBe(1)->and($events['service.active'])->toBe(1)->and($events['payment.succeeded'])->toBe(2) // the order's card payment and this one
        ->and($events['invoice.paid'])->toBe(3); // statement + receipt of the order, and this invoice — its card payment gets no receipt (G1)
    expect(Notification::query()->where('organization_id', $org->id)->where('title', 'Platba přijata, vše v pořádku')->count())->toBe(1);

    // ── the money: the card payment is credited and spent on this one invoice, the books stay balanced and VAT is booked once per sale ─
    $ledger = app(LedgerService::class);
    expect($ledger->balance(LedgerService::walletAccount($org->id, 'CZK'), 'CZK')->minor)->toBe(0)->and($ledger->verifyInvariant()['balanced'])->toBeTrue()
        ->and($ledger->balance(LedgerService::vatAccount('CZK'), 'CZK')->minor)->toBe(3 * 1869) // the order and the two renewal invoices, not one more for the receipt
        ->and($ledger->balance(LedgerService::receivableAccount($org->id, 'CZK'), 'CZK')->minor)->toBe(10769); // only the second invoice is still owed
    // one sale, one tax document (G1): the card payment of the invoice got no receipt, and the VAT the tax documents state is the VAT
    // the ledger booked — the order's receipt and the two renewal invoices, nothing twice
    $taxDocuments = Invoice::query()->where('organization_id', $org->id)->whereIn('type', CzkTaxStatement::TYPES)->get();
    expect($taxDocuments->where('type', 'receipt')->filter(fn (Invoice $r) => $r->issued_at->toDateString() === '2026-12-19'))->toHaveCount(0)
        ->and($taxDocuments->countBy('type')->sortKeys()->all())->toBe(['invoice' => 2, 'receipt' => 1]) // keys sorted: PostgreSQL returns unordered rows in any order
        ->and((int) $taxDocuments->sum('tax_minor'))->toBe(3 * 1869)->and($ledger->balance(LedgerService::vatAccount('CZK'), 'CZK')->minor)->toBe(3 * 1869);

    // ── the second invoice runs its own course: nothing of the first one's history is replayed on it ─────────────
    e2eBillingDay($this, '2026-12-22'); // three days after its due date
    expect(DunningCase::query()->where('invoice_id', $second->id)->first()->notices_sent)->toBe([3])->and($service->refresh()->state)->toBe(ServiceStateMachine::ACTIVE)
        ->and(e2eBillingPanelState($panel))->toBe(['site' => 'y', 'cron' => 'y', 'ftp' => 'y']);
    $final = $this->withHeaders(e2eHeaders('services-after'))->getJson("/v1/services/{$service->id}")->assertOk();
    expect($final->json('data.state'))->toBe(ServiceStateMachine::ACTIVE);
    e2eBillingNoVendors($final->json());
});

it('lets a prepaid hosting go past due without credit, reminds, suspends it, and renews and resumes it when a card top-up arrives', function () {
    $panel = [];
    $gate = [];
    [$user, $org, $password, $service] = e2eBillingActiveWeb($this, $panel, $gate, 'prepaid.e2e@example.cz', 'prepaid-e2e.cz');
    $subscription = Subscription::query()->where('service_id', $service->id)->firstOrFail();
    $ledger = app(LedgerService::class);
    $wallet = fn (): int => $ledger->balance(LedgerService::walletAccount($org->id, 'CZK'), 'CZK')->minor;
    expect($org->billing_mode)->toBe('prepaid')->and($wallet())->toBe(0); // the order was paid in full: nothing is left over
    $setup = count(e2eBillingSiteSwitches());

    // ── no credit when the renewal falls due: the period turns past due, a case opens without a document ─────────
    $timeline = [];
    for ($day = Carbon::parse('2026-11-05', 'UTC'); $day->lte(Carbon::parse('2026-12-12', 'UTC')); $day->addDay()) {
        e2eBillingDay($this, $day->toDateString());
        $case = DunningCase::query()->where('organization_id', $org->id)->first();
        $timeline[$day->toDateString()] = ['service' => $service->refresh()->state, 'subscription' => $subscription->refresh()->state, 'case' => $case?->state, 'notices' => $case?->notices_sent ?? [], 'panel' => e2eBillingPanelState($panel)];
    }
    $case = DunningCase::query()->where('organization_id', $org->id)->sole();
    expect($case->invoice_id)->toBeNull()->and($case->service_id)->toBe($service->id)->and($case->due_at->toDateString())->toBe('2026-11-12') // due when the paid period ended
        ->and(Invoice::query()->where('organization_id', $org->id)->whereIn('type', ['invoice', 'statement'])->count())->toBe(1); // nothing was invoiced and nothing charged: only the order's own statement
    expect($timeline['2026-11-05'])->toMatchArray(['service' => ServiceStateMachine::ACTIVE, 'subscription' => Subscription::PAST_DUE, 'case' => DunningCase::DUE]) // the credit was found short a week before the end
        ->and($timeline['2026-11-14']['notices'])->toBe([])->and($timeline['2026-11-15']['notices'])->toBe([3])->and($timeline['2026-11-19']['notices'])->toBe([3, 7])
        ->and($timeline['2026-11-25']['case'])->toBe(DunningCase::OVERDUE_NOTICE)->and($timeline['2026-11-26']['notices'])->toBe([3, 7, 14])->and($timeline['2026-11-26']['case'])->toBe(DunningCase::GRACE)
        ->and($timeline['2026-12-11'])->toMatchArray(['service' => ServiceStateMachine::ACTIVE, 'panel' => ['site' => 'y', 'cron' => 'y', 'ftp' => 'y']])
        ->and($timeline['2026-12-12'])->toMatchArray(['service' => ServiceStateMachine::SUSPENDED, 'case' => DunningCase::SUSPENDED, 'panel' => ['site' => 'n', 'cron' => 'n', 'ftp' => 'n']]);
    $events = OutboxMessage::query()->where('organization_id', $org->id)->get();
    $failures = $events->where('name', 'subscription.renewal_failed');
    expect($failures->count())->toBeGreaterThan(1)->and($failures->pluck('payload.attempt')->unique()->count())->toBe($failures->count()) // every daily retry is counted once
        ->and($failures->first()->payload['cause'])->toBe('credit')
        ->and($events->where('name', 'dunning.notice')->count())->toBe(3)->and($events->where('name', 'dunning.suspended')->count())->toBe(1)->and($events->where('name', 'dunning.opened')->count())->toBe(1);
    expect(MailOutbox::query()->where('organization_id', $org->id)->where('template_key', 'dunning-notice')->count())->toBe(3)
        ->and(MailOutbox::query()->where('organization_id', $org->id)->where('template_key', 'renewal-failed')->count())->toBe($failures->count())
        ->and(MailOutbox::query()->where('organization_id', $org->id)->where('template_key', 'service-suspended')->count())->toBe(1);

    // ── the customer tops up by card: the credit pays the period that ran out, and the suspension ends with the debt ─
    $this->travelTo(Carbon::parse('2026-12-12 14:00:00', 'UTC'));
    e2eBillingLogin($this, 'prepaid.e2e@example.cz', $password);
    e2eTopUp($this, $gate, 500);
    app(OutboxPublisher::class)->relayPending();
    driveOperations();
    app(OutboxPublisher::class)->relayPending();
    $service->refresh();
    expect($service->state)->toBe(ServiceStateMachine::ACTIVE)->and(SuspensionHold::holds($service))->toBe([])->and(e2eBillingPanelState($panel))->toBe(['site' => 'y', 'cron' => 'y', 'ftp' => 'y'])
        ->and(array_slice(e2eBillingSiteSwitches(), $setup))->toBe(['n', 'y']);
    $subscription->refresh();
    expect($subscription->state)->toBe(Subscription::ACTIVE)->and($subscription->renewal_failures)->toBe(0)->and($subscription->current_period_start->toDateString())->toBe('2026-11-12')
        ->and($subscription->current_period_end->toDateString())->toBe('2026-12-12') // the period the customer had used is paid; the dead days are not billed twice
        ->and($case->refresh()->state)->toBe(DunningCase::RESOLVED)->and($case->actions()->pluck('action')->all())->toContain('resolve')->toContain('resume')
        ->and($wallet())->toBe(50000 - 10769);

    // ── the next cron pass: the period that began while the service was down is charged too, once, at the list price ─
    e2eBillingDay($this, '2026-12-12', '15:20');
    e2eBillingDay($this, '2026-12-12', '16:20'); // the hourly run again: nothing more to take
    $subscription->refresh();
    expect($subscription->current_period_end->toDateString())->toBe('2027-01-12')->and($wallet())->toBe(50000 - 2 * 10769);
    $statements = Invoice::query()->where('organization_id', $org->id)->where('type', 'statement')->where('meta->subscription_id', $subscription->id)->get();
    expect($statements)->toHaveCount(2)->and($statements->every(fn (Invoice $s) => $s->state === Invoice::PAID && $s->subtotal_minor === 8900 && (int) $s->discount_minor === 0 && $s->tax_minor === 1869 && $s->total_minor === 10769))->toBeTrue();
    $ledgerCheck = $ledger->verifyInvariant();
    expect($ledgerCheck['balanced'])->toBeTrue()->and($ledger->balance(LedgerService::vatAccount('CZK'), 'CZK')->minor)->toBe(3 * 1869) // the order and two renewals; the top-up's receipt adds none
        ->and($ledger->balance(LedgerService::receivableAccount($org->id, 'CZK'), 'CZK')->minor)->toBe(0);
    $events = OutboxMessage::query()->where('organization_id', $org->id)->pluck('name')->countBy()->all();
    expect($events['dunning.resolved'])->toBe(1)->and($events['subscription.renewed'])->toBe(2)->and($events['service.active'])->toBe(1)->and($events['wallet.topup.completed'])->toBe(2);
    expect(Notification::query()->where('organization_id', $org->id)->where('title', 'Platba přijata, vše v pořádku')->count())->toBe(1);

    // ── what the customer sees ───────────────────────────────────────────────────────────────────────────────────
    $services = $this->withHeaders(e2eHeaders('services'))->getJson('/v1/services')->assertOk();
    expect($services->json('data.0.state'))->toBe(ServiceStateMachine::ACTIVE);
    $wallets = $this->withHeaders(e2eHeaders('wallet'))->getJson('/v1/wallet')->assertOk();
    e2eBillingNoVendors([$services->json(), $wallets->json(), $this->withHeaders(e2eHeaders('subs'))->getJson('/v1/subscriptions')->assertOk()->json()]);
});

/** A member of finance on a /v1 route, with a fresh step-up and no browser session of a customer left over on the test client. */
function e2eBillingFinance(object $test, User $finance): void
{
    $test->flushSession();
    app(StepUpService::class)->grant($finance, 'totp', null, '127.0.0.1');
    $test->actingAs($finance, 'sanctum');
}

/** One request as finance: the customer's browser session is dropped first (two sessions on one test client would clash). */
function e2eBillingCreditNote(object $test, string $invoiceId, string $key, array $body): TestResponse
{
    $test->flushSession();

    return $test->withHeader('Idempotency-Key', 'e2e-'.$key)->postJson("/v1/invoices/{$invoiceId}/credit-note", $body);
}

it('pays an invoice from credit only when there is credit, and finance credits it back exactly once, VAT included', function () {
    $panel = [];
    $gate = [];
    [$user, $org, $password, $service] = e2eBillingActiveWeb($this, $panel, $gate, 'credit.e2e@example.cz', 'credit-e2e.cz');
    $org->forceFill(['billing_mode' => 'postpaid'])->save();
    CreditLine::query()->create(['organization_id' => $org->id, 'currency' => 'CZK', 'limit_minor' => 5000, 'risk_hold_minor' => 0, 'state' => 'approved']); // 50 Kč of credit line: less than one renewal
    $ledger = app(LedgerService::class);
    $wallet = fn (): int => $ledger->balance(LedgerService::walletAccount($org->id, 'CZK'), 'CZK')->minor;
    e2eBillingDay($this, '2026-11-05');
    $invoice = Invoice::query()->where('organization_id', $org->id)->where('type', 'invoice')->sole();
    expect($invoice->state)->toBe(Invoice::ISSUED)->and($invoice->total_minor)->toBe(10769)->and($ledger->balance(LedgerService::receivableAccount($org->id, 'CZK'), 'CZK')->minor)->toBe(10769);

    // ── no credit: the refusal says what is missing, and nothing moves ───────────────────────────────────────────
    $refused = $this->withHeaders(e2eHeaders('pay-nocredit'))->postJson("/v1/invoices/{$invoice->id}/pay", ['method' => 'wallet'])->assertStatus(402)->assertJsonPath('error', 'insufficient_funds');
    expect($refused->json('required.minor'))->toBe(10769)->and($refused->json('available.minor'))->toBe(5000)
        ->and($invoice->refresh()->state)->toBe(Invoice::ISSUED)->and($wallet())->toBe(0)->and(DunningCase::query()->where('invoice_id', $invoice->id)->value('state'))->toBe(DunningCase::DUE);

    // ── a card top-up through the gateway, then the invoice is paid from the credit ──────────────────────────────
    e2eTopUp($this, $gate, 500);
    expect($wallet())->toBe(50000)->and(Invoice::query()->where('organization_id', $org->id)->where('type', 'receipt')->count())->toBe(2) // the order's payment and the company's top-up: each a tax receipt
        ->and(Invoice::query()->where('organization_id', $org->id)->where('type', InvoiceService::PAYMENT_CONFIRMATION)->count())->toBe(0);
    $paid = $this->withHeaders(e2eHeaders('pay-credit'))->postJson("/v1/invoices/{$invoice->id}/pay", ['method' => 'wallet'])->assertOk();
    expect($paid->json('state'))->toBe(Invoice::PAID)->and($paid->json('paid.minor'))->toBe(10769)->and($wallet())->toBe(50000 - 10769)
        ->and($invoice->refresh()->paid_minor)->toBe(10769)->and($ledger->balance(LedgerService::receivableAccount($org->id, 'CZK'), 'CZK')->minor)->toBe(0)
        ->and(DunningCase::query()->where('invoice_id', $invoice->id)->value('state'))->toBe(DunningCase::RESOLVED);
    $this->withHeaders(e2eHeaders('pay-again'))->postJson("/v1/invoices/{$invoice->id}/pay", ['method' => 'wallet'])->assertStatus(409)->assertJsonPath('error', 'invoice_not_payable'); // never twice
    expect($wallet())->toBe(50000 - 10769)->and(Invoice::query()->where('organization_id', $org->id)->whereIn('type', ['receipt', InvoiceService::PAYMENT_CONFIRMATION])->count())->toBe(2); // paying from credit is not a payment received: no receipt

    // ── the customer cannot write a document or mark one paid by hand ────────────────────────────────────────────
    $this->withHeaders(e2eHeaders('own-note'))->postJson("/v1/invoices/{$invoice->id}/credit-note", ['reason' => 'Chci peníze zpět', 'return_to_credit' => true])->assertForbidden();
    $this->withHeaders(e2eHeaders('own-paid'))->postJson("/v1/invoices/{$invoice->id}/mark-paid", ['method' => 'cash', 'reference' => 'x1', 'reason' => 'Zaplaceno hotově'])->assertForbidden();
    expect($wallet())->toBe(50000 - 10769)->and(Invoice::query()->where('organization_id', $org->id)->where('type', 'credit_note')->count())->toBe(0);

    // ── finance writes the credit notes: a part (gross 50 Kč), then the rest, then there is nothing left ─────────
    e2eBillingFinance($this, $this->staff('billing_finance_admin'));
    $line = $invoice->lines()->sole();
    $part = e2eBillingCreditNote($this, $invoice->id, 'cn-part', ['reason' => 'Částečná sleva za výpadek', 'amounts' => [(string) $line->id => 5000], 'return_to_credit' => true])->assertCreated();
    expect($part->json('invoice.type'))->toBe('credit_note')->and($part->json('invoice.corrects_invoice_id'))->toBe($invoice->id)->and($part->json('invoice.total_minor'))->toBe(-5000)
        ->and($part->json('invoice.subtotal_minor'))->toBe(-4132)->and($part->json('invoice.tax_minor'))->toBe(-868) // 50 Kč gross at 21 %: 41,32 + 8,68
        ->and($part->json('returned_to_credit.minor'))->toBe(5000)->and($wallet())->toBe(50000 - 10769 + 5000)->and($invoice->refresh()->state)->toBe(Invoice::PAID);
    $rest = e2eBillingCreditNote($this, $invoice->id, 'cn-rest', ['reason' => 'Zbytek za výpadek', 'return_to_credit' => true])->assertCreated();
    expect($rest->json('invoice.total_minor'))->toBe(-5769)->and($rest->json('invoice.tax_minor'))->toBe(-1001) // together exactly the invoice: 10 769 gross and 1 869 VAT
        ->and($rest->json('returned_to_credit.minor'))->toBe(5769)->and($wallet())->toBe(50000)->and($invoice->refresh()->state)->toBe(Invoice::CREDITED);
    e2eBillingCreditNote($this, $invoice->id, 'cn-again', ['reason' => 'Ještě jednou', 'return_to_credit' => true])->assertStatus(409)->assertJsonPath('error', 'invoice_nothing_to_credit');
    expect($wallet())->toBe(50000)->and(Invoice::query()->where('organization_id', $org->id)->where('type', 'credit_note')->count())->toBe(2);
    $notes = Invoice::query()->where('organization_id', $org->id)->where('type', 'credit_note')->get();
    expect($notes->sum('total_minor'))->toBe(-10769)->and($notes->sum('tax_minor'))->toBe(-1869)->and($notes->pluck('meta.original_number')->unique()->all())->toBe([$invoice->number]);

    // ── the books: balanced, the VAT of the credited invoice is gone again, only the order's VAT remains ─────────
    expect($ledger->verifyInvariant()['balanced'])->toBeTrue()->and($ledger->balance(LedgerService::vatAccount('CZK'), 'CZK')->minor)->toBe(1869)
        ->and($ledger->balance(LedgerService::receivableAccount($org->id, 'CZK'), 'CZK')->minor)->toBe(0)
        ->and(OutboxMessage::query()->where('organization_id', $org->id)->where('name', 'dunning.resolved')->count())->toBe(1);
});

it('lets the credit note of an unpaid invoice end its dunning case and the suspension it caused — and offers no other way to cancel one', function () {
    $panel = [];
    $gate = [];
    [$user, $org, $password, $service] = e2eBillingActiveWeb($this, $panel, $gate, 'cancel.e2e@example.cz', 'cancel-e2e.cz');
    $org->forceFill(['billing_mode' => 'postpaid'])->save();
    CreditLine::query()->create(['organization_id' => $org->id, 'currency' => 'CZK', 'limit_minor' => 5000000, 'risk_hold_minor' => 0, 'state' => 'approved']);
    for ($day = Carbon::parse('2026-11-05', 'UTC'); $day->lte(Carbon::parse('2026-12-19', 'UTC')); $day->addDay()) {
        e2eBillingDay($this, $day->toDateString());
    }
    $invoices = Invoice::query()->where('organization_id', $org->id)->where('type', 'invoice')->orderBy('issued_at')->get();
    [$first, $second] = [$invoices[0], $invoices[1]];
    expect($service->refresh()->state)->toBe(ServiceStateMachine::SUSPENDED)->and(e2eBillingPanelState($panel))->toBe(['site' => 'n', 'cron' => 'n', 'ftp' => 'n']);

    // ── the customer has no way to void, edit or delete a document: the routes do not exist, the invoice does not move ─
    $this->travelTo(Carbon::parse('2026-12-19 14:00:00', 'UTC'));
    e2eBillingLogin($this, 'cancel.e2e@example.cz', $password);
    foreach ([['postJson', "/v1/invoices/{$first->id}/cancel"], ['postJson', "/v1/invoices/{$first->id}/void"], ['deleteJson', "/v1/invoices/{$first->id}"], ['putJson', "/v1/invoices/{$first->id}"], ['patchJson', "/v1/invoices/{$first->id}"]] as [$verb, $uri]) {
        expect($this->withHeaders(e2eHeaders('void'))->{$verb}($uri, ['state' => 'CANCELLED'])->status())->toBeIn([404, 405]);
    }
    $this->withHeaders(e2eHeaders('own-paid'))->postJson("/v1/invoices/{$first->id}/mark-paid", ['method' => 'cash', 'reference' => 'x2', 'reason' => 'Zaplaceno hotově'])->assertForbidden();
    expect($first->refresh()->state)->toBe(Invoice::OVERDUE)->and($service->refresh()->state)->toBe(ServiceStateMachine::SUSPENDED);

    // ── finance credits the whole unpaid invoice (the only cancellation there is: a credit note, written through the bus) ─
    e2eBillingFinance($this, $this->staff('billing_finance_admin'));
    $note = e2eBillingCreditNote($this, $first->id, 'cn-cancel', ['reason' => 'Faktura vystavena omylem, služba neměla být účtována'])->assertCreated();
    expect($note->json('invoice.total_minor'))->toBe(-10769)->and($note->json('returned_to_credit.minor'))->toBe(0) // nothing was paid, so nothing goes back
        ->and($first->refresh()->state)->toBe(Invoice::CREDITED)->and($first->paid_minor)->toBe(0);
    $ledger = app(LedgerService::class);
    expect($ledger->balance(LedgerService::receivableAccount($org->id, 'CZK'), 'CZK')->minor)->toBe(10769) // only the second invoice is still owed
        ->and($ledger->balance(LedgerService::vatAccount('CZK'), 'CZK')->minor)->toBe(2 * 1869); // the order's and the second invoice's

    // ── the next dunning pass sees a document that is gone: the case ends, and so does the suspension ────────────
    e2eBillingDay($this, '2026-12-20');
    $case = DunningCase::query()->where('invoice_id', $first->id)->sole();
    expect($case->state)->toBe(DunningCase::RESOLVED)->and($case->actions()->pluck('action')->all())->toContain('resolve')->toContain('resume')
        ->and(DunningCase::query()->where('invoice_id', $second->id)->value('state'))->toBe(DunningCase::DUE);
    $service->refresh();
    expect($service->state)->toBe(ServiceStateMachine::ACTIVE)->and(SuspensionHold::holds($service))->toBe([])->and(e2eBillingPanelState($panel))->toBe(['site' => 'y', 'cron' => 'y', 'ftp' => 'y']);
    expect(OutboxMessage::query()->where('organization_id', $org->id)->where('name', 'dunning.resolved')->count())->toBe(1)
        ->and(OutboxMessage::query()->where('organization_id', $org->id)->where('name', 'service.active')->count())->toBe(1);
});

it('states the VAT of a renewal invoice in euro in crowns at the bank rate of the supply day, and its credit note at the rate of the invoice', function () {
    $panel = [];
    $gate = [];
    [$user, $org, $password, $service] = e2eBillingActiveWeb($this, $panel, $gate, 'eur.e2e@example.cz', 'eur-e2e.cz');
    // a customer billed in euro on invoice terms (the catalogue is priced in crowns; the contract is what makes the amount 4,00 EUR)
    $org->forceFill(['billing_mode' => 'postpaid', 'currency' => 'EUR'])->save();
    CreditLine::query()->create(['organization_id' => $org->id, 'currency' => 'EUR', 'limit_minor' => 500000, 'risk_hold_minor' => 0, 'state' => 'approved']);
    Subscription::query()->where('service_id', $service->id)->update(['currency' => 'EUR', 'amount_minor' => 400]);
    config(['onhost.billing.fx.fetch' => true]);
    $list = fn (string $day, string $rate): string => "{$day} #214\nzemě|měna|množství|kód|kurz\nEMU|euro|1|EUR|{$rate}\n";
    $bank = ['status' => 200, 'body' => $list('05.11.2026', '24,335')]; // one fake with a switch: fakes stack, the first that answers wins
    Http::fake(['www.cnb.cz/*' => function () use (&$bank) {
        return Http::response($bank['body'], $bank['status'], ['Content-Type' => 'text/plain; charset=UTF-8']);
    }]);

    e2eBillingDay($this, '2026-11-05');
    $invoice = Invoice::query()->where('organization_id', $org->id)->where('type', 'invoice')->sole();
    expect($invoice->currency)->toBe('EUR')->and($invoice->subtotal_minor)->toBe(400)->and($invoice->tax_minor)->toBe(84)->and($invoice->total_minor)->toBe(484) // 4,00 EUR + 21 % = 4,84 EUR, no discount
        ->and((int) $invoice->discount_minor)->toBe(0);
    $czk = $invoice->meta['czk'] ?? null;
    expect($czk)->not->toBeNull()->and($invoice->meta)->not->toHaveKey('czk_pending')
        ->and($czk['source'])->toBe('cnb')->and($czk['basis'])->toBe('supply_date')->and($czk['rate'])->toBe('24.335')->and($czk['valid_on'])->toBe('2026-11-05')
        ->and($czk['net_minor'])->toBe(9734)->and($czk['tax_minor'])->toBe(2044)->and($czk['total_minor'])->toBe(9734 + 2044); // 4,00 × 24,335 = 97,34 Kč and 0,84 × 24,335 = 20,44 Kč

    // the bank does not answer when the second period is invoiced: the document is issued all the same, its amounts unchanged, and completed later
    $bank = ['status' => 503, 'body' => 'maintenance'];
    $this->travelTo(Carbon::parse('2026-12-05 12:20:00', 'UTC'));
    Artisan::call('onhost:billing:renewals');
    $second = Invoice::query()->where('organization_id', $org->id)->where('type', 'invoice')->where('id', '!=', $invoice->id)->sole();
    expect($second->total_minor)->toBe(484)->and($second->meta['czk_pending'] ?? false)->toBeTrue()->and($second->meta)->not->toHaveKey('czk');

    $bank = ['status' => 200, 'body' => $list('05.12.2026', '24,500')];
    expect(Artisan::call('onhost:fx:sync'))->toBe(0);
    $second->refresh();
    expect($second->meta)->not->toHaveKey('czk_pending')->and($second->total_minor)->toBe(484)->and($second->meta['czk']['rate'])->toBe('24.500')->and($second->meta['czk']['tax_minor'])->toBe(2058); // 0,84 × 24,5; the amounts of the document never moved

    // the customer sees both documents; neither names a vendor
    e2eBillingLogin($this, 'eur.e2e@example.cz', $password);
    $listed = $this->withHeaders(e2eHeaders('eur-invoices'))->getJson('/v1/invoices')->assertOk();
    expect(collect($listed->json('data'))->where('type', 'invoice')->pluck('currency')->unique()->all())->toBe(['EUR']);
    e2eBillingNoVendors($listed->json());

    // finance credits the first invoice: the credit note carries the invoice's own rate (§ 42), not the day's — and the day's would be different
    $this->travelTo(Carbon::parse('2026-11-20 12:00:00', 'UTC'));
    $bank = ['status' => 200, 'body' => $list('20.11.2026', '25,100')];
    e2eBillingFinance($this, $this->staff('billing_finance_admin'));
    $note = e2eBillingCreditNote($this, $invoice->id, 'cn-eur', ['reason' => 'Chybně vystaveno v eurech'])->assertCreated();
    $noteCzk = $note->json('invoice.meta.czk');
    expect($note->json('invoice.total_minor'))->toBe(-484)->and($noteCzk['basis'])->toBe('original')->and($noteCzk['rate'])->toBe('24.335')
        ->and($noteCzk['net_minor'])->toBe(-9734)->and($noteCzk['tax_minor'])->toBe(-2044)
        ->and(Invoice::query()->findOrFail($invoice->id)->state)->toBe(Invoice::CREDITED);
});
