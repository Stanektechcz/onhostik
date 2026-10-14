<?php

declare(strict_types=1);

namespace Onhost\Domain\Payments\Statements;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use Onhost\Platform\Errors\DomainError;

/**
 * J-R7 (option B, package M3): a statement file downloaded from the bank's internet banking (Česká spořitelna) read into the lines
 * BankStatementImporter::record() takes — nothing is ever written to the bank. Two formats:
 *
 *  * **camt.053** (ISO 20022 BankToCustomerStatement, any `camt.053.001.xx`): parsed without the network (`LIBXML_NONET`), without
 *    entity substitution and without a DTD — a file that declares a DOCTYPE or an entity is refused before parsing (XXE, billion
 *    laughs). Only booked credit entries (`CdtDbtInd` CRDT, status BOOK, not a reversal) become lines; a batch entry gives one line
 *    per transaction detail.
 *  * **ABO / GPC** (the Czech banks' fixed-width export): header `074`, transactions `075`; only accounting code `2` (a credit) is
 *    money that arrived. Amount in hellers at positions 49–60, variable symbol 62–71, posting date DDMMRR at 123–128 (value date
 *    92–97 when the line is shorter), counter-account 20–35, document number 36–48, name 98–117 (Windows-1250), currency 119–122.
 *
 * Every line gets a stable `external_id` starting `cs:` (the bank's own reference where the file has one), so a second upload of
 * the same file — or of an overlapping statement — records nothing new.
 *
 * Unverified until the owner's sample (J-R7 input 5): where Česká spořitelna puts the variable symbol in camt.053. Read, in this
 * order: the structured creditor reference (`Strd/CdtrRefInf/Ref`), the ČBA convention `/VS/<digits>` in `EndToEndId`, then
 * a marked VS in the remittance text and the additional transaction / entry information (see camtSymbol()); in GPC the fixed field,
 * then a marked VS in the 078/079 message. A line without one is recorded as `no_symbol` for manual assignment.
 */
final class BankStatementFile
{
    /** Largest file accepted (a month of a small business account is far below it). */
    public const MAX_BYTES = 2_097_152;

    /** Most lines of one file (credit lines and skipped lines together). */
    public const MAX_LINES = 5000;

    private const CURRENCIES = ['0203' => 'CZK', '0978' => 'EUR', '0840' => 'USD', '0826' => 'GBP', '0985' => 'PLN', '0348' => 'HUF', '0756' => 'CHF'];

    /**
     * @return array{format:string, account:?string, lines:list<array{external_id:string, amount:string, currency:string, variable_symbol:?string, counterparty:?string, message:?string, booked_at:string, account:?string}>, skipped:int}
     */
    public static function parse(string $content): array
    {
        if (strlen($content) > self::MAX_BYTES) {
            throw new DomainError('bank_statement_too_large', 'The statement file is larger than '.intdiv(self::MAX_BYTES, 1024).' kB; download a shorter period.', 422, ['field' => 'file']);
        }
        $content = str_starts_with($content, "\xEF\xBB\xBF") ? substr($content, 3) : $content;
        $head = ltrim(substr($content, 0, 512));
        if (str_starts_with($head, '<')) {
            return self::camt($content);
        }
        if (str_starts_with($head, '074')) {
            return self::gpc($content);
        }

        throw new DomainError('bank_statement_format', 'Unknown statement format: upload camt.053 (XML) or ABO/GPC from internet banking. CSV is not supported.', 422, ['field' => 'file']);
    }

