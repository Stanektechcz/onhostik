<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Onhost\Domain\Orders\LegalDocuments;
use Onhost\Domain\Orders\Models\ConsentDocument;
use Onhost\Platform\Commands\CommandContext;
use Throwable;

/**
 * The owner's step that puts a prepared version of the legal documents in force (TASK-0142, owner decision I-R4/4A: the 2026-09
 * texts stay until an attorney confirms the new ones). Without `--apply` it lists the drafts, their text hashes and what stands in
 * the way; with it each draft becomes the published version from `--effective-from` (never sooner than the notice owed to customers
 * who accepted the version in force), the older version is closed on that day and every document is audited. Runs as the system
 * actor: shell access to the server is the gate, the texts are reviewed as files (docs/legal/LEGAL_REVIEW_2026-10.md).
 */
final class LegalPublish extends Command
{
    protected $signature = 'onhost:legal:publish {version : the prepared version, e.g. 2026-10} {--effective-from= : the day the version takes effect (YYYY-MM-DD, Europe/Prague)} {--approved-by= : who confirmed the texts, e.g. "Mgr. X, advokát, 2026-11-02"} {--apply : publish (without it: a dry run)} {--yes : do not ask for confirmation}';

    protected $description = 'Preview (default) or publish a prepared version of the legal documents (consent_documents drafts)';

    public function handle(LegalDocuments $legal): int
    {
        $version = (string) $this->argument('version');
        $drafts = $legal->drafts($version);
        if ($drafts === []) {
            $this->info("No draft of version {$version}: nothing to publish.");

            return self::SUCCESS;
        }
        try {
            $effective = $this->option('effective-from') !== null
                ? CarbonImmutable::createFromFormat('!Y-m-d', (string) $this->option('effective-from'), (string) config('onhost.billing.timezone', 'Europe/Prague'))
                : null;
        } catch (Throwable) {
            $effective = null;
        }
        if (! $effective instanceof CarbonImmutable) {
            $this->error('Give the day the version takes effect: --effective-from=YYYY-MM-DD.');

            return self::FAILURE;
        }
        foreach ($drafts as $draft) {
            $current = ConsentDocument::current((string) $draft->key);
            $this->line(sprintf('  %-18s %s → %s  text sha256 %s', $draft->key, $current->version ?? '(new document)', $version, LegalDocuments::hash((string) $draft->key, $version) ?? 'MISSING'));
        }
        $blockers = $legal->blockers($version, $effective, (string) $this->option('approved-by'));
        foreach ($blockers as $blocker) {
            $this->warn("  not ready: {$blocker}");
        }
        if (! $this->option('apply')) {
            $this->info('Dry run: nothing was published. Customers must be told about the change before it takes effect (e-mail and panel); then run again with --apply.');

            return $blockers === [] ? self::SUCCESS : self::FAILURE;
        }
        if ($blockers !== []) {
            $this->error('Not published: resolve the points above first.');

            return self::FAILURE;
        }
        if (! $this->option('yes') && ! $this->confirm("Publish version {$version} in force from {$effective->toDateString()}?", false)) {
            $this->warn('Nothing was published.');

            return self::FAILURE;
        }
        foreach ($legal->publish($version, $effective->utc(), (string) $this->option('approved-by'), CommandContext::system('cli:legal:publish')) as $row) {
            $this->info("  {$row['key']} {$row['version']} in force from {$row['effective_from']}".($row['closed'] === [] ? '' : ' (closes '.implode(', ', $row['closed']).')'));
        }

        return self::SUCCESS;
    }
}
