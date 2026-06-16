<?php

declare(strict_types=1);

use App\Domains\Ai\Models\AiRun;
use App\Domains\Ai\Models\AiUsageLog;
use App\Domains\Billing\Actions\ProcessMockPaymentAction;
use App\Domains\Billing\Events\InvoicePaid;
use App\Domains\Billing\Listeners\HandleInvoicePaid;
use App\Domains\Billing\Models\CreditTransaction;
use App\Domains\Billing\Models\Invoice;
use App\Domains\Billing\Services\CreditLedger;
use App\Domains\Support\Models\SupportTicket;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;
use Spatie\Activitylog\Models\Activity;

beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class]);
});

it('loads the customer dashboard with real widgets', function (): void {
    $user = customerUser();
    placeOrder($user);

    $this->actingAs($user)
        ->get(route('panel.dashboard'))
        ->assertOk()
        ->assertSee(__('panel.dashboard.unpaid_invoices'))
        ->assertSee(__('panel.dashboard.quick_actions'));
});

it('tops up credit via mock payment and never double-credits', function (): void {
    $user     = customerUser();
    $customer = $user->customer;
    $ledger   = app(CreditLedger::class);

    // 1. create the top-up invoice
    $this->actingAs($user)
        ->post(route('panel.billing.credits.topup'), ['amount' => 500])
        ->assertRedirect();

    $invoice = Invoice::query()->where('purpose', 'credit_topup')->firstOrFail();
    expect($invoice->total?->getMinorAmount()->toInt())->toBe(50_000);

    // 2. pay it with the mock gateway → wallet credited
    app(ProcessMockPaymentAction::class)->execute($invoice);

    expect($ledger->getBalance($customer->fresh())->getMinorAmount()->toInt())->toBe(50_000);

    // 3. replaying the listener never credits twice
    $payment = $invoice->payments()->firstOrFail();
    app(HandleInvoicePaid::class)->handle(new InvoicePaid($invoice->fresh(), $payment));

    expect($ledger->getBalance($customer->fresh())->getMinorAmount()->toInt())->toBe(50_000)
        ->and(CreditTransaction::query()->count())->toBe(1);
});

it('refuses paying a top-up invoice from credit', function (): void {
    $user = customerUser();

    $this->actingAs($user)->post(route('panel.billing.credits.topup'), ['amount' => 300]);
    $invoice = Invoice::query()->where('purpose', 'credit_topup')->firstOrFail();

    $this->actingAs($user)
        ->post(route('panel.billing.invoices.pay-credit', $invoice))
        ->assertSessionHasErrors('payment');
});

it('creates, shows and replies to a support ticket with events', function (): void {
    $user = customerUser();

    $this->actingAs($user)
        ->post(route('panel.support.store'), [
            'subject' => 'Web mi nejede',
            'message' => 'Dobrý den, od rána mi nejede web, můžete se podívat?',
        ])
        ->assertRedirect();

    $ticket = SupportTicket::query()->firstOrFail();

    $this->actingAs($user)->get(route('panel.support.show', $ticket))->assertOk()->assertSee('Web mi nejede');

    $this->actingAs($user)
        ->post(route('panel.support.reply', $ticket), ['message' => 'Doplňuji: jde o doménu example.cz'])
        ->assertRedirect();

    expect($ticket->fresh()->messages()->count())->toBe(2)
        ->and($ticket->events()->pluck('event')->all())->toContain('created', 'replied')
        ->and(Activity::query()->where('description', 'support.ticket_created')->exists())->toBeTrue();
});

it('blocks customers from another customer\'s ticket', function (): void {
    $owner    = customerUser();
    $intruder = customerUser();

    $this->actingAs($owner)->post(route('panel.support.store'), [
        'subject' => 'Privátní ticket',
        'message' => 'Toto je soukromá zpráva pro podporu.',
    ]);

    $ticket = SupportTicket::query()->firstOrFail();

    $this->actingAs($intruder)->get(route('panel.support.show', $ticket))->assertForbidden();
});

it('runs the mock AI assistant and records the trail', function (): void {
    $user = customerUser();

    $this->actingAs($user)
        ->post(route('panel.ai.run'), [
            'feature' => 'plan_recommendation',
            'text'    => 'Potřebuji hosting pro firemní web s e-shopem.',
        ])
        ->assertRedirect();

    $run = AiRun::query()->firstOrFail();

    expect($run->provider)->toBe('mock')
        ->and($run->messages()->count())->toBe(2)
        ->and(AiUsageLog::query()->count())->toBe(1)
        ->and(Activity::query()->where('description', 'ai.run_completed')->exists())->toBeTrue();
});

it('saves billing details for the tax document flow', function (): void {
    $user = customerUser();

    $this->actingAs($user)
        ->put(route('panel.account.billing.update'), [
            'type'         => 'company',
            'company_name' => 'Test s.r.o.',
            'vat_number'   => 'CZ12345678',
            'country_code' => 'cz',
            'street'       => 'Dlouhá 1',
            'city'         => 'Praha',
            'zip'          => '11000',
        ])
        ->assertRedirect();

    $customer = $user->customer->fresh();

    expect($customer->company_name)->toBe('Test s.r.o.')
        ->and($customer->country_code)->toBe('CZ')
        ->and($customer->billingAddress()?->street)->toBe('Dlouhá 1');
});
