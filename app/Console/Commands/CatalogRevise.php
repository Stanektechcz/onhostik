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
 * versions, services and subscriptions are never touched; a priced option a revision withdraws is gone for new orders only. Runs as the system actor: shell access to the server is the gate,
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
            if ($id === null && CatalogRevisions::proposals() !== []) { // prepared for the owner, applied only by their id (TASK-0110)
                $this->line('Proposals waiting for the owner\'s decision (preview with the id): '.implode(', ', CatalogRevisions::proposals()));
            }

            return self::SUCCESS;
        }
        $plans = []; // target => [from, how many revisions change it]
        foreach (array_keys($pending) as $revision) {
            $rows = $revisions->preview($revision);
            $this->printPreview($revision, $rows);
            foreach ($rows as $row) {
                if ($row['kind'] === 'plan') {
                    $plans[$row['target']] = [$plans[$row['target']][0] ?? (int) $row['from'], ($plans[$row['target']][1] ?? 0) + 1];
                }
            }
        }
        // each revision above is counted from today's version; --apply reads each one just before it runs, so a later revision
        // on the same plan publishes on top of the version the earlier one makes (TASK-0027 review round 1)
        foreach ($plans as $target => [$from, $count]) {
            if ($count > 1) {
                $this->line("{$target} is changed by {$count} revisions: --apply publishes them one after another (".implode(' → ', array_map(fn (int $n) => "v{$n}", range($from, $from + $count))).')');
            }
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
                $priced = array_filter((array) ($row['definition']['plans'] ?? []), fn ($plan) => ! empty($plan['prices']));
                $this->line("  create product {$row['target']} ({$row['definition']['family']}, {$row['definition']['name']['cs']}): ".($priced === [] ? 'no plan and no price of its own' : 'with its plan(s) at the defined monthly prices ('.implode('; ', array_map(fn (string $plan, array $definition) => $plan.' '.json_encode($definition['prices']), array_keys($priced), $priced)).', a year = 12 months), state '.($row['definition']['state'] ?? 'draft')).'; four eyes in the console, the system actor here');

                continue;
            }
            if ($row['kind'] === 'priced') { // the untouched draft of an older proposal (H-R7)
                $this->line("  price product {$row['target']} (still a draft at zero prices): new plan version(s) at ".json_encode($row['prices']).' a month, then on sale');

                continue;
            }
            if ($row['kind'] === 'offer') {
                $this->line('  write the Penpot rules per web hosting tariff ('.count((array) ($row['rules']['plans'] ?? [])).' tariff(s), every one includes Penpot; a tariff added later follows the same default)');

                continue;
            }
            if ($row['kind'] === 'product') {
                $this->line("  product {$row['target']}: description → „{$row['description']['cs']}“");

                continue;
            }
            if ($row['kind'] === 'option') { // a price for new orders: what an order already bought stays with its service
                $this->line("  withdraw option {$row['target']} (grants {$row['entitlement']}, which the product's server cannot deliver): no longer offered to new orders");
                $this->line("    {$row['services']} service(s) that ordered it keep it and its price; four eyes in the console, the system actor here");

                continue;
            }
            $changes = array_merge(
                $row['drop'] === [] ? [] : ['− '.implode(', ', array_map(fn (string $key, string $bag) => "{$bag}.{$key}", array_keys($row['drop']), $row['drop']))],
                array_map(fn (string $key, array $set) => $key.' '.self::shown($set['from']).' → '.self::shown($set['to']), array_keys($row['set']), $row['set']),
            );
            $this->line("  {$row['target']} v{$row['from']} → v{$row['to']}: ".implode('; ', $changes));
            $this->line("    {$row['services']} service(s) and {$row['subscriptions']} subscription(s) keep v{$row['from']}; prices carried over unchanged");
            foreach ($row['promos'] as $promo) {
                $this->line("    promo price {$promo} carried over to v{$row['to']} unchanged");
            }
            foreach ($row['features'] as $line) {
                $this->warn("    a features line still says „{$line}“ — edit it in Nastavení systému → Tarify a verze");
            }
        }
    }

    /** A value as the preview names it: a word as it is, a number, a switch or nothing (a key the plan never had, TASK-0110) as JSON. */
    private static function shown(mixed $value): string
    {
        return is_string($value) ? $value : (string) json_encode($value);
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
                'option' => "  withdrew option {$row['target']}",
                'priced' => "  priced and put on sale {$row['target']}",
                'offer' => "  wrote {$row['target']}",
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
