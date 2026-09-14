<?php

declare(strict_types=1);

namespace Onhost\Domain\Provisioning;

use Closure;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Symfony\Component\Process\Process;

/**
 * A second (third, …) worker when the backlog says so (audit §5i-3): the backlog gauge decides how many helper
 * workers are wanted; `--apply` starts them as short-lived `queue:work --stop-when-empty --max-time` processes that
 * drain the queues and exit, so nothing has to be stopped later. Helpers started within the cool-down are counted,
 * never doubled. Production installs may prefer a systemd template unit driven by the same gauge — the desired count
 * is the contract; how the processes are started is this class's only opinion.
 */
final class QueueScaler
{
    public const HELPERS_KEY = 'onhost:queue:helpers';

    public const QUEUES = 'default,mails,provider-proxmox,provider-ispconfig,provider-aapanel,provider-pterodactyl,provider-powerdns,provider-registrar,provider-kubernetes';

    /** @var (Closure(list<string>): void)|null test seam: receives the command line instead of starting a process */
    public static ?Closure $launcher = null;

    public function __construct(private readonly AutomationLedger $ledger, private readonly CacheRepository $cache) {}

    /**
     * @return array{stale:int, threshold:int, desired:int, running:int, max:int, cooldown_minutes:int}
     */
    public function advise(?int $max = null): array
    {
        $backlog = $this->ledger->backlog();
        $max ??= max(0, (int) config('onhost.provisioning.autoscale.max_helpers', 3));
        $desired = $backlog['alert'] ? min($max, (int) ceil($backlog['stale'] / max(1, $backlog['threshold']))) : 0;
        $running = (int) (($this->cache->get(self::HELPERS_KEY) ?? ['count' => 0])['count'] ?? 0);

        return ['stale' => $backlog['stale'], 'threshold' => $backlog['threshold'], 'desired' => $desired, 'running' => $running, 'max' => $max, 'cooldown_minutes' => max(1, (int) config('onhost.provisioning.autoscale.cooldown_minutes', 15))];
    }

    /** Starts the missing helpers; returns how many were started now. */
    public function apply(?int $max = null): int
    {
        $advice = $this->advise($max);
        $missing = max(0, $advice['desired'] - $advice['running']);
        for ($i = 0; $i < $missing; $i++) {
            $this->launch();
        }
        if ($missing > 0) {
            $this->cache->put(self::HELPERS_KEY, ['count' => $advice['running'] + $missing, 'at' => now()->toIso8601String()], $advice['cooldown_minutes'] * 60);
        }

        return $missing;
    }

    private function launch(): void
    {
        $command = [PHP_BINARY, base_path('artisan'), 'queue:work', '--queue='.self::QUEUES, '--stop-when-empty', '--max-time='.max(60, (int) config('onhost.provisioning.autoscale.max_time_seconds', 900)), '--tries=3', '--sleep=1'];
        if (self::$launcher !== null) {
            (self::$launcher)($command);

            return;
        }
        $process = new Process($command, base_path());
        $process->setTimeout(null);
        $process->disableOutput();
        $process->start();
    }
}
