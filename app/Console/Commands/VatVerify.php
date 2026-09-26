<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Onhost\Domain\Invoicing\Models\Invoice;
use Onhost\Domain\Invoicing\Models\InvoiceLine;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Tax\VatHealth;
use Onhost\Domain\Tax\VatNumber;
use Onhost\Domain\Tax\VatNumberChecks;
use Onhost\Domain\Tax\VatStanding;
use Onhost\Platform\Money\Money;
use Throwable;

/**
 * The operator's way to bring existing customers under the VIES check (TASK-0031, D31.3d). A dry run by default: it makes
 * no HTTP call and writes nothing. Part 1 lists the organizations whose standing a check would decide — an EU business
 * number never checked, a VIES answer too old to count, a row from before the check (legacy `valid`/`payer`) — with how
 * they are charged today, and those whose valid number VIES registers to another trader name (`name_mismatch`, review round 1:
 * listed for finance, never re-asked — VIES would say the same), and partners whose number — a Czech DIČ included — no check
 * has spoken about (`partner`, review round 2: their self-billing VAT depends on it). Part 2, for the accountant, lists the documents already issued with VAT to EU business customers
 * of another member state who had given a VAT ID; they are never changed (corrections are the accountant's decision and
 * new documents). `--apply` asks VIES about part 1 and records the answers through the bus.
 */
final class VatVerify extends Command
{
    protected $signature = 'onhost:vat:verify {--apply : ask VIES and record the answers (without it: dry run, no HTTP call)} {--limit=200 : at most this many organizations}
        {--organization=* : only these organization ids} {--country=* : only these countries} {--pause-ms=500 : pause between two VIES calls}
        {--since= : documents issued on or after this date (Y-m-d)} {--csv= : also write the document list to storage/app/private/reports/<name>}';

    protected $description = 'List (dry run) or check in VIES (--apply) the VAT numbers of existing organizations, and list past VAT invoices to EU business customers';

    public function handle(VatNumberChecks $checks): int
    {
        $apply = (bool) $this->option('apply');
        if ($apply && ! (bool) config('onhost.vies.enabled', false)) {
            $this->error('ONHOST_VIES_ENABLED=false — nothing was checked. Switch VIES on (and set ONHOST_VIES_REQUESTER_VAT_ID) first.');

            return self::FAILURE;
        }
        $candidates = $this->candidates();
        $this->table(['organization', 'name', 'country', 'number', 'stored', 'effective', 'group', 'checked_at', 'charged today', $apply ? 'result' : '--apply would'],
            array_map(fn (array $c) => $c['row'], $candidates));

        if ($apply) {
            $counts = ['valid' => 0, 'invalid' => 0, 'unknown' => 0, 'skipped' => 0];
            $pause = max(0, (int) $this->option('pause-ms'));
            foreach ($candidates as $i => $candidate) {
                if (in_array($candidate['group'], ['name_mismatch', 'supplier_identity'], true)) {
                    $counts['skipped']++; // a person looks at who holds the number; asking VIES again tells nothing new

                    continue;
                }
                if ($i > 0 && $pause > 0) {
                    usleep($pause * 1000);
                }
                $counts[VatNumberChecks::tally($checks->check($candidate['organization'], 'operator', (int) config('onhost.vies.timeout_seconds', 8)))]++;
            }
            $this->line(sprintf('valid %d · invalid %d · unknown %d · skipped %d', $counts['valid'], $counts['invalid'], $counts['unknown'], $counts['skipped']));
        }

        $documents = $this->documents();
        $this->line('');
        $this->line('Documents already issued with VAT to EU business customers who gave a VAT ID (for the accountant; never changed here):');
        $this->table(['number', 'issued_at', 'organization', 'country', 'VAT ID', 'tax'], $documents);
        $csv = $this->option('csv');
        if (is_string($csv) && $csv !== '') {
            $path = 'reports/'.basename($csv);
            Storage::disk('local')->put($path, $this->csv($documents));
            $this->line('written: storage/app/private/'.$path);
        }
        $this->line($apply ? sprintf('%d organizations checked · %d documents listed', count($candidates), count($documents))
            : sprintf('dry run — no VIES call, nothing written · %d organizations would be checked · %d documents listed; add --apply to check them', count($candidates), count($documents)));

        return self::SUCCESS;
    }

    /** @return list<array{organization:Organization, group:string, row:list<string>}> */
    private function candidates(): array
    {
        $query = VatHealth::open()->where(fn ($q) => $q->where(fn ($v) => $v->whereNotNull('vat_id')->where('vat_id', '!=', ''))->orWhere(fn ($d) => $d->whereNotNull('dic')->where('dic', '!=', '')));
        $only = array_values(array_filter(array_map('strval', (array) $this->option('organization'))));
        $countries = array_values(array_filter(array_map(fn ($c) => strtoupper((string) $c), (array) $this->option('country'))));
        if ($only !== []) {
            $query->whereIn('id', $only);
        }
        if ($countries !== []) {
            $query->whereIn('country', $countries);
        }
        $limit = max(1, (int) $this->option('limit'));
        $apply = (bool) $this->option('apply');
        $eu = VatNumber::euMembers();
        $out = [];
        foreach ($query->lazyById(200) as $organization) {
            $subject = VatStanding::subject($organization);
            if ($subject === null || ! $subject->isEuPrefixed() || ! in_array(strtoupper((string) $organization->country), $eu, true)) {
                continue; // VIES knows only EU numbers; nobody outside is ever called invalid
            }
            $group = self::group($organization);
            if ($group === null) {
                continue;
            }
            $standing = VatStanding::standing($organization);
            $out[] = ['organization' => $organization, 'group' => $group, 'row' => [
                $organization->id, mb_substr((string) $organization->name, 0, 40), (string) $organization->country, $subject->value, (string) $organization->vat_status,
                $standing['status'].' ('.$standing['reason'].')', $group, $organization->vat_checked_at?->toDateString() ?? '—', self::treatment($organization),
                $apply ? '' : ($group === 'name_mismatch' ? 'nothing (finance: VIES names another trader)' : ($group === 'supplier_identity' ? 'nothing (finance: confirm the supplier with the override)' : ($subject->isWellFormed() ? 'check in VIES' : 'record invalid (malformed, no call)'))),
            ]];
            if (count($out) >= $limit) {
                break;
            }
        }

        return $out;
    }

