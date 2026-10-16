<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Invoicing\Commands\SendEInvoiceCommand;
use Onhost\Domain\Invoicing\Models\EInvoiceDelivery;
use Onhost\Domain\Invoicing\Models\Invoice;
use Onhost\Domain\Orders\CheckoutService;
use Onhost\Domain\Orders\QuoteService;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Platform\Commands\CommandBus;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Outbox\OutboxMessage;
use Onhost\Platform\Outbox\OutboxPublisher;

/*
 * W7-1 (Peppol layer 1): SendEInvoiceCommand through the CommandBus over the existing UBL export. One delivery row per invoice,
 * the driver `null` records it as skipped, events carry no document, recipient or raw answer, and nothing of the XML is logged.
 */

beforeEach(function () {
    $this->seed([CatalogSeeder::class, TaxRuleSeeder::class, LegalEntitySeeder::class]);
    Http::preventStrayRequests();
    Http::fake();
    config(['onhost.einvoice.driver' => 'null']);
});

function w71IssuedInvoice(User $owner, Organization $org): Invoice
{
    $ctx = new CommandContext('user', $owner->id, $org->id, null, '127.0.0.1', 'pest', 'w71-session');
    $consents = ['terms' => ['version' => '4.0'], 'privacy' => ['version' => '4.0'], 'withdrawal_waiver' => ['version' => '4.0'], 'dpa' => ['version' => '4.0'], 'sla' => ['version' => '4.0']];
    $quote = app(QuoteService::class)->quote([['product_key' => 'web-hosting', 'plan_key' => 'standard']], 'CZK', ['country' => 'CZ', 'customer_class' => 'b2c'], 12, null, $org);
    $order = app(CheckoutService::class)->placeOrder($quote, $org, $owner, $consents, ['mode' => 'bank'], 'w71:'.$org->id, $ctx)['order'];

    return Invoice::query()->findOrFail($order->invoice_id);
}

/** @return list<OutboxMessage> */
function w71EinvoiceEvents(): array
{
    return OutboxMessage::query()->where('name', 'like', 'invoice.einvoice.%')->orderBy('created_at')->get()->all();
}

/** @return array<string, mixed> */
function w71DoctorRow(): array
{
    Artisan::call('onhost:doctor', ['--json' => true]);

    return (array) collect(json_decode(trim(Artisan::output()), true, 512, JSON_THROW_ON_ERROR)['checks'])
        ->first(fn (array $row): bool => $row['area'] === 'einvoice' && $row['check'] === 'readiness');
}

it('records one skipped delivery and one queued event for two dispatches, without the document anywhere', function () {
    Log::spy();
    [$owner, $org] = $this->customerWithOrganization();
    $invoice = w71IssuedInvoice($owner, $org);
    $finance = $this->staff('billing_finance_admin');
    $context = $this->staffContextFor($finance, $org);

    $first = (array) app(CommandBus::class)->dispatch(new SendEInvoiceCommand($org->id, 'w71-send-1', ['invoice_id' => $invoice->id]), $context);
    $second = (array) app(CommandBus::class)->dispatch(new SendEInvoiceCommand($org->id, 'w71-send-2', ['invoice_id' => $invoice->id]), $context);
    app(OutboxPublisher::class)->relayPending();

    expect($first['delivery']['state'])->toBe('skipped')->and($second['delivery']['id'])->toBe($first['delivery']['id'])
        ->and($second['delivery']['attempts'])->toBe(1);
    expect(EInvoiceDelivery::query()->where('invoice_id', $invoice->id)->count())->toBe(1);
    $row = EInvoiceDelivery::query()->where('invoice_id', $invoice->id)->firstOrFail();
    expect($row->driver)->toBe('null')->and($row->delivery_id)->toBe('null:'.sha1((string) $invoice->number))->and($row->error_code)->toBeNull();

    $events = w71EinvoiceEvents();
    expect(array_map(fn (OutboxMessage $m): string => (string) $m->getAttribute('name'), $events))->toBe(['invoice.einvoice.queued']);
    foreach ($events as $event) {
        $payload = (array) $event->payload;
        expect(array_keys($payload))->toEqualCanonicalizing(['invoice_id', 'invoice_number', 'driver', 'state', 'error_code'])
            ->and($payload)->not->toHaveKey('raw')
            ->and((string) json_encode($payload))->not->toContain('<Invoice');
    }

    foreach (['debug', 'info', 'notice', 'warning', 'error', 'critical', 'alert', 'emergency', 'log'] as $level) {
        Log::shouldNotHaveReceived($level, [Mockery::on(fn ($message): bool => str_contains((string) json_encode($message), '<Invoice')), Mockery::andAnyOtherArgs()]);
        Log::shouldNotHaveReceived($level, [Mockery::any(), Mockery::on(fn ($context): bool => str_contains((string) json_encode($context), '<Invoice'))]);
    }
});

it('answers 404 for an invoice of another organization and writes nothing', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $invoice = w71IssuedInvoice($owner, $org);
    [, $stranger] = $this->customerWithOrganization();
    $finance = $this->staff('billing_finance_admin');

    try {
        app(CommandBus::class)->dispatch(new SendEInvoiceCommand($stranger->id, 'w71-foreign', ['invoice_id' => $invoice->id]), $this->staffContextFor($finance, $stranger));
        $this->fail('a foreign invoice was accepted');
    } catch (DomainError $e) {
        expect($e->status)->toBe(404);
    }
    expect(EInvoiceDelivery::query()->count())->toBe(0)->and(w71EinvoiceEvents())->toBe([]);
});

it('warns before the SK mandatory date and fails without blocking after it while the driver is null and an SK customer exists', function () {
    config(['onhost.einvoice.sk_mandatory_from' => '2027-01-01', 'onhost.einvoice.peppol.sender_id' => '']);
    $this->customerWithOrganization([], ['country' => 'SK', 'currency' => 'EUR']);

    $this->travelTo(CarbonImmutable::parse('2026-11-01 12:00:00'));
    $before = w71DoctorRow();
    expect($before['status'])->toBe('WARN')->and($before['blocking'])->toBeFalse()
        ->and($before['detail'])->toContain('driver null')->toContain('sender_id set: no');

    $this->travelTo(CarbonImmutable::parse('2027-01-02 12:00:00'));
    $after = w71DoctorRow();
    expect($after['status'])->toBe('FAIL')->and($after['blocking'])->toBeFalse();
});