    /** @return array{format:string, account:?string, lines:list<array<string,mixed>>, skipped:int} */
    private static function camt(string $xml): array
    {
        // the DOCTYPE test reads bytes, so only an ASCII-compatible encoding may reach the parser: a NUL byte (UTF-16/32) or a declared
        // encoding other than these could hide `<!DOCTYPE` from it (the parser's own result is checked once more below)
        $declared = preg_match('/^\s*<\?xml[^>]*\bencoding\s*=\s*["\']([A-Za-z0-9._-]+)["\']/', $xml, $m) === 1 ? strtolower($m[1]) : 'utf-8';
        if (str_contains($xml, "\0") || ! in_array($declared, ['utf-8', 'us-ascii', 'ascii', 'windows-1250', 'cp1250', 'iso-8859-2'], true)
            || preg_match('/<!DOCTYPE|<!ENTITY/i', $xml) === 1) {
            throw new DomainError('bank_statement_unsafe', 'The XML statement declares a DOCTYPE, an entity or an unexpected encoding; a bank statement has none of them. Refused.', 422, ['field' => 'file']);
        }
        $dom = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        try {
            $loaded = $dom->loadXML($xml, LIBXML_NONET);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        if (! $loaded || $dom->doctype !== null || $dom->documentElement === null) {
            throw new DomainError('bank_statement_xml', 'The XML statement could not be read.', 422, ['field' => 'file']);
        }
        $xp = new DOMXPath($dom);
        $statements = $xp->query('/*[local-name()="Document"]/*[local-name()="BkToCstmrStmt"]/*[local-name()="Stmt"]');
        if ($statements === false || $statements->length === 0) {
            throw new DomainError('bank_statement_format', 'The XML file is not a camt.053 bank statement (Document/BkToCstmrStmt/Stmt).', 422, ['field' => 'file']);
        }
        $lines = [];
        $skipped = 0;
        $account = null;
        foreach ($statements as $statement) {
            $own = self::text($xp, 'L:Acct/L:Id/L:IBAN', $statement) ?? self::text($xp, 'L:Acct/L:Id/L:Othr/L:Id', $statement);
            $account ??= $own;
            foreach (self::nodes($xp, 'L:Ntry', $statement) as $entry) {
                $status = self::text($xp, 'L:Sts/L:Cd', $entry) ?? self::text($xp, 'L:Sts', $entry);
                $credit = self::text($xp, 'L:CdtDbtInd', $entry) === 'CRDT';
                $reversal = strtolower((string) self::text($xp, 'L:RvslInd', $entry)) === 'true';
                $details = self::nodes($xp, 'L:NtryDtls/L:TxDtls', $entry);
                if (! $credit || $reversal || strtoupper((string) $status) !== 'BOOK') {
                    $skipped += max(1, count($details));
                    self::guardCount(count($lines) + $skipped);

                    continue;
                }
                $date = substr((string) (self::text($xp, 'L:BookgDt/L:Dt', $entry) ?? self::text($xp, 'L:BookgDt/L:DtTm', $entry) ?? self::text($xp, 'L:ValDt/L:Dt', $entry) ?? ''), 0, 10);
                $entryRef = self::text($xp, 'L:AcctSvcrRef', $entry) ?? self::text($xp, 'L:NtryRef', $entry);
                foreach ($details === [] ? [null] : $details as $i => $tx) {
                    $amountNode = $tx === null ? null : (self::node($xp, 'L:Amt', $tx) ?? self::node($xp, 'L:AmtDtls/L:TxAmt/L:Amt', $tx));
                    $amountNode = (count($details) <= 1 || $amountNode !== null) ? ($amountNode ?? self::node($xp, 'L:Amt', $entry)) : null;
                    if ($amountNode === null) {
                        $skipped++;

                        continue;
                    }
                    $amount = trim($amountNode->textContent);
                    $currency = strtoupper($amountNode instanceof DOMElement ? $amountNode->getAttribute('Ccy') : '') ?: 'CZK';
                    $txRef = $tx === null ? null : self::text($xp, 'L:Refs/L:AcctSvcrRef', $tx);
                    $ref = $txRef ?? ($entryRef !== null ? $entryRef.(count($details) > 1 ? ':'.$i : '') : null);
                    $line = [
                        'amount' => $amount, 'currency' => $currency, 'booked_at' => $date,
                        'variable_symbol' => self::camtSymbol($xp, $tx, $entry, count($details) <= 1),
                        'counterparty' => $tx === null ? null : self::camtCounterparty($xp, $tx),
                        'message' => $tx === null ? null : self::camtMessage($xp, $tx),
                        'account' => $own,
                    ];
                    $line['external_id'] = $ref !== null && $ref !== '' ? 'cs:camt:'.$own.':'.$ref : 'cs:camt:h:'.self::digest($line, $lines, true);
                    $lines[] = $line;
                    self::guardCount(count($lines) + $skipped);
                }
            }
        }

        return ['format' => 'camt.053', 'account' => $account, 'lines' => self::valid($lines), 'skipped' => $skipped];
    }

    /** @return array{format:string, account:?string, lines:list<array<string,mixed>>, skipped:int} */
    private static function gpc(string $raw): array
    {
        $account = null;
        $lines = [];
        $skipped = 0;
        $last = null;
        foreach (preg_split('/\r\n|\n|\r/', $raw) ?: [] as $row) {
            $type = substr($row, 0, 3);
            if ($type === '074') {
                $account = self::accountNumber(substr($row, 3, 16));

                continue;
            }
            if (($type === '078' || $type === '079') && $last !== null) { // the transaction's message fields (AV) follow it
                $text = trim(self::cp1250(substr($row, 3)));
                if ($text !== '') {
                    $lines[$last]['message'] = trim(($lines[$last]['message'] ?? '').' '.$text);
                }

                continue;
            }
            if ($type !== '075') {
                continue;
            }
            $last = null;
            self::guardCount(count($lines) + $skipped + 1);
            if (strlen($row) < 97 || preg_match('/^\d{12}$/', substr($row, 48, 12)) !== 1) {
                throw new DomainError('bank_statement_gpc', 'A transaction line (075) of the GPC file is malformed; download the statement again.', 422, ['field' => 'file']);
            }
            if (substr($row, 60, 1) !== '2') { // 1 debit, 4 / 5 reversals: not money that arrived
                $skipped++;

                continue;
            }
            $hellers = (int) substr($row, 48, 12);
            $posted = self::ddmmrr(substr($row, 122, 6)) ?? self::ddmmrr(substr($row, 91, 6));
            if ($posted === null || $hellers <= 0) {
                throw new DomainError('bank_statement_gpc', 'A transaction line (075) of the GPC file has no valid date or amount.', 422, ['field' => 'file']);
            }
            $counterAccount = self::accountNumber(substr($row, 19, 16));
            $name = trim(self::cp1250(substr($row, 97, 20)));
            $document = ltrim(trim(substr($row, 35, 13)), '0');
            $vs = ltrim(trim(substr($row, 61, 10)), '0');
            $line = [
                'amount' => intdiv($hellers, 100).'.'.str_pad((string) ($hellers % 100), 2, '0', STR_PAD_LEFT),
                'currency' => self::CURRENCIES[substr($row, 118, 4)] ?? 'CZK', 'booked_at' => $posted,
                'variable_symbol' => $vs !== '' ? $vs : null,
                'counterparty' => trim($name.($counterAccount !== null ? ' ('.$counterAccount.')' : '')) ?: null,
                'message' => null, 'account' => $account,
            ];
            $line['external_id'] = $document !== '' ? 'cs:gpc:'.($account ?? '-').':'.$document.':'.$posted.':'.$hellers : 'cs:gpc:h:'.self::digest($line, $lines);
            $lines[] = $line;
            $last = array_key_last($lines);
        }
        foreach ($lines as $i => $line) { // the fixed field was empty or zero: a symbol the sender marked in the 078/079 message
            if ($line['variable_symbol'] === null && ($line['message'] ?? '') !== '') {
                $found = self::markedSymbols((string) $line['message']);
                $lines[$i]['variable_symbol'] = count($found) === 1 ? $found[0] : null;
            }
        }
        if ($account === null && $lines === [] && $skipped === 0) {
            throw new DomainError('bank_statement_gpc', 'The GPC file has no statement header (074) or transactions (075).', 422, ['field' => 'file']);
        }

        return ['format' => 'gpc', 'account' => $account, 'lines' => self::valid($lines), 'skipped' => $skipped];
    }

    /**
     * The variable symbol, most trustworthy source first; the first source that names one wins and a free-text number never
     * overrides a structured one:
     *
     *  1. every structured creditor reference (`Strd/CdtrRefInf/Ref`): bare digits, or a marked VS (an `RF…` ISO reference is no VS);
     *  2. the ČBA convention in `EndToEndId` (`/VS/<digits>/SS/…`), a marked VS only. Bare digits there are NOT taken: the field is the
     *     payer's own free reference (invoice number, order id), and a wrong guess would credit a stranger's account;
     *  3. the unstructured remittance text (`Ustrd`, `Strd/AddtlRmtInf`);
     *  4. `AddtlTxInf`, then — for an entry with at most one transaction (a batch's shared note proves nothing about one payment) —
     *     `AddtlNtryInf`. An entry with no `TxDtls` at all is read from its own `AddtlNtryInf`.
     *
     * A source that names two different symbols is ambiguous and ends the search: the line goes to manual assignment.
     */
    private static function camtSymbol(DOMXPath $xp, ?DOMNode $tx, DOMNode $entry, bool $entryInfoUsable): ?string
    {
        $sources = [];
        if ($tx !== null) {
            $refs = array_map(fn (DOMNode $n) => trim($n->textContent), self::nodes($xp, 'L:RmtInf/L:Strd/L:CdtrRefInf/L:Ref', $tx));
            $sources[] = ['ref', array_values(array_filter($refs, fn (string $r) => $r !== ''))];
            $sources[] = ['marked', [self::text($xp, 'L:Refs/L:EndToEndId', $tx) ?? '']];
            $sources[] = ['marked', [implode(' ', array_map(fn (DOMNode $n) => $n->textContent, [...self::nodes($xp, 'L:RmtInf/L:Ustrd', $tx), ...self::nodes($xp, 'L:RmtInf/L:Strd/L:AddtlRmtInf', $tx)]))]];
            $sources[] = ['marked', [self::text($xp, 'L:AddtlTxInf', $tx) ?? '']];
        }
        if ($entryInfoUsable) {
            $sources[] = ['marked', [self::text($xp, 'L:AddtlNtryInf', $entry) ?? '']];
        }
        foreach ($sources as [$kind, $values]) {
            $found = [];
            foreach ($values as $value) {
                $symbol = $kind === 'ref' ? self::referenceSymbol($value) : null;
                $found = [...$found, ...($symbol !== null ? [$symbol] : self::markedSymbols($value))];
            }
            $found = array_values(array_unique($found));
            if (count($found) > 1) {
                return null;
            }
            if (count($found) === 1) {
                return $found[0];
            }
        }

        return null;
    }

    /** A structured creditor reference: bare digits (leading zeros dropped) or a marked VS; null for an RF reference or zeros. */
    private static function referenceSymbol(string $ref): ?string
    {
        if (preg_match('/^(?:VS[:\s.\/=]*)?(\d{1,10})$/i', $ref, $m) === 1) {
            return self::normalizeSymbol($m[1]);
        }

        return self::markedSymbols($ref)[0] ?? null;
    }

    /**
     * Every number explicitly marked as a variable symbol in a text: `VS 1`, `VS:1`, `VS=1`, `VS.1`, `VS/1`, `/VS/1/SS/2`, `v.s. 1`,
     * `var. symbol 1`, `variabilní symbol 1` (also without diacritics). At most 10 digits, not glued to letters or longer digit runs.
     *
     * @return list<string>
     */
    public static function markedSymbols(string $text): array
    {
        $marker = '(?:variabiln[ií]\s+symbol|var\.?\s*symbol|v\.\s?s\.|vs)';
        if (preg_match_all('/(?<![\p{L}\p{N}])'.$marker.'\s*[:=.\/#-]?\s*(\d{1,10})(?![\p{L}\p{N}])/iu', $text, $all) < 1) {
            return [];
        }
        $symbols = [];
        foreach ($all[1] as $digits) {
            $symbol = self::normalizeSymbol($digits);
            if ($symbol !== null) {
                $symbols[] = $symbol;
            }
        }

        return array_values(array_unique($symbols));
    }

    /** Leading zeros dropped; a symbol of only zeros is "none". */
    private static function normalizeSymbol(string $digits): ?string
    {
        $symbol = ltrim($digits, '0');

        return $symbol !== '' ? $symbol : null;
    }

    private static function camtCounterparty(DOMXPath $xp, DOMNode $tx): ?string
    {
        $name = self::text($xp, 'L:RltdPties/L:Dbtr/L:Nm', $tx) ?? self::text($xp, 'L:RltdPties/L:Dbtr/L:Pty/L:Nm', $tx);
        $account = self::text($xp, 'L:RltdPties/L:DbtrAcct/L:Id/L:IBAN', $tx) ?? self::text($xp, 'L:RltdPties/L:DbtrAcct/L:Id/L:Othr/L:Id', $tx);
        $text = trim(($name ?? '').($account !== null ? ' ('.$account.')' : ''));

        return $text !== '' ? $text : null;
    }

    private static function camtMessage(DOMXPath $xp, DOMNode $tx): ?string
    {
        $text = trim(implode(' ', array_map(fn (DOMNode $n) => trim($n->textContent), self::nodes($xp, 'L:RmtInf/L:Ustrd', $tx))));

        return $text !== '' ? $text : null;
    }

    /**
     * Lines whose amount the importer can take (a positive decimal); anything else in a file the bank produced is a reason to stop.
     *
     * @param  list<array<string,mixed>>  $lines
     * @return list<array{external_id:string, amount:string, currency:string, variable_symbol:?string, counterparty:?string, message:?string, booked_at:string, account:?string}>
     */
    private static function valid(array $lines): array
    {
        foreach ($lines as $line) {
            if (preg_match('/^\d{1,13}(\.\d{1,2})?$/', (string) $line['amount']) !== 1 || (float) $line['amount'] <= 0
                || preg_match('/^[A-Z]{3}$/', (string) $line['currency']) !== 1 || preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $line['booked_at']) !== 1) {
                throw new DomainError('bank_statement_line', 'A credit line of the statement has no valid amount, currency or date; nothing was recorded.', 422, ['field' => 'file']);
            }
        }

        /** @var list<array{external_id:string, amount:string, currency:string, variable_symbol:?string, counterparty:?string, message:?string, booked_at:string, account:?string}> $lines */
        return $lines;
    }

