<?php

declare(strict_types=1);

namespace App\Domains\Billing\Services;

use App\Domains\Billing\Enums\InvoiceStatus;
use App\Domains\Billing\Models\FinancialExportJob;
use App\Domains\Billing\Models\Invoice;
use App\Domains\Billing\Models\Payment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;

final class FinancialExportService
{
    public function process(FinancialExportJob $job): void
    {
        $job->update(['status' => 'processing']);

        try {
            [$content, $extension, $rows] = match ($job->format) {
                'pohoda_xml'   => $this->buildPohodaXml($job),
                'csv_invoices' => $this->buildCsvInvoices($job),
                'csv_payments' => $this->buildCsvPayments($job),
                'pdf_summary'  => $this->buildPdfSummary($job),
            };

            $filename = sprintf('exports/%s_%s.%s', $job->format, $job->uuid, $extension);

            Storage::disk('local')->put($filename, $content);

            $job->update([
                'status'    => 'done',
                'file_path' => $filename,
                'row_count' => $rows,
            ]);
        } catch (\Throwable $e) {
            $job->update([
                'status'        => 'failed',
                'error_message' => mb_substr($e->getMessage(), 0, 255),
            ]);
        }
    }

    /** @return array{string, string, int} */
    private function buildPohodaXml(FinancialExportJob $job): array
    {
        $invoices = $this->invoiceQuery($job)->with(['items'])->get();

        $xml  = '<?xml version="1.0" encoding="Windows-1250"?>' . "\n";
        $xml .= '<dataPack xmlns="http://www.stormware.cz/schema/version_2/data.xsd"' . "\n";
        $xml .= '          xmlns:inv="http://www.stormware.cz/schema/version_2/invoice.xsd"' . "\n";
        $xml .= '          version="2.0" ico="' . e(config('billing.supplier.ic', '')) . '">' . "\n";

        foreach ($invoices as $invoice) {
            $totalMinor = $invoice->total->getMinorAmount()->toInt();

            $xml .= "  <dataPackItem id=\"{$invoice->uuid}\">\n";
            $xml .= "    <inv:invoice version=\"2.0\">\n";
            $xml .= "      <inv:invoiceHeader>\n";
            $xml .= '        <inv:invoiceType>issuedInvoice</inv:invoiceType>' . "\n";
            $xml .= '        <inv:number><inv:numberRequested>' . e($invoice->number) . "</inv:numberRequested></inv:number>\n";
            $xml .= '        <inv:date>' . $invoice->created_at->format('Y-m-d') . "</inv:date>\n";
            $xml .= '        <inv:dateDue>' . ($invoice->due_date?->format('Y-m-d') ?? '') . "</inv:dateDue>\n";

            $xml .= "        <inv:partnerIdentity>\n";
            $xml .= '          <inv:company>' . e($invoice->snapshot_company ?: $invoice->snapshot_name) . "</inv:company>\n";
            $xml .= '          <inv:city>' . e($invoice->snapshot_city ?? '') . "</inv:city>\n";
            $xml .= '          <inv:zip>' . e($invoice->snapshot_zip ?? '') . "</inv:zip>\n";
            $xml .= '          <inv:ico>' . e($invoice->snapshot_registration_number ?? '') . "</inv:ico>\n";
            $xml .= '          <inv:dic>' . e($invoice->snapshot_vat_number ?? '') . "</inv:dic>\n";
            $xml .= "        </inv:partnerIdentity>\n";
            $xml .= "      </inv:invoiceHeader>\n";
            $xml .= "      <inv:invoiceDetail>\n";

            foreach ($invoice->items as $item) {
                $unitMinor = $item->unit_price->getMinorAmount()->toInt();
                $xml .= "        <inv:invoiceItem>\n";
                $xml .= '          <inv:text>' . e($item->description) . "</inv:text>\n";
                $xml .= "          <inv:quantity>{$item->quantity}</inv:quantity>\n";
                $xml .= "          <inv:payVAT>false</inv:payVAT>\n";
                $xml .= "          <inv:rateVAT>none</inv:rateVAT>\n";
                $xml .= '          <inv:homeCurrency><inv:unitPrice>' . number_format($unitMinor / 100, 2, '.', '') . "</inv:unitPrice></inv:homeCurrency>\n";
                $xml .= "        </inv:invoiceItem>\n";
            }

            $xml .= "      </inv:invoiceDetail>\n";
            $xml .= "      <inv:invoiceSummary>\n";
            $xml .= '        <inv:homeCurrency><inv:priceNone>' . number_format($totalMinor / 100, 2, '.', '') . "</inv:priceNone></inv:homeCurrency>\n";
            $xml .= "      </inv:invoiceSummary>\n";
            $xml .= "    </inv:invoice>\n";
            $xml .= "  </dataPackItem>\n";
        }

        $xml .= '</dataPack>';

        return [$xml, 'xml', $invoices->count()];
    }

