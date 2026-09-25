<?php

declare(strict_types=1);

namespace Onhost\Domain\Services\Metering;

use Carbon\CarbonImmutable;
use Onhost\Domain\Services\IncludedServices;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Platform\Commands\Command;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Commands\CommandHandler;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Events\GenericEvent;
use Onhost\Platform\Outbox\OutboxPublisher;
use Throwable;

/**
 * Records the dated notice on the service (`tags.usage_notices.disk_total = {sent_at, effective}`) and publishes
 * `service.disk_total.announced` for the customer's mail. The record is what `WebDiskTotal::enforcedFor` trusts, so it
 * is written only for a date that is set and far enough ahead, and only once per date: a rerun is a no-op.
 *
 * @implements CommandHandler<AnnounceDiskTotalCommand>
 */
final class AnnounceDiskTotalHandler implements CommandHandler
{
    /** Paying services that hold data and can still be told. */
    public const STATES = [ServiceStateMachine::ACTIVE, ServiceStateMachine::DEGRADED, ServiceStateMachine::SUSPENDED];

    public function __construct(private readonly OutboxPublisher $outbox) {}

    /** @return array{service_id:string, announced:bool, effective:string} */
    public function handle(Command $command, CommandContext $context): mixed
    {
        if (! $command instanceof AnnounceDiskTotalCommand || $context->actorType !== 'system') {
            throw DomainError::forbidden('Only the operator\'s notice command tells customers about the plan total.');
        }
        $effective = self::effective((string) $command->get('effective', ''));
        $service = Service::query()->where('organization_id', $command->organizationId)->whereKey((string) $command->get('service_id', ''))->lockForUpdate()->first();
        if ($service === null || ! in_array($service->family, ['web', 'managed'], true) || IncludedServices::isIncluded($service) || ! in_array($service->state, self::STATES, true)) {
            throw new DomainError('service_not_noticeable', 'Only a paying web service that is running or suspended is told about the plan total.', 422, ['field' => 'service_id']);
        }
        $tags = (array) ($service->tags ?? []);
        if ((string) data_get($tags, 'usage_notices.'.WebDiskTotal::METRIC.'.effective') === $effective) {
            return ['service_id' => $service->id, 'announced' => false, 'effective' => $effective];
        }
        $notices = (array) ($tags['usage_notices'] ?? []);
        $notices[WebDiskTotal::METRIC] = ['sent_at' => now()->toIso8601String(), 'effective' => $effective];
        $service->forceFill(['tags' => array_merge($tags, ['usage_notices' => $notices])])->save();
        $held = (array) (WebDiskTotal::held($service) ?? []);
        $this->outbox->publish(GenericEvent::of('service.disk_total.announced', 'service', $service->id, [
            'effective' => $effective, 'hostname' => $service->hostname, 'label' => $service->label,
            'files' => $held['files'] ?? null, 'databases' => $held['databases'] ?? null, 'mail' => $held['mail'] ?? null,
            'total' => $held['total'] ?? null, 'limit' => $held['limit'] ?? WebDiskTotal::limitBytes($service), 'pct' => $held['pct'] ?? null,
            'quality' => $held['quality'] ?? WebDiskTotal::UNAVAILABLE, 'over' => $held !== [] && WebDiskTotal::isFull($held),
        ], $service->organization_id));

        return ['service_id' => $service->id, 'announced' => true, 'effective' => $effective];
    }

    /** The configured date, and only when it is at least the notice period ahead: a late notice would not count anyway. */
    private static function effective(string $given): string
    {
        $from = WebDiskTotal::enforceFrom();
        try {
            $date = CarbonImmutable::createFromFormat('!Y-m-d', $given) ?: null;
        } catch (Throwable) {
            $date = null;
        }
        if ($from === null || $date === null || $date->toDateString() !== $from->toDateString()) {
            throw new DomainError('disk_total_date_mismatch', 'The notice must name the configured enforcement date (onhost.metering.web_disk_total.enforce_from).', 422, ['field' => 'effective']);
        }
        if ($from->lessThan(now()->startOfDay()->addDays(WebDiskTotal::noticeDays()))) {
            throw new DomainError('disk_total_notice_too_late', 'The enforcement date is less than '.WebDiskTotal::noticeDays().' days away; set a later date before telling customers.', 422, ['field' => 'effective']);
        }

        return $from->toDateString();
    }
}