    /**
     * Why a check would matter: a row from before the check, an answer too old to count, a number never asked about — and
     * a partner's number no check has spoken about (review round 2): a Czech DIČ is never asked about at the quote, so an
     * existing Czech partner that is a VAT payer would otherwise get self-billing documents without VAT for ever.
     */
    private static function group(Organization $organization): ?string
    {
        if (VatStanding::isLegacy($organization)) {
            return 'legacy';
        }
        if (VatStanding::standing($organization)['reason'] === 'stale') {
            return 'stale';
        }

        if (VatStanding::needsCheck($organization)) {
            return 'unchecked';
        }
        if (VatStanding::payerUnverified($organization) && VatHealth::isPartner($organization)) {
            return 'partner';
        }

        // the name rule as the tax decision reads it (VatStanding::verdict, review round 3), not a second copy of it here
        if (VatStanding::snapshot($organization)['name_mismatch']) {
            return 'name_mismatch';
        }

        // a partner VIES confirmed whose VAT is not paid out until finance confirms the supplier (closing review): VIES has
        // nothing more to say, the list tells the operator whom finance still has to look at
        return VatHealth::isPartner($organization) && in_array(VatStanding::payerStanding($organization)['reason'], ['identity_unconfirmed', 'identity_changed'], true)
            ? 'supplier_identity' : null;
    }

    /** How the organization is charged today, in the words of the tax decision. */
    private static function treatment(Organization $organization): string
    {
        $country = strtoupper((string) $organization->country);
        if ($country === VatNumber::supplierCountry()) {
            return VatStanding::isVatPayer($organization) ? 'domestic · self-billing VAT payer' : 'domestic';
        }
        if (! in_array($country, VatNumber::euMembers(), true)) {
            return 'outside EU';
        }

        return $organization->customer_class === 'b2b' && VatStanding::effectiveStatus($organization) === VatStanding::VALID ? 'reverse charge' : 'destination VAT';
    }

    /**
     * Invoices, statements and receipts (not drafts) whose buyer — as the document froze it — was a business in another EU
     * state with a VAT ID or DIČ, and that carried standard-rated VAT on at least one line. Read-only.
     *
     * @return list<list<string>>
     */
    private function documents(): array
    {
        $query = Invoice::query()->whereIn('type', ['invoice', 'statement', 'receipt'])->where('state', '!=', 'DRAFT')->whereNotNull('issued_at')->where('tax_minor', '>', 0);
        $since = $this->since();
        if ($since !== null) {
            $query->where('issued_at', '>=', $since);
        }
        $rows = [];
        foreach ($query->lazyById(500) as $invoice) {
            $buyer = $invoice->getAttribute('buyer'); // the buyer as the document froze it (array cast; null on very old rows)
            $buyer = is_array($buyer) ? $buyer : [];
            $lines = InvoiceLine::query()->where('invoice_id', $invoice->id)->get(['tax_category', 'tax_minor'])->map(fn (InvoiceLine $l) => ['tax_category' => (string) $l->tax_category, 'tax' => (int) $l->tax_minor]);
            if (! VatStanding::invoiceNeedsReview($buyer, $lines)) { // the same predicate that flags a new document (InvoiceService::draft)
                continue;
            }
            $vatId = trim((string) ($buyer['vat_id'] ?? '')) !== '' ? trim((string) $buyer['vat_id']) : trim((string) ($buyer['dic'] ?? ''));
            $country = strtoupper((string) ($buyer['country'] ?? ''));
            $rows[] = [(string) $invoice->number, CarbonImmutable::parse($invoice->issued_at)->toDateString(), (string) ($buyer['name'] ?? $invoice->organization_id), $country, $vatId,
                Money::minor((int) $invoice->tax_minor, (string) $invoice->currency)->format('cs')];
        }
        usort($rows, fn (array $a, array $b) => [$a[1], $a[0]] <=> [$b[1], $b[0]]);

        return $rows;
    }

    private function since(): ?CarbonImmutable
    {
        $since = $this->option('since');
        if (! is_string($since) || $since === '') {
            return null;
        }
        try {
            return CarbonImmutable::createFromFormat('!Y-m-d', $since) ?: null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * A cell a spreadsheet would run as a formula — a buyer name or a VAT ID the customer typed, starting with = + - or @ — is
     * written as text, with a leading apostrophe: the rule of Staff\CustomerController::csvCell (review round 1).
     */
    private static function csvCell(string $value): string
    {
        return $value !== '' && preg_match('/^[\s]*[=+\-@]/', $value) === 1 ? "'".$value : $value;
    }

    /** @param list<list<string>> $rows */
    private function csv(array $rows): string
    {
        $handle = fopen('php://temp', 'r+');
        if ($handle === false) {
            return '';
        }
        fputcsv($handle, ['number', 'issued_at', 'organization', 'country', 'vat_id', 'tax'], ',', '"', '');
        foreach ($rows as $row) {
            fputcsv($handle, array_map(self::csvCell(...), $row), ',', '"', '');
        }
        rewind($handle);
        $csv = (string) stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }
}
