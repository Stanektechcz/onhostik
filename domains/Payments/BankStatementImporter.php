<?php

declare(strict_types=1);

namespace Onhost\Domain\Payments;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Payments\Models\BankStatementLine;
use Onhost\Domain\Payments\Models\PaymentIntent;
use Onhost\Domain\Payments\Models\PaymentStateMachineStates as S;
use Onhost\Platform\Audit\AuditRecorder;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Money\Money;

/**
 * Bank transfers have no webhook: incoming payments arrive as statement lines and are matched to the pending
 * bank-transfer intent by variable symbol + amount (proformas, top-ups). Lines come from the Fio bank API
 * (`onhost:bank:sync`, token per account) or are recorded by finance by hand from another bank's statement.
 * Every line is stored once (unique external id), so re-running the sync or re-typing a line never pays twice.
 */
final class BankStatementImporter
{
    public const FIO_API = 'https://fioapi.fio.cz/v1/rest';

    public function __construct(private readonly PaymentService $payments, private readonly AuditRecorder $audit) {}

    /**
     * Record one incoming statement line and try to settle the matching intent.
     *
     * @param  array{external_id?:?string, amount:int|float|string, currency?:string, variable_symbol?:?string, counterparty?:?string, message?:?string, booked_at?:?string, account?:?string}  $input
     * @return array{line:BankStatementLine, created:bool, intent:?PaymentIntent, result:string}
     */
    public function record(array $input, CommandContext $context): array
    {
        $currency = strtoupper((string) ($input['currency'] ?? 'CZK'));
        $amountMinor = $this->minor($input['amount'] ?? 0);
        if ($amountMinor <= 0) {
            throw new DomainError('bank_line_amount', 'Only incoming (positive) amounts can be matched to payments.', 422, ['field' => 'amount']);
        }
        $vs = $this->digits($input['variable_symbol'] ?? null);
        $bookedAt = ! empty($input['booked_at']) ? Carbon::parse((string) $input['booked_at']) : now();
        $externalId = trim((string) ($input['external_id'] ?? ''));
        if ($externalId === '') {
            $externalId = 'manual:'.hash('sha256', implode('|', [$vs, $amountMinor, $currency, $bookedAt->toDateString(), mb_strtolower(trim((string) ($input['counterparty'] ?? '')))]));
        }
        $existing = BankStatementLine::query()->where('external_id', $externalId)->first();
        if ($existing !== null) {
            $intent = $existing->matched_payment_intent_id ? PaymentIntent::query()->find($existing->matched_payment_intent_id) : null;

            return ['line' => $existing, 'created' => false, 'intent' => $intent, 'result' => $existing->state === 'matched' ? 'already_matched' : 'duplicate'];
        }
        $line = BankStatementLine::query()->create([
            'account' => mb_substr((string) ($input['account'] ?? (config('onhost.payments.bank.account_number') ?: 'manual')), 0, 64),
            'amount_minor' => $amountMinor, 'currency' => $currency, 'variable_symbol' => $vs !== '' ? $vs : null,
            'counterparty' => ! empty($input['counterparty']) ? mb_substr((string) $input['counterparty'], 0, 190) : null,
            'message' => ! empty($input['message']) ? mb_substr((string) $input['message'], 0, 250) : null,
            'booked_at' => $bookedAt, 'external_id' => mb_substr($externalId, 0, 190), 'state' => 'unmatched',
        ]);
        $intent = $this->payments->matchBankLine($line, $context);
        $line->refresh();
        $result = $intent !== null ? 'matched' : ($vs === '' ? 'no_symbol' : ($this->pendingBySymbol($vs, $currency) !== null ? 'amount_mismatch' : 'unmatched'));
        $this->audit->record($context, 'payment.bank_line.record', 'succeeded', ['external_id' => $line->external_id, 'variable_symbol' => $vs, 'amount' => Money::minor($amountMinor, $currency), 'result' => $result, 'intent' => $intent?->id], 'bank_statement_line', $line->id);

        return ['line' => $line, 'created' => true, 'intent' => $intent, 'result' => $result];
    }