    /** @return array{string, string, int} */
    private function buildCsvInvoices(FinancialExportJob $job): array
    {
        $invoices = $this->invoiceQuery($job)->get();

        $rows   = [];
        $rows[] = implode(';', [
            'Číslo faktury', 'Datum vystavení', 'Datum splatnosti', 'Stav',
            'Zákazník', 'IČO', 'DIČ', 'Částka bez DPH (Kč)', 'DPH (Kč)', 'Celkem (Kč)', 'Měna',
        ]);

        foreach ($invoices as $inv) {
            $totalMinor    = $inv->total->getMinorAmount()->toInt();
            $subtotalMinor = $inv->subtotal->getMinorAmount()->toInt();
            $taxMinor      = $inv->tax_amount->getMinorAmount()->toInt();

            $rows[] = implode(';', [
                $inv->number,
                $inv->created_at->format('d.m.Y'),
                $inv->due_date?->format('d.m.Y') ?? '',
                $inv->status->value,
                $this->csvEscape($inv->snapshot_company ?: ($inv->snapshot_name ?? '')),
                $this->csvEscape($inv->snapshot_registration_number ?? ''),
                $this->csvEscape($inv->snapshot_vat_number ?? ''),
                number_format($subtotalMinor / 100, 2, ',', ''),
                number_format($taxMinor / 100, 2, ',', ''),
                number_format($totalMinor / 100, 2, ',', ''),
                $inv->currency->value,
            ]);
        }

        return [implode("\n", $rows), 'csv', $invoices->count()];
    }

    /** @return array{string, string, int} */
    private function buildCsvPayments(FinancialExportJob $job): array
    {
        $query = Payment::query()
            ->whereNotNull('invoice_id')
            ->with(['invoice'])
            ->orderBy('processed_at');

        if ($job->date_from) {
            $query->where('processed_at', '>=', $job->date_from->startOfDay());
        }
        if ($job->date_to) {
            $query->where('processed_at', '<=', $job->date_to->endOfDay());
        }

        $payments = $query->get();

        $rows   = [];
        $rows[] = implode(';', [
            'ID platby', 'Datum platby', 'Číslo faktury', 'Zákazník',
            'Způsob platby', 'Částka (Kč)', 'Měna', 'Reference',
        ]);

        foreach ($payments as $p) {
            $amountMinor = $p->amount->getMinorAmount()->toInt();
            $rows[]      = implode(';', [
                $p->id,
                $p->processed_at?->format('d.m.Y H:i') ?? '',
                $p->invoice->number ?? '',
                $this->csvEscape($p->invoice->snapshot_company ?: ($p->invoice->snapshot_name ?? '')),
                $p->method->value,
                number_format($amountMinor / 100, 2, ',', ''),
                $p->currency->value,
                $this->csvEscape($p->gateway_transaction_id ?? ''),
            ]);
        }

        return [implode("\n", $rows), 'csv', $payments->count()];
    }

