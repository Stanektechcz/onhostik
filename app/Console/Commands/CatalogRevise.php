<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Onhost\Domain\Catalog\CatalogRevisions;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;

/**
 * Applies the catalogue revisions defined in code (`Onhost\Domain\Catalog\CatalogRevisions`) as new plan versions. Without
 * `--apply` it only shows what would be published, who keeps the current version and what would not carry over; with it, each
 * plan is one audited `CatalogCommand plan.publish` (prices unchanged) and finance hears about every new version. Existing
 * versions, services and subscriptions are never touched. Runs as the system actor: shell access to the server is the gate,
 * and the content of a revision is reviewed as code (docs/runbooks/pricing.md, "Catalogue revisions").
 */
final class CatalogRevise extends Command
{
    protected $signature = 'onhost:catalog:revise {revision? : one revision id (default: every revision with something pending)} {--apply : publish the new versions (without it: a dry run)} {--yes : do not ask for confirmation}';

    protected $description = 'Preview (default) or publish the catalogue revisions defined in code as new plan versions';

    public function handle(CatalogRevisions $revisions): int
    {
        $id = $this->argument('revision') !== null ? (string) $this->argument('revision') : null;
        try {
            $pending = $revisions->pending($id);
        } catch (DomainError $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
        if ($pending === []) {
            $this->info('Nothing pending: every catalogue revision is applied.');

            return self::SUCCESS;
        }
        foreach (array_keys($pending) as $revision) {
            $this->printPreview($revision, $revisions->preview($revision));
        }
        if (! $this->option('apply')) {
            $this->info('Dry run: nothing was published. Run again with --apply to publish the new versions.');

            return self::SUCCESS;
        }
        if (! $this->option('yes') && ! $this->confirm('Publish these new plan versions now?', false)) {
            $this->warn('Nothing was published.');

            return self::FAILURE;
        }

        return $this->printApplied($revisions->apply($id, CommandContext::system('cli:catalog:revise')));
    }

    /** @param list<array<string,mixed>> $rows */
    private function printPreview(string $revision, array $rows): void
    {
        $this->line("Revision {$revision}: ".(string) CatalogRevisions::REVISIONS[$revision]['reason']);
        foreach ($rows as $row) {
            if ($row['kind'] === 'create') { // a product the code defines and this catalogue does not have yet
                $this->line("  create product {$row['target']} ({$row['definition']['family']}, {$row['definition']['name']['cs']}): no plan and no price of its own; four eyes in the console, the system actor here");

                continue;
            }
            if ($row['kind'] === 'product') {
                $this->line("  product {$row['target']}: description → „{$row['description']['cs']}“");

                continue;
            }
            $changes = array_merge(
                $row['drop'] === [] ? [] : ['− '.implode(', ', array_map(fn (string $key, string $bag) => "{$bag}.{$key}", array_keys($row['drop']), $row['drop']))],
                array_map(fn (string $key, array $set) => "{$key} {$set['from']} → {$set['to']}", array_keys($row['set']), $row['set']),
            );
            $this->line("  {$row['target']} v{$row['from']} → v{$row['to']}: ".implode('; ', $changes));
            $this->line("    {$row['services']} service(s) and {$row['subscriptions']} subscription(s) keep v{$row['from']}; prices carried over unchanged");
            foreach ($row['promos'] as $promo) {
                $this->warn("    promo price {$promo} ends for new orders (not carried into a new version) — set it again on v{$row['to']} if it should stay");
            }
            foreach ($row['features'] as $line) {
                $this->warn("    a features line still says „{$line}“ — edit it in Nastavení systému → Tarify a verze");
            }
        }
    }

    /** @param list<array<string,mixed>> $done */
    private function printApplied(array $done): int
    {
        $failed = 0;
        foreach ($done as $row) {
            if (isset($row['error'])) {
                $failed++;
                $this->error("  {$row['target']}: {$row['error']}");

                continue;
            }
            $this->line(match ($row['kind']) {
                'plan' => "  published {$row['target']} v{$row['from']} → v{$row['to']}",
                'create' => "  created product {$row['target']}",
                default => "  described product {$row['target']}",
            });
        }
        if ($failed > 0) {
            $this->error("{$failed} change(s) refused; the others are published. Fix the cause and run the command again: it continues where it stopped.");

            return self::FAILURE;
        }
        $this->info('Published. Check `php artisan onhost:doctor`: "every catalogue revision is applied".');

        return self::SUCCESS;
    }
}
