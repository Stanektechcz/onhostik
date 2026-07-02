<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Monitoring\Models\Monitor;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Symfony\Component\Process\Process;

/**
 * Renews SSL certificates via certbot for domains expiring within the threshold.
 *
 * Usage:
 *   php artisan ssl:renew                     # all monitors expiring within 14 days
 *   php artisan ssl:renew example.com         # specific domain
 *   php artisan ssl:renew --dry-run           # print what would be renewed, no changes
 *   php artisan ssl:renew --threshold=30      # renew if expiring within 30 days
 *
 * Requires certbot to be installed on the server:
 *   apt install certbot python3-certbot-nginx  # Nginx
 *   apt install certbot python3-certbot-apache # Apache / aaPanel
 */
final class AcmeSslRenewCommand extends Command
{
    protected $signature = 'ssl:renew
        {domain? : Specific domain to renew (optional — renews all if omitted)}
        {--dry-run : Simulate only, do not run certbot}
        {--threshold=14 : Renew certificates expiring within this many days}
        {--webroot= : Webroot path for certbot --webroot mode (leave empty for --nginx)}';

    protected $description = 'Renew SSL certificates via certbot for expiring domains';

    public function handle(): int
    {
        $domain    = (string) ($this->argument('domain') ?? '');
        $dryRun    = (bool) $this->option('dry-run');
        $threshold = (int) ($this->option('threshold') ?? 14);
        $webroot   = (string) ($this->option('webroot') ?? '');
        $renewed   = 0;
        $failed    = 0;

        $domains = $this->resolveDomains($domain, $threshold);

        if ($domains->isEmpty()) {
            $this->info('No SSL certificates due for renewal.');
            return self::SUCCESS;
        }

        foreach ($domains as $target) {
            if ($dryRun) {
                $this->line("[dry-run] Would renew SSL for: {$target}");
                $renewed++;
                continue;
            }

            $result = $this->runCertbot($target, $webroot);

            if ($result) {
                $this->info("SSL renewed: {$target}");
                $this->updateMonitorExpiry($target);
                $renewed++;
            } else {
                $this->error("SSL renewal failed: {$target}");
                $failed++;
            }
        }

        $this->info("SSL renew: {$renewed} renewed, {$failed} failed.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /** @return \Illuminate\Support\Collection<int, string> */
    private function resolveDomains(string $domain, int $threshold): \Illuminate\Support\Collection
    {
        if ($domain !== '') {
            /** @var \Illuminate\Support\Collection<int, string> $col */
            $col = collect([$domain]);
            return $col;
        }

        $cutoff = Carbon::now()->addDays($threshold);

        /** @var \Illuminate\Support\Collection<int, string> $col */
        $col = Monitor::query()
            ->whereNotNull('ssl_expires_at')
            ->where('ssl_expires_at', '<=', $cutoff)
            ->where('is_active', true)
            ->pluck('target')
            ->map(fn (string $url) => parse_url($url, PHP_URL_HOST) ?? $url)
            ->filter()
            ->unique()
            ->values();

        return $col;
    }

    private function runCertbot(string $domain, string $webroot): bool
    {
        $cmd = $webroot !== ''
            ? ['certbot', 'certonly', '--webroot', '-w', $webroot, '-d', $domain, '--non-interactive', '--agree-tos', '--quiet']
            : ['certbot', 'certonly', '--nginx', '-d', $domain, '--non-interactive', '--agree-tos', '--quiet'];

        $process = new Process($cmd);
        $process->setTimeout(120);
        $process->run();

        if (!$process->isSuccessful()) {
            $this->line($process->getErrorOutput());
        }

        return $process->isSuccessful();
    }

    private function updateMonitorExpiry(string $domain): void
    {
        Monitor::query()
            ->where('target', 'LIKE', '%' . $domain . '%')
            ->update(['ssl_expires_at' => Carbon::now()->addDays(90)]);
    }
}