    private static function guardCount(int $count): void
    {
        if ($count > self::MAX_LINES) {
            throw new DomainError('bank_statement_too_long', 'The statement has more than '.self::MAX_LINES.' lines; download a shorter period.', 422, ['field' => 'file']);
        }
    }

    /**
     * A line without a bank reference: its content plus how many identical lines came before it in this file (a second upload of
     * the same file gives the same ids).
     *
     * @param  array<string,mixed>  $line
     * @param  list<array<string,mixed>>  $before
     */
    private static function digest(array $line, array $before, bool $camt = false): string
    {
        $key = self::digestKey($line, $camt);
        $same = count(array_filter($before, fn (array $b) => self::digestKey($b, $camt) === $key));

        return substr(hash('sha256', $key.'#'.$same), 0, 40);
    }

    /**
     * What identifies a line without a reference (the message is left out: a GPC message line arrives after its transaction).
     *
     * @param  array<string,mixed>  $line
     */
    private static function digestKey(array $line, bool $camt = false): string
    {
        // camt: the raw remittance text stands in for the extracted variable symbol, so a better extraction never changes the id
        $symbolOrText = $camt ? ($line['message'] ?? '') : ($line['variable_symbol'] ?? '');

        return implode('|', [$line['account'] ?? '', $line['booked_at'], $line['amount'], $line['currency'], $symbolOrText, $line['counterparty'] ?? '']);
    }