    /**
     * Pull new transactions from the Fio bank API (the token is bound to one account; Fio keeps a server-side
     * bookmark, so `last` returns only what was not downloaded yet). With `$from`/`$to` a date range is fetched instead.
     *
     * @return array{fetched:int, recorded:int, matched:int, skipped:int, account:?string}
     */
    public function syncFio(CommandContext $context, ?string $from = null, ?string $to = null): array
    {
        $token = (string) config('onhost.payments.bank.fio_token', '');
        if ($token === '') {
            throw new DomainError('bank_sync_unconfigured', 'Set ONHOST_BANK_FIO_TOKEN (Fio API token for the incoming-payments account) to sync statements.', 422);
        }
        $url = $from !== null
            ? sprintf('%s/periods/%s/%s/%s/transactions.json', self::FIO_API, $token, $from, $to ?? now()->toDateString())
            : sprintf('%s/last/%s/transactions.json', self::FIO_API, $token);
        $response = Http::acceptJson()->timeout(30)->retry(2, 1500)->get($url);
        if ($response->status() === 409) {
            throw new DomainError('bank_sync_rate_limited', 'The bank API accepts one download per 30 seconds; try again shortly.', 429);
        }
        if (! $response->successful()) {
            throw new DomainError('bank_sync_failed', 'The bank API refused the statement download (HTTP '.$response->status().'). Check the token and its permissions.', 502, ['status' => $response->status()]);
        }
        $statement = (array) $response->json('accountStatement', []);
        $info = (array) ($statement['info'] ?? []);
        $account = isset($info['accountId']) ? $info['accountId'].'/'.($info['bankId'] ?? '2010') : null;
        $stats = ['fetched' => 0, 'recorded' => 0, 'matched' => 0, 'skipped' => 0, 'account' => $account];
        foreach ((array) (($statement['transactionList'] ?? [])['transaction'] ?? []) as $tx) {
            $stats['fetched']++;
            $amount = $this->column($tx, 1);
            if ($amount === null || (float) $amount <= 0) {
                $stats['skipped']++; // outgoing payments and fees are not customer money

                continue;
            }
            $counterparty = trim(implode(' ', array_filter([$this->column($tx, 10), $this->column($tx, 2) !== null ? '('.$this->column($tx, 2).'/'.($this->column($tx, 3) ?? '').')' : null])));
            $outcome = $this->record([
                'external_id' => 'fio:'.$this->column($tx, 22), 'amount' => $amount, 'currency' => $this->column($tx, 14) ?? ($info['currency'] ?? 'CZK'),
                'variable_symbol' => $this->column($tx, 5), 'counterparty' => $counterparty !== '' ? $counterparty : null,
                'message' => $this->column($tx, 16) ?? $this->column($tx, 25), 'booked_at' => $this->column($tx, 0), 'account' => $account,
            ], $context);
            if ($outcome['created']) {
                $stats['recorded']++;
            }
            if ($outcome['result'] === 'matched') {
                $stats['matched']++;
            }
        }
        $this->audit->record($context, 'payment.bank_sync', 'succeeded', $stats, 'bank_account', $account ?? 'fio');

        return $stats;
    }

    /**
     * Finance overview: what customers are expected to pay by transfer and what arrived recently.
     *
     * @return array{pending:list<array<string,mixed>>, lines:list<array<string,mixed>>, fio_configured:bool}
     */
    public function overview(int $limit = 50): array
    {
        $organizations = [];
        $pending = PaymentIntent::query()->where('provider', 'bank')->whereIn('state', [S::CREATED, S::PENDING_CUSTOMER])->orderByDesc('created_at')->limit($limit)->get()
            ->map(function (PaymentIntent $intent) use (&$organizations): array {
                $organizations[$intent->organization_id] ??= Organization::query()->find($intent->organization_id)?->name;

                return [
                    'id' => $intent->id, 'organization_id' => $intent->organization_id, 'organization' => $organizations[$intent->organization_id],
                    'purpose' => $intent->purpose, 'reference' => [$intent->reference_type, $intent->reference_id], 'amount' => $intent->amount(), 'currency' => $intent->currency,
                    'variable_symbol' => $intent->raw['instructions']['variable_symbol'] ?? null, 'message' => $intent->raw['instructions']['message'] ?? null, 'created_at' => $intent->created_at?->toIso8601String(),
                ];
            })->values()->all();
        $lines = BankStatementLine::query()->orderByDesc('booked_at')->orderByDesc('created_at')->limit($limit)->get()->map(fn (BankStatementLine $l): array => self::presentLine($l))->values()->all();

        return ['pending' => $pending, 'lines' => $lines, 'fio_configured' => (string) config('onhost.payments.bank.fio_token', '') !== ''];
    }

    /** @return array<string,mixed> */
    public static function presentLine(BankStatementLine $line): array
    {
        return [
            'id' => $line->id, 'external_id' => $line->external_id, 'account' => $line->account, 'amount' => Money::minor((int) $line->amount_minor, $line->currency), 'currency' => $line->currency,
            'variable_symbol' => $line->variable_symbol, 'counterparty' => $line->counterparty, 'message' => $line->message, 'booked_at' => $line->booked_at?->toIso8601String(),
            'state' => $line->state, 'payment_intent_id' => $line->matched_payment_intent_id,
        ];
    }

    private function pendingBySymbol(string $vs, string $currency): ?PaymentIntent
    {
        return PaymentIntent::query()->where('provider', 'bank')->whereIn('state', [S::CREATED, S::PENDING_CUSTOMER])->where('raw->instructions->variable_symbol', $vs)->where('currency', $currency)->first();
    }

    private function minor(int|float|string $amount): int
    {
        if (is_string($amount)) {
            $amount = (float) str_replace([' ', ','], ['', '.'], $amount);
        }

        return (int) round($amount * 100);
    }

    private function digits(?string $value): string
    {
        return ltrim(preg_replace('/\D/', '', (string) $value) ?? '', '0');
    }

    /** @param array<string,mixed> $tx */
    private function column(array $tx, int $id): ?string
    {
        $cell = $tx['column'.$id] ?? null;
        if (! is_array($cell) || ! isset($cell['value']) || $cell['value'] === '') {
            return null;
        }

        return (string) $cell['value'];
    }
}
