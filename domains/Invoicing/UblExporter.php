<?php

declare(strict_types=1);

namespace Onhost\Domain\Invoicing;

use Onhost\Domain\Invoicing\Models\Invoice;
use Onhost\Platform\Money\Money;

/**
 * EN 16931 / Peppol BIS Billing 3.0 mapping (UBL 2.1). The structured array is
 * frozen on the invoice at issue time; `export()` renders the XML for the SK
 * eFaktúra / Peppol delivery adapter (§64.5).
 */
final class UblExporter
{
    public const CUSTOMIZATION_ID = 'urn:cen.eu:en16931:2017#compliant#urn:fdc:peppol.eu:2017:poacc:billing:3.0';

    public const PROFILE_ID = 'urn:fdc:peppol.eu:2017:poacc:billing:01:1.0';

    /** @return array<string,mixed> */
    public function structure(Invoice $invoice): array
    {
        $lines = $invoice->lines()->get();

        return [
            'CustomizationID' => self::CUSTOMIZATION_ID,
            'ProfileID' => self::PROFILE_ID,
            'ID' => $invoice->number,
            'IssueDate' => $invoice->issued_at?->format('Y-m-d'),
            'DueDate' => $invoice->due_at?->format('Y-m-d'),
            'TaxPointDate' => $invoice->supply_date?->format('Y-m-d'),
            'InvoiceTypeCode' => $invoice->type === 'credit_note' ? '381' : ($invoice->type === 'proforma' ? '386' : '380'),
            'DocumentCurrencyCode' => $invoice->currency,
            'BuyerReference' => $invoice->buyer['organization_id'] ?? null,
            'BillingReference' => $invoice->corrects_invoice_id ? ['InvoiceDocumentReference' => ['ID' => $invoice->meta['original_number'] ?? $invoice->corrects_invoice_id]] : null,
            'AccountingSupplierParty' => [
                'EndpointID' => ['schemeID' => '9946', 'value' => $invoice->seller['vat_id'] ?? ''],
                'PartyName' => $invoice->seller['name'] ?? '',
                'PostalAddress' => [
                    'StreetName' => $invoice->seller['address']['street'] ?? '', 'CityName' => $invoice->seller['address']['city'] ?? '',
                    'PostalZone' => $invoice->seller['address']['postal_code'] ?? '', 'Country' => $invoice->seller['country'] ?? 'CZ',
                ],
                'PartyTaxScheme' => ['CompanyID' => $invoice->seller['vat_id'] ?? '', 'TaxScheme' => 'VAT'],
                'PartyLegalEntity' => ['RegistrationName' => $invoice->seller['name'] ?? '', 'CompanyID' => $invoice->seller['ico'] ?? ''],
            ],
            'AccountingCustomerParty' => [
                'PartyName' => $invoice->buyer['name'] ?? '',
                'PostalAddress' => [
                    'StreetName' => $invoice->buyer['street'] ?? '', 'CityName' => $invoice->buyer['city'] ?? '',
                    'PostalZone' => $invoice->buyer['postal_code'] ?? '', 'Country' => $invoice->buyer['country'] ?? '',
                ],
                'PartyTaxScheme' => empty($invoice->buyer['vat_id']) ? null : ['CompanyID' => $invoice->buyer['vat_id'], 'TaxScheme' => 'VAT'],
                'PartyLegalEntity' => ['RegistrationName' => $invoice->buyer['name'] ?? '', 'CompanyID' => $invoice->buyer['ico'] ?? null],
            ],
            'PaymentMeans' => [
                'PaymentMeansCode' => ($invoice->payment_method ?? '') === 'bank' ? '58' : '30',
                'PaymentID' => $invoice->payment_reference,
                'PayeeFinancialAccount' => ['ID' => $invoice->seller['iban'] ?? '', 'FinancialInstitutionBranch' => $invoice->seller['bic'] ?? ''],
            ],
            'TaxTotal' => [
                'TaxAmount' => Money::minor($invoice->tax_minor, $invoice->currency)->toDecimal(),
                'TaxSubtotal' => array_map(fn ($row) => [
                    'TaxableAmount' => Money::minor((int) $row['net'], $invoice->currency)->toDecimal(),
                    'TaxAmount' => Money::minor((int) $row['tax'], $invoice->currency)->toDecimal(),
                    'TaxCategory' => ['ID' => $row['category'], 'Percent' => $row['rate'], 'TaxExemptionReason' => $this->exemptionReason($row['category']), 'TaxScheme' => 'VAT'],
                ], $invoice->tax_summary ?? []),
            ],
            'LegalMonetaryTotal' => [
                'LineExtensionAmount' => Money::minor($invoice->subtotal_minor - $invoice->discount_minor, $invoice->currency)->toDecimal(),
                'TaxExclusiveAmount' => Money::minor($invoice->subtotal_minor - $invoice->discount_minor, $invoice->currency)->toDecimal(),
                'TaxInclusiveAmount' => Money::minor($invoice->total_minor, $invoice->currency)->toDecimal(),
                'AllowanceTotalAmount' => Money::minor($invoice->discount_minor, $invoice->currency)->toDecimal(),
                'PrepaidAmount' => Money::minor($invoice->paid_minor, $invoice->currency)->toDecimal(),
                'PayableAmount' => Money::minor($invoice->total_minor - $invoice->paid_minor, $invoice->currency)->toDecimal(),
            ],
            'InvoiceLine' => $lines->map(fn ($line) => [
                'ID' => (string) $line->position,
                'InvoicedQuantity' => ['unitCode' => 'C62', 'value' => $line->qty],
                'LineExtensionAmount' => Money::minor($line->net_minor, $invoice->currency)->toDecimal(),
                'InvoicePeriod' => $line->period_from ? ['StartDate' => $line->period_from->format('Y-m-d'), 'EndDate' => $line->period_to?->format('Y-m-d')] : null,
                'Item' => ['Name' => $line->description, 'SellersItemIdentification' => $line->sku, 'ClassifiedTaxCategory' => ['ID' => $line->tax_category, 'Percent' => $line->tax_rate, 'TaxScheme' => 'VAT']],
                'Price' => ['PriceAmount' => Money::minor($line->unit_net_minor, $invoice->currency)->toDecimal()],
            ])->all(),
        ];
    }

