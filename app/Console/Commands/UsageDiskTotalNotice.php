<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Onhost\Domain\Services\IncludedServices;
use Onhost\Domain\Services\Metering\AnnounceDiskTotalCommand;
use Onhost\Domain\Services\Metering\AnnounceDiskTotalHandler;
use Onhost\Domain\Services\Metering\WebDiskTotal;
use Onhost\Domain\Services\Models\Service;
use Onhost\Platform\Commands\CommandBus;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;

/**
 * The dated notice before the plan's total (files + databases + mail) counts (TASK-0023 web-disk-total). A dry run by
 * default: it lists every paying web service with its last measured total and whether it is over its plan today, and
 * writes nothing. `--send` tells each of them once for the configured date (`AnnounceDiskTotalCommand` through the
 * bus) and is refused while no date is set or the date is closer than the notice period. Production only by a human.
 */
final class UsageDiskTotalNotice extends Command
{
    protected $signature = 'onhost:usage:disk-total-notice {--send : record and mail the notice (without it: dry run)} {--limit=500 : at most this many services} {--service=* : only these service ids}';

    protected $description = 'Tell web customers from when their plan space counts as files + databases + mail together (dry run unless --send)';

    public function handle(CommandBus $bus): int
    {
        $send = (bool) $this->option('send');
        $from = WebDiskTotal::enforceFrom();
        if ($send && ($from === null || $from->lessThan(now()->startOfDay()->addDays(WebDiskTotal::noticeDays())))) {
            $this->error('No notice sent: set onhost.metering.web_disk_total.enforce_from (ONHOST_WEB_DISK_TOTAL_ENFORCE_FROM) to a date at least '.WebDiskTotal::noticeDays().' days ahead first,'
                .' and confirm the parts are measured without double counting (ONHOST_WEB_DISK_TOTAL_PARTS_VERIFIED=true; without it the date counts for nobody).');

            return self::FAILURE;
        }
        $rows = [];
        $counts = ['listed' => 0, 'over' => 0, 'announced' => 0, 'already' => 0, 'refused' => 0, 'included' => 0];
        $limit = max(1, (int) $this->option('limit'));
        $this->owners()->chunkById(200, function (Collection $services) use (&$rows, &$counts, $limit, $send, $from, $bus) {
            foreach ($services as $service) {
                if (IncludedServices::isIncluded($service)) {
                    // its space is part of its owner's plan total: the owner is told, the site never on its own
                    $counts['included']++;

                    continue;
                }
                if ($counts['listed'] >= $limit) {
                    return false;
                }
                $held = (array) (WebDiskTotal::held($service) ?? []);
                $over = $held !== [] && WebDiskTotal::isFull($held);
                $counts['listed']++;
                $counts['over'] += $over ? 1 : 0;
                $outcome = '—';
                if ($send && $from !== null) {
                    $outcome = $this->announce($bus, $service, $from->toDateString(), $counts);
                }
                $rows[] = [$service->id, (string) $service->hostname, WebDiskTotal::size($held['files'] ?? null), WebDiskTotal::size($held['databases'] ?? null), WebDiskTotal::size($held['mail'] ?? null),
                    WebDiskTotal::size($held['total'] ?? null), WebDiskTotal::size($held['limit'] ?? WebDiskTotal::limitBytes($service)), isset($held['pct']) ? $held['pct'].' %' : '—', (string) ($held['quality'] ?? 'nezměřeno'), $over ? 'ano' : 'ne', $outcome];
            }

            return true;
        });
        $this->table(['service', 'hostname', 'files', 'databases', 'mail', 'total', 'plan', 'used', 'quality', 'over', 'notice'], $rows);
        $this->line(sprintf('enforce_from %s · listed %d · over today %d · included sites skipped %d (their space counts in the owner\'s plan; the owner is told)',
            $from?->toDateString() ?? 'not set', $counts['listed'], $counts['over'], $counts['included']));
        $this->line($send ? sprintf('announced %d · already told %d · refused %d', $counts['announced'], $counts['already'], $counts['refused']) : 'dry run — nothing was recorded or sent; add --send to tell these customers');

        return self::SUCCESS;
    }

    /** @return Builder<Service> */
    private function owners(): Builder
    {
        $query = Service::query()->whereIn('family', ['web', 'managed'])->whereIn('state', AnnounceDiskTotalHandler::STATES);
        $only = array_values(array_filter(array_map('strval', (array) $this->option('service'))));

        return $only === [] ? $query : $query->whereIn('id', $only);
    }

    /** @param  array<string,int>  $counts */
    private function announce(CommandBus $bus, Service $service, string $effective, array &$counts): string
    {
        if ((string) data_get($service->tags, 'usage_notices.'.WebDiskTotal::METRIC.'.effective') === $effective) {
            $counts['already']++;

            return 'already told';
        }
        try {
            $result = (array) $bus->dispatch(new AnnounceDiskTotalCommand((string) $service->organization_id, "disk-total-notice:{$service->id}:{$effective}", ['service_id' => $service->id, 'effective' => $effective]),
                CommandContext::system('operator:disk-total-notice'));
        } catch (DomainError $e) {
            $counts['refused']++;

            return 'refused: '.$e->error;
        }
        $announced = ! empty($result['announced']);
        $counts[$announced ? 'announced' : 'already']++;

        return $announced ? 'sent' : 'already told';
    }
}
