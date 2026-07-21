<?php

declare(strict_types=1);

namespace App\Domains\Billing\Services;

use App\Domains\Billing\Models\Invoice;
use Brick\Money\Money;

/**
 * Builds the SPAYD string for "QR Platba" (audit D45).
 *
 * SPAYD (Short Payment Descriptor) is the Czech Banking Association's standard
 * — every Czech banking app scans it. The payload is a plain string; encoding
 * it as an actual QR image is the caller's job (see QrCodeRenderer), which
 * keeps this class trivially testable against the published format.
 *
 * Format: `SPD*1.0*ACC:<IBAN>*AM:<amount>*CC:<currency>*X-VS:<variable symbol>`
 * Field order matters only for ACC (must be first after the version).
 */
final class QrPaymentGenerator
{
    /** Characters SPAYD reserves as separators — must never appear in a value. */
    private const FORBIDDEN = ['*', "\n", "\r"];

    /**
     * Null when the invoice cannot be paid this way — no configured account
     * for its currency, nothing outstanding, or already paid. Returning null
     * rather than a broken string keeps a scannable-but-wrong QR off the PDF.
     */
    public function forInvoice(Invoice $invoice): ?string
    {
        if ($invoice->paid_at !== null) {
            return null;
        }

        $account = $this->accountFor($invoice->currency->value);

        if ($account === null) {
            return null;
        }

        /** @var Money $total */
        $total = $invoice->total;

        $segments = [
            'SPD',
            '1.0',
            'ACC:' . $account,
            // SPAYD wants a plain decimal with a dot, max 2 places.
            'AM:' . number_format((float) $total->getAmount()->toFloat(), 2, '.', ''),
            'CC:' . strtoupper($total->getCurrency()->getCurrencyCode()),
        ];

        if (! empty($invoice->variable_symbol)) {
            $segments[] = 'X-VS:' . $this->sanitize((string) $invoice->variable_symbol);
        }

        $segments[] = 'MSG:' . $this->sanitize(
            mb_substr('Faktura ' . $invoice->number, 0, 60),
        );

        return implode('*', $segments);
    }

    /** IBAN for the invoice currency, or null when none is configured. */
    private function accountFor(string $currency): ?string
    {
        $key = strtoupper($currency) === 'EUR'
            ? 'billing.supplier.bank_account_eur'
            : 'billing.supplier.bank_account_czk';

        $account = config($key);

        if (! is_string($account) || trim($account) === '') {
            return null;
        }

        // SPAYD requires the account with no spaces.
        return strtoupper(str_replace(' ', '', trim($account)));
    }

    /**
     * A `*` inside a value would silently split the payload into extra fields,
     * producing a QR that scans but pays the wrong thing.
     */
    private function sanitize(string $value): string
    {
        return str_replace(self::FORBIDDEN, '', $value);
    }
}
