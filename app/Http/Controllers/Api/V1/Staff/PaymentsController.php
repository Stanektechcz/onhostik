<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Staff;

use App\Http\Controllers\Api\V1\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Onhost\Domain\Payments\BankStatementImporter;
use Onhost\Domain\Payments\Commands\BankCommand;
use Onhost\Platform\Commands\CommandScope;

/**
 * Finance → bank transfers: pending bank-transfer intents (proformas and top-ups waiting for money), the statement
 * lines received so far, manual recording of a line from another bank's statement and the Fio API download.
 */
final class PaymentsController extends ApiController
{
    public function bank(Request $request, BankStatementImporter $importer): JsonResponse
    {
        $this->api->authorize($request, 'billing.reconcile', CommandScope::global());

        return $this->ok($importer->overview((int) min(200, max(1, (int) $request->query('limit', 50)))));
    }

    public function recordBankLine(Request $request): JsonResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'], 'currency' => ['nullable', 'in:CZK,EUR'], 'variable_symbol' => ['nullable', 'string', 'max:20'],
            'external_id' => ['nullable', 'string', 'max:190'], 'counterparty' => ['nullable', 'string', 'max:190'], 'message' => ['nullable', 'string', 'max:250'],
            'booked_at' => ['nullable', 'date'], 'account' => ['nullable', 'string', 'max:64'],
        ]);

        return $this->dispatch(new BankCommand($this->idempotencyKey($request, 'bank.line.record'), ['op' => 'bank.line.record', 'line' => $data]), $this->api->context($request), 201);
    }

    public function syncBank(Request $request): JsonResponse
    {
        $data = $request->validate(['from' => ['nullable', 'date_format:Y-m-d'], 'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from']]);

        return $this->dispatch(new BankCommand($this->idempotencyKey($request, 'bank.sync'), ['op' => 'bank.sync', 'from' => $data['from'] ?? null, 'to' => $data['to'] ?? null]), $this->api->context($request));
    }
}
