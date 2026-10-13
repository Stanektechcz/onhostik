<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Staff;

use App\Http\Controllers\Api\V1\ApiController;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Onhost\Domain\Invoicing\AccountingExport;
use Onhost\Platform\Commands\CommandScope;
use Onhost\Platform\Errors\DomainError;

/**
 * R2 (phase R, P-R36): `GET /v1/staff/finance/exports/{kind}?month=2026-10` — the month's documents or payments as CSV for the
 * accountant (AccountingExport). Finance only (`billing.reconcile`); the read is recorded in the staff read trail (StaffReadAudit).
 */
final class AccountingExportController extends ApiController
{
    public function show(Request $request, AccountingExport $export, string $kind): Response
    {
        $this->api->authorize($request, 'billing.reconcile', CommandScope::global());
        if (! in_array($kind, AccountingExport::KINDS, true)) {
            throw DomainError::notFound('export');
        }
        $month = (string) $request->query('month', '');
        $csv = $export->csv($kind, $month);

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.($kind === 'documents' ? 'doklady' : 'platby').'-'.$month.'.csv"',
            'Cache-Control' => 'no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
