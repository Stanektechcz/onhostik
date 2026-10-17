<?php

declare(strict_types=1);

namespace Onhost\Domain\Invoicing\Commands;

use LogicException;
use Onhost\Domain\Invoicing\Models\EInvoiceDelivery;
use Onhost\Domain\Invoicing\Models\Invoice;
use Onhost\Domain\Invoicing\UblExporter;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Platform\Commands\Command;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Commands\CommandHandler;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Events\GenericEvent;
use Onhost\Platform\Outbox\OutboxPublisher;
use Onhost\Providers\Contracts\EInvoiceProvider;
use Throwable;

/**
 * W7-1: one delivery row per invoice; a row already sent, delivered or skipped is returned without a second deliver().
 * The XML is the existing UBL export. Events and the row carry {invoice_id, invoice_number, driver, state, error_code}
 * only — never the document, the recipient or the provider's raw answer, and nothing of them is logged.
 */
final class SendEInvoiceHandler implements CommandHandler
{
    public function __construct(private readonly OutboxPublisher $outbox) {}

    public function handle(Command $command, CommandContext $context): mixed
    {
        if (! $command instanceof SendEInvoiceCommand) {
            throw new LogicException('Unsupported command '.get_class($command));
        }
        $invoice = Invoice::query()->find((string) $command->get('invoice_id'));
        if ($invoice === null || $invoice->organization_id !== $command->organizationId) {
            throw DomainError::notFound('invoice');
        }
        if (! UblExporter::exportable($invoice)) {
            throw new DomainError('einvoice_not_exportable', 'This document has no structured (UBL) form and is not delivered as an e-invoice.', 422);
        }

        $delivery = EInvoiceDelivery::query()->where('invoice_id', $invoice->id)->lockForUpdate()->first();
        if ($delivery !== null && in_array($delivery->state, EInvoiceDelivery::FINAL, true)) {
            return ['delivery' => self::view($delivery)];
        }

        $provider = app(EInvoiceProvider::class);
        $driver = $provider::providerKey();
        if ($delivery === null) {
            $delivery = EInvoiceDelivery::query()->create([
                'organization_id' => $invoice->organization_id, 'invoice_id' => $invoice->id, 'invoice_number' => (string) $invoice->number,
                'driver' => $driver, 'state' => EInvoiceDelivery::QUEUED, 'attempts' => 0,
            ]);
            $this->publish('invoice.einvoice.queued', $delivery);
        }

        $delivery->attempts = $delivery->attempts + 1;
        $delivery->driver = $driver;
        try {
            $xml = app(UblExporter::class)->export($invoice);
            if ($driver !== 'null') {
                $check = $provider->validate($xml);
                if (! $check['valid']) {
                    return ['delivery' => self::view($this->fail($delivery, 'einvoice_invalid'))];
                }
            }
            $organization = Organization::query()->find($invoice->organization_id);
            $result = $provider->deliver((string) $invoice->number, $xml, ['organization_id' => $invoice->organization_id, 'country' => $organization?->country]);
        } catch (Throwable) {
            return ['delivery' => self::view($this->fail($delivery, 'einvoice_delivery_failed'))];
        }

        $delivery->delivery_id = $result['delivery_id'];
        $delivery->state = in_array($result['state'], [EInvoiceDelivery::SKIPPED, EInvoiceDelivery::SENT, EInvoiceDelivery::DELIVERED, EInvoiceDelivery::REJECTED, EInvoiceDelivery::FAILED], true)
            ? $result['state'] : EInvoiceDelivery::SENT;
        $delivery->error_code = null;
        $delivery->save();
        if (in_array($delivery->state, [EInvoiceDelivery::SENT, EInvoiceDelivery::DELIVERED], true)) {
            $this->publish('invoice.einvoice.sent', $delivery);
        } elseif (in_array($delivery->state, [EInvoiceDelivery::REJECTED, EInvoiceDelivery::FAILED], true)) {
            $delivery->error_code = 'einvoice_'.$delivery->state;
            $delivery->save();
            $this->publish('invoice.einvoice.failed', $delivery);
        }

        return ['delivery' => self::view($delivery)];
    }

    private function fail(EInvoiceDelivery $delivery, string $code): EInvoiceDelivery
    {
        $delivery->state = EInvoiceDelivery::FAILED;
        $delivery->error_code = $code;
        $delivery->save();
        $this->publish('invoice.einvoice.failed', $delivery);

        return $delivery;
    }

    private function publish(string $event, EInvoiceDelivery $delivery): void
    {
        $this->outbox->publish(GenericEvent::of($event, 'invoice', $delivery->invoice_id, [
            'invoice_id' => $delivery->invoice_id, 'invoice_number' => $delivery->invoice_number, 'driver' => $delivery->driver,
            'state' => $delivery->state, 'error_code' => $delivery->error_code,
        ], $delivery->organization_id));
    }

    /** @return array{id:string, invoice_id:string, invoice_number:string, driver:string, state:string, attempts:int, error_code:string|null} */
    private static function view(EInvoiceDelivery $delivery): array
    {
        return [
            'id' => (string) $delivery->id, 'invoice_id' => $delivery->invoice_id, 'invoice_number' => $delivery->invoice_number, 'driver' => $delivery->driver,
            'state' => $delivery->state, 'attempts' => (int) $delivery->attempts, 'error_code' => $delivery->error_code,
        ];
    }
}
