<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Presenters\Presenters;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Onhost\Domain\Invoicing\Commands\InvoiceCommand;
use Onhost\Domain\Invoicing\InvoiceService;
use Onhost\Domain\Invoicing\Models\Invoice;
use Onhost\Domain\Invoicing\UblExporter;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Platform\Audit\AuditRecorder;
use Onhost\Platform\Commands\CommandScope;
use Onhost\Platform\Errors\DomainError;

final class InvoiceController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $organization = $this->api->organization($request);
        $this->api->authorize($request, 'billing.invoice.read', CommandScope::organization($organization->id));
        $query = Invoice::query()->where('organization_id', $organization->id)->where('state', '!=', Invoice::DRAFT);
        if ($request->filled('state')) {
            $query->where('state', strtoupper((string) $request->query('state')));
        }
        if ($request->filled('type')) {
            $query->where('type', (string) $request->query('type'));
        }

        return $this->api->paginate($request, $query, fn (Invoice $i) => Presenters::invoice($i), 'issued_at');
    }

    public function show(Request $request, string $invoice): JsonResponse
    {
        return response()->json(['data' => Presenters::invoice($this->resolve($request, $invoice), true)]);
    }

    public function pdf(Request $request, InvoiceService $invoices, AuditRecorder $audit, string $invoice): Response
    {
        $model = $this->resolve($request, $invoice);
        $binary = $invoices->pdfBinary($model);
        $audit->record($this->api->context($request), 'invoice.download', 'succeeded', ['number' => $model->number, 'format' => 'pdf'], 'invoice', $model->id);

        return response($binary, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="'.($model->number ?? $model->id).'.pdf"', 'X-Document-Hash' => (string) $model->pdf_hash]);
    }

    public function ubl(Request $request, UblExporter $ubl, AuditRecorder $audit, string $invoice): Response
    {
        $model = $this->resolve($request, $invoice);
        $xml = $ubl->export($model);
        $audit->record($this->api->context($request), 'invoice.download', 'succeeded', ['number' => $model->number, 'format' => 'ubl'], 'invoice', $model->id);

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="'.($model->number ?? $model->id).'.xml"']);
    }

    public function pay(Request $request, string $invoice): JsonResponse
    {
        $model = $this->resolve($request, $invoice);
        $data = $request->validate(['method' => ['nullable', 'in:wallet,credit,bank,card'], 'return_urls' => ['nullable', 'array']]);
        $op = match ($data['method'] ?? 'wallet') {
            'bank' => 'pay_by_bank', 'card' => 'pay_by_card', default => 'pay_from_wallet'
        };

        return $this->dispatch(new InvoiceCommand($model->organization_id, $this->idempotencyKey($request, 'invoice.pay:'.$op), ['op' => $op, 'invoice_id' => $model->id, 'return_urls' => $data['return_urls'] ?? []]), $this->api->context($request, Organization::query()->find($model->organization_id)));
    }

    public function creditNote(Request $request, string $invoice): JsonResponse
    {
        $model = $this->resolve($request, $invoice, 'billing.invoice.manage');
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:250'], 'line_ids' => ['nullable', 'array', 'max:100'], 'line_ids.*' => ['string', 'max:40'], 'amounts' => ['nullable', 'array', 'max:100'], 'amounts.*' => ['integer', 'min:1'], 'return_to_credit' => ['nullable', 'boolean'], 'incident_ref' => ['nullable', 'string', 'max:40']]);

        return $this->dispatch(new InvoiceCommand($model->organization_id, $this->idempotencyKey($request, 'invoice.credit'), ['op' => 'credit_note', 'invoice_id' => $model->id] + $data), $this->api->context($request, Organization::query()->find($model->organization_id), $data['reason']), 201);
    }

    public function markPaid(Request $request, string $invoice): JsonResponse
    {
        $model = $this->resolve($request, $invoice, 'billing.invoice.manage');
        $data = $request->validate(['method' => ['required', 'in:bank,card,cash,other'], 'reference' => ['required', 'string', 'max:60'], 'reason' => ['required', 'string', 'max:250']]);

        return $this->dispatch(new InvoiceCommand($model->organization_id, $this->idempotencyKey($request, 'invoice.markpaid'), ['op' => 'mark_paid', 'invoice_id' => $model->id] + $data), $this->api->context($request, Organization::query()->find($model->organization_id), $data['reason']));
    }

    private function resolve(Request $request, string $id, string $permission = 'billing.invoice.read'): Invoice
    {
        $invoice = Invoice::query()->find($id) ?? Invoice::query()->where('number', $id)->first();
        if ($invoice === null || $invoice->state === Invoice::DRAFT) {
            throw DomainError::notFound('invoice');
        }
        $this->api->authorize($request, $permission, CommandScope::organization($invoice->organization_id));

        return $invoice;
    }
}