    private static function ddmmrr(string $value): ?string
    {
        if (preg_match('/^(\d{2})(\d{2})(\d{2})$/', $value, $m) !== 1 || ! checkdate((int) $m[2], (int) $m[1], 2000 + (int) $m[3])) {
            return null;
        }

        return sprintf('20%s-%s-%s', $m[3], $m[2], $m[1]);
    }

    /** A 16-digit GPC account field as `prefix-number`, or null when it is all zeros. */
    private static function accountNumber(string $field): ?string
    {
        $digits = preg_replace('/\D/', '', $field) ?? '';
        if (trim($digits, '0') === '') {
            return null;
        }
        $digits = str_pad($digits, 16, '0', STR_PAD_LEFT);
        $prefix = ltrim(substr($digits, 0, 6), '0');
        $number = ltrim(substr($digits, 6), '0');

        return ($prefix !== '' ? $prefix.'-' : '').$number;
    }

    private static function cp1250(string $bytes): string
    {
        if (mb_check_encoding($bytes, 'UTF-8')) {
            return $bytes;
        }
        $converted = iconv('CP1250', 'UTF-8//IGNORE', $bytes);

        return $converted === false ? '' : $converted;
    }

    private static function text(DOMXPath $xp, string $path, DOMNode $context): ?string
    {
        $node = self::node($xp, $path, $context);
        $value = $node === null ? '' : trim($node->textContent);

        return $value !== '' ? $value : null;
    }

    private static function node(DOMXPath $xp, string $path, DOMNode $context): ?DOMNode
    {
        $list = $xp->query(self::local($path), $context);

        return $list === false || $list->length === 0 ? null : $list->item(0);
    }

    /** @return list<DOMNode> */
    private static function nodes(DOMXPath $xp, string $path, DOMNode $context): array
    {
        $list = $xp->query(self::local($path), $context);

        return $list === false ? [] : iterator_to_array($list, false);
    }

    /** `L:Name` steps → namespace-agnostic `*[local-name()="Name"]` (camt.053.001.02 … .08 differ only in the namespace). */
    private static function local(string $path): string
    {
        return (string) preg_replace('/L:([A-Za-z0-9]+)/', '*[local-name()="$1"]', $path);
    }
}