    /** @return array{string, string, int} */
    private function buildPdfSummary(FinancialExportJob $job): array
    {
        $invoices = $this->invoiceQuery($job)->get();

        $totalMinor  = $invoices->sum(fn ($i) => $i->total->getMinorAmount()->toInt());
        $paidMinor   = $invoices
            ->filter(fn ($i) => $i->status === InvoiceStatus::Paid)
            ->sum(fn ($i) => $i->total->getMinorAmount()->toInt());
        $countTotal  = $invoices->count();
        $countPaid   = $invoices->filter(fn ($i) => $i->status === InvoiceStatus::Paid)->count();
        $countUnpaid = $countTotal - $countPaid;

        $html  = '<!DOCTYPE html><html><head><meta charset="UTF-8">';
        $html .= '<style>body{font-family:sans-serif;font-size:12px}';
        $html .= 'table{width:100%;border-collapse:collapse}';
        $html .= 'th,td{border:1px solid #ccc;padding:4px 8px}';
        $html .= 'th{background:#f5f5f5;text-align:left}</style></head><body>';

        $html .= '<h2>Finanční přehled — ' . config('billing.supplier.name', 'OnHost') . '</h2>';

        if ($job->date_from || $job->date_to) {
            $html .= '<p>Období: ';
            $html .= ($job->date_from ? e($job->date_from->format('d.m.Y')) : '?');
            $html .= ' – ';
            $html .= ($job->date_to ? e($job->date_to->format('d.m.Y')) : '?');
            $html .= '</p>';
        }

        $html .= '<table><tr>';
        $html .= '<th>Celkem faktur</th><th>Zaplaceno</th><th>Nezaplaceno</th>';
        $html .= '<th>Celková částka (Kč)</th><th>Zaplaceno (Kč)</th></tr><tr>';
        $html .= "<td>{$countTotal}</td><td>{$countPaid}</td><td>{$countUnpaid}</td>";
        $html .= '<td>' . number_format($totalMinor / 100, 2, ',', ' ') . '</td>';
        $html .= '<td>' . number_format($paidMinor / 100, 2, ',', ' ') . '</td>';
        $html .= '</tr></table>';

        $html .= '<br><h3>Seznam faktur</h3>';
        $html .= '<table><tr><th>Číslo</th><th>Datum</th><th>Zákazník</th><th>Stav</th><th>Částka (Kč)</th></tr>';

        foreach ($invoices as $inv) {
            $totalMinorItem = $inv->total->getMinorAmount()->toInt();
            $html .= '<tr>';
            $html .= '<td>' . e($inv->number) . '</td>';
            $html .= '<td>' . $inv->created_at->format('d.m.Y') . '</td>';
            $html .= '<td>' . e($inv->snapshot_company ?: ($inv->snapshot_name ?? '—')) . '</td>';
            $html .= '<td>' . e($inv->status->value) . '</td>';
            $html .= '<td>' . number_format($totalMinorItem / 100, 2, ',', ' ') . '</td>';
            $html .= '</tr>';
        }

        $html .= '</table></body></html>';

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html);
        $pdf->setPaper('A4', 'portrait');

        return [$pdf->output(), 'pdf', $invoices->count()];
    }

    /** @return Builder<Invoice> */
    private function invoiceQuery(FinancialExportJob $job): Builder
    {
        $query = Invoice::query()->orderBy('created_at');

        if ($job->date_from) {
            $query->whereDate('created_at', '>=', $job->date_from);
        }
        if ($job->date_to) {
            $query->whereDate('created_at', '<=', $job->date_to);
        }

        /** @var array<string, mixed>|null $filters */
        $filters = $job->filters;
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['customer_id'])) {
            $query->where('customer_id', $filters['customer_id']);
        }

        return $query;
    }

    private function csvEscape(string $value): string
    {
        if (str_contains($value, ';') || str_contains($value, '"') || str_contains($value, "\n")) {
            return '"' . str_replace('"', '""', $value) . '"';
        }
        return $value;
    }
}