    public function export(Invoice $invoice): string
    {
        $s = $invoice->structured ?? $this->structure($invoice);
        $isCredit = ($s['InvoiceTypeCode'] ?? '380') === '381';
        $root = $isCredit ? 'CreditNote' : 'Invoice';
        $ns = $isCredit ? 'urn:oasis:names:specification:ubl:schema:xsd:CreditNote-2' : 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2';
        $lineTag = $isCredit ? 'CreditNoteLine' : 'InvoiceLine';
        $qtyTag = $isCredit ? 'CreditedQuantity' : 'InvoicedQuantity';
        $e = fn (?string $v) => htmlspecialchars((string) $v, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $x = [];
        $x[] = '<?xml version="1.0" encoding="UTF-8"?>';
        $x[] = "<{$root} xmlns=\"{$ns}\" xmlns:cac=\"urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2\" xmlns:cbc=\"urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2\">";
        $x[] = "<cbc:CustomizationID>{$e($s['CustomizationID'])}</cbc:CustomizationID><cbc:ProfileID>{$e($s['ProfileID'])}</cbc:ProfileID>";
        $x[] = "<cbc:ID>{$e($s['ID'])}</cbc:ID><cbc:IssueDate>{$e($s['IssueDate'])}</cbc:IssueDate>";
        if (! $isCredit && ! empty($s['DueDate'])) {
            $x[] = "<cbc:DueDate>{$e($s['DueDate'])}</cbc:DueDate>";
        }
        $x[] = $isCredit ? '<cbc:CreditNoteTypeCode>381</cbc:CreditNoteTypeCode>' : "<cbc:InvoiceTypeCode>{$e($s['InvoiceTypeCode'])}</cbc:InvoiceTypeCode>";
        $x[] = "<cbc:DocumentCurrencyCode>{$e($s['DocumentCurrencyCode'])}</cbc:DocumentCurrencyCode>";
        if (! empty($s['BuyerReference'])) {
            $x[] = "<cbc:BuyerReference>{$e($s['BuyerReference'])}</cbc:BuyerReference>";
        }
        if (! empty($s['BillingReference'])) {
            $x[] = "<cac:BillingReference><cac:InvoiceDocumentReference><cbc:ID>{$e($s['BillingReference']['InvoiceDocumentReference']['ID'])}</cbc:ID></cac:InvoiceDocumentReference></cac:BillingReference>";
        }
        foreach (['AccountingSupplierParty' => $s['AccountingSupplierParty'], 'AccountingCustomerParty' => $s['AccountingCustomerParty']] as $tag => $party) {
            $x[] = "<cac:{$tag}><cac:Party>";
            if (! empty($party['EndpointID']['value'])) {
                $x[] = "<cbc:EndpointID schemeID=\"{$e($party['EndpointID']['schemeID'])}\">{$e($party['EndpointID']['value'])}</cbc:EndpointID>";
            }
            $x[] = "<cac:PartyName><cbc:Name>{$e($party['PartyName'])}</cbc:Name></cac:PartyName>";
            $a = $party['PostalAddress'];
            $x[] = "<cac:PostalAddress><cbc:StreetName>{$e($a['StreetName'])}</cbc:StreetName><cbc:CityName>{$e($a['CityName'])}</cbc:CityName><cbc:PostalZone>{$e($a['PostalZone'])}</cbc:PostalZone><cac:Country><cbc:IdentificationCode>{$e($a['Country'])}</cbc:IdentificationCode></cac:Country></cac:PostalAddress>";
            if (! empty($party['PartyTaxScheme']['CompanyID'])) {
                $x[] = "<cac:PartyTaxScheme><cbc:CompanyID>{$e($party['PartyTaxScheme']['CompanyID'])}</cbc:CompanyID><cac:TaxScheme><cbc:ID>VAT</cbc:ID></cac:TaxScheme></cac:PartyTaxScheme>";
            }
            $x[] = "<cac:PartyLegalEntity><cbc:RegistrationName>{$e($party['PartyLegalEntity']['RegistrationName'])}</cbc:RegistrationName>".(! empty($party['PartyLegalEntity']['CompanyID']) ? "<cbc:CompanyID>{$e($party['PartyLegalEntity']['CompanyID'])}</cbc:CompanyID>" : '').'</cac:PartyLegalEntity>';
            $x[] = "</cac:Party></cac:{$tag}>";
        }
        $pm = $s['PaymentMeans'];
        $x[] = "<cac:PaymentMeans><cbc:PaymentMeansCode>{$e($pm['PaymentMeansCode'])}</cbc:PaymentMeansCode><cbc:PaymentID>{$e($pm['PaymentID'])}</cbc:PaymentID><cac:PayeeFinancialAccount><cbc:ID>{$e($pm['PayeeFinancialAccount']['ID'])}</cbc:ID></cac:PayeeFinancialAccount></cac:PaymentMeans>";
        $cur = $e($s['DocumentCurrencyCode']);
        $x[] = "<cac:TaxTotal><cbc:TaxAmount currencyID=\"{$cur}\">{$e($s['TaxTotal']['TaxAmount'])}</cbc:TaxAmount>";
        foreach ($s['TaxTotal']['TaxSubtotal'] as $sub) {
            $reason = ! empty($sub['TaxCategory']['TaxExemptionReason']) ? "<cbc:TaxExemptionReason>{$e($sub['TaxCategory']['TaxExemptionReason'])}</cbc:TaxExemptionReason>" : '';
            $x[] = "<cac:TaxSubtotal><cbc:TaxableAmount currencyID=\"{$cur}\">{$e($sub['TaxableAmount'])}</cbc:TaxableAmount><cbc:TaxAmount currencyID=\"{$cur}\">{$e($sub['TaxAmount'])}</cbc:TaxAmount><cac:TaxCategory><cbc:ID>{$e($sub['TaxCategory']['ID'])}</cbc:ID><cbc:Percent>{$e($sub['TaxCategory']['Percent'])}</cbc:Percent>{$reason}<cac:TaxScheme><cbc:ID>VAT</cbc:ID></cac:TaxScheme></cac:TaxCategory></cac:TaxSubtotal>";
        }
        $x[] = '</cac:TaxTotal>';
        $t = $s['LegalMonetaryTotal'];
        $x[] = "<cac:LegalMonetaryTotal><cbc:LineExtensionAmount currencyID=\"{$cur}\">{$e($t['LineExtensionAmount'])}</cbc:LineExtensionAmount><cbc:TaxExclusiveAmount currencyID=\"{$cur}\">{$e($t['TaxExclusiveAmount'])}</cbc:TaxExclusiveAmount><cbc:TaxInclusiveAmount currencyID=\"{$cur}\">{$e($t['TaxInclusiveAmount'])}</cbc:TaxInclusiveAmount><cbc:PrepaidAmount currencyID=\"{$cur}\">{$e($t['PrepaidAmount'])}</cbc:PrepaidAmount><cbc:PayableAmount currencyID=\"{$cur}\">{$e($t['PayableAmount'])}</cbc:PayableAmount></cac:LegalMonetaryTotal>";
        foreach ($s['InvoiceLine'] as $line) {
            $period = ! empty($line['InvoicePeriod']) ? "<cac:InvoicePeriod><cbc:StartDate>{$e($line['InvoicePeriod']['StartDate'])}</cbc:StartDate><cbc:EndDate>{$e($line['InvoicePeriod']['EndDate'])}</cbc:EndDate></cac:InvoicePeriod>" : '';
            $x[] = "<cac:{$lineTag}><cbc:ID>{$e($line['ID'])}</cbc:ID><cbc:{$qtyTag} unitCode=\"C62\">{$e($line['InvoicedQuantity']['value'])}</cbc:{$qtyTag}><cbc:LineExtensionAmount currencyID=\"{$cur}\">{$e($line['LineExtensionAmount'])}</cbc:LineExtensionAmount>{$period}<cac:Item><cbc:Name>{$e($line['Item']['Name'])}</cbc:Name><cac:ClassifiedTaxCategory><cbc:ID>{$e($line['Item']['ClassifiedTaxCategory']['ID'])}</cbc:ID><cbc:Percent>{$e($line['Item']['ClassifiedTaxCategory']['Percent'])}</cbc:Percent><cac:TaxScheme><cbc:ID>VAT</cbc:ID></cac:TaxScheme></cac:ClassifiedTaxCategory></cac:Item><cac:Price><cbc:PriceAmount currencyID=\"{$cur}\">{$e($line['Price']['PriceAmount'])}</cbc:PriceAmount></cac:Price></cac:{$lineTag}>";
        }
        $x[] = "</{$root}>";

        return implode("\n", $x);
    }

    private function exemptionReason(string $category): ?string
    {
        return match ($category) {
            'AE' => 'Reverse charge',
            'O' => 'Not subject to VAT',
            'E' => 'Exempt from VAT',
            'Z' => 'Zero rated',
            default => null,
        };
    }
}
