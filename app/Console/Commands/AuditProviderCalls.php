<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Onhost\Domain\Provisioning\Audit\ProviderCallsOwnershipAudit;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

/**
 * Did anybody use the TASK-0005 hole before it was closed? (TASK-0020, runbook docs/runbooks/provider-calls-audit.md)
 *
 * Reads the logged ISPConfig calls and the service actions that named a record by id, and reports whose each record
 * was. Read-only: it writes no row and calls no panel — its only output is the report, on stdout or as one file on
 * the private local disk (never a link to it). Exit 1 when a CRITICAL or HIGH row was found, 2 for bad options.
 */
final class AuditProviderCalls extends Command
{
    private const MAX_DAYS = 3650;

    protected $signature = 'onhost:audit:provider-calls
        {--days=90 : the window of operations and write calls examined, 1-3650 days back}
        {--since= : start of the window (ISO date), instead of --days}
        {--until= : end of the window (ISO date), default now}
        {--instance=* : ISPConfig instance keys, default every ISPConfig instance}
        {--format=md : md or json}
        {--output= : file on the private local disk under reports/, ending in .md or .json as --format says, default reports/provider-calls-audit-<time>.<ext>}
        {--force : replace an existing report file (an earlier report is never overwritten otherwise)}
        {--stdout : print the report instead of writing a file}
        {--include-clean : also list the actions that touched the service\'s own records}';

    protected $description = 'Read-only audit: did a service act on an ISPConfig record that was not its own (TASK-0005)';

    public function handle(ProviderCallsOwnershipAudit $audit): int
    {
        $window = $this->window();
        $format = (string) $this->option('format');
        $instances = $this->instances();
        $path = $this->outputPath($format);
        $problem = match (true) {
            is_string($window) => $window,
            ! in_array($format, ['md', 'json'], true) => '--format must be md or json.',
            is_string($instances) => $instances,
            $path === null => '--output must be a relative path under reports/ (letters, digits, . _ - /) ending in .'.$format.'.',
            ! $this->option('stdout') && ! $this->option('force') && Storage::disk('local')->exists($path) => "storage/app/private/{$path} already exists: choose another --output, or add --force to replace it.",
            default => null,
        };
        if ($problem !== null || ! is_array($window) || ! is_array($instances) || $path === null) {
            $this->error((string) $problem);

            return self::INVALID;
        }
        $report = $audit->run($window[0], $window[1], $instances, (bool) $this->option('include-clean'));
        $text = $format === 'json' ? (string) json_encode($report->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : $report->toMarkdown();
        if ($this->option('stdout')) {
            $this->output->writeln($text, OutputInterface::OUTPUT_RAW); // the report as it is: no console markup is read into it
        } else {
            Storage::disk('local')->put($path, $text);
            $this->info("Report written to storage/app/private/{$path}. It is confidential: delete it when the review is closed.");
            $this->line(implode(' · ', array_map(fn (string $s, int $n) => "{$s} {$n}", array_keys($report->summary), $report->summary)));
        }

        return $report->hasFindings() ? self::FAILURE : self::SUCCESS;
    }

    /** @return array{0:CarbonImmutable, 1:CarbonImmutable}|string */
    private function window(): array|string
    {
        $days = (string) $this->option('days');
        if (! ctype_digit($days) || (int) $days < 1 || (int) $days > self::MAX_DAYS) {
            return '--days must be a whole number from 1 to '.self::MAX_DAYS.'.';
        }
        try {
            $until = $this->option('until') ? CarbonImmutable::parse((string) $this->option('until')) : CarbonImmutable::now();
            $since = $this->option('since') ? CarbonImmutable::parse((string) $this->option('since')) : $until->subDays((int) $days);
        } catch (Throwable) {
            return '--since and --until must be ISO dates.';
        }

        return $since->lessThan($until) ? [$since, $until] : '--since must lie before --until.';
    }

    /** @return list<string>|string */
    private function instances(): array|string
    {
        $known = ProviderInstance::query()->where('provider', 'ispconfig')->orderBy('key')->pluck('key')->map(fn ($key) => (string) $key)->all();
        $asked = array_values(array_filter(array_map('strval', (array) $this->option('instance'))));
        if ($asked === []) {
            return $known;
        }
        $unknown = array_diff($asked, $known);

        return $unknown === [] ? array_values(array_unique($asked)) : 'Not an ISPConfig instance: '.implode(', ', $unknown).'. Available: '.(implode(', ', $known) ?: 'none').'.';
    }

    /** reports/… with safe characters, no `.`/`..`/empty path segment, and the extension of the format. */
    private function outputPath(string $format): ?string
    {
        $extension = $format === 'json' ? 'json' : 'md';
        $path = (string) ($this->option('output') ?? '');
        if ($path === '') {
            return 'reports/provider-calls-audit-'.CarbonImmutable::now()->format('Ymd-His').'.'.$extension;
        }
        $segments = explode('/', $path);
        $safe = preg_match('#^reports/[A-Za-z0-9._/-]+$#', $path) === 1
            && array_filter($segments, fn (string $segment) => in_array($segment, ['', '.', '..'], true)) === []
            && str_ends_with($path, '.'.$extension) && strlen(basename($path)) > strlen($extension) + 1;

        return $safe ? $path : null;
    }
}
