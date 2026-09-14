<?php

declare(strict_types=1);

namespace Onhost\Domain\Dns\Commands;

use Onhost\Domain\Dns\DnsService;
use Onhost\Domain\Dns\Models\DnsRecord;
use Onhost\Domain\Dns\Models\DnsZone;
use Onhost\Domain\Dns\Models\DnsZoneVersion;
use Onhost\Domain\Domains\Models\Domain;
use Onhost\Platform\Commands\Command;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Commands\CommandHandler;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Support\Hostname;

final class DnsCommandHandler implements CommandHandler
{
    public function __construct(private readonly DnsService $dns) {}

    public function handle(Command $command, CommandContext $context): mixed
    {
        if (! $command instanceof DnsCommand) {
            throw new \LogicException('Unsupported command '.get_class($command));
        }
        if ($command->op() === 'create_zone') {
            $name = Hostname::canonical((string) $command->get('name'));
            $domain = Domain::query()->where('fqdn_ascii', $name)->where('organization_id', $command->organizationId)->first();
            $zone = $this->dns->ensureZone($command->organizationId, $name, $context, $domain?->id, (string) $command->get('template', 'external'), (array) $command->get('vars', []));
            if ($domain !== null && $domain->dns_zone_id === null) {
                $domain->forceFill(['dns_zone_id' => $zone->id])->save();
            }

            return ['zone_id' => $zone->id, 'name' => $zone->name, 'version' => $zone->version, 'nameservers' => $zone->nameservers];
        }
        $zone = DnsZone::query()->find((string) $command->get('zone_id'));
        if ($zone === null || $zone->organization_id !== $command->organizationId) {
            throw DomainError::notFound('dns zone');
        }

        return match ($command->op()) {
            'stage' => ['change' => $this->stage($command, $zone, $context)],
            'discard' => ['discarded' => $this->dns->discard($zone, $context)],
            'commit' => $this->version($this->dns->commit($zone, $context, $command->get('reason'))),
            'rollback' => $this->version($this->dns->rollback($zone, (int) $command->get('version'), $context)),
            'dnssec' => (bool) $command->get('enabled') ? $this->dns->enableDnssec($zone, $context) : (function () use ($zone, $context) {
                $this->dns->disableDnssec($zone, $context);

                return ['enabled' => false, 'ds' => []];
            })(),
            'delete_zone' => (function () use ($zone, $context, $command) {
                $this->dns->deleteZone($zone, $context, (string) $command->get('reason', 'deleted by customer'));

                return ['deleted' => true];
            })(),
            default => throw new DomainError('dns_op_unknown', "Unknown DNS operation {$command->op()}.", 422),
        };
    }

    private function stage(DnsCommand $command, DnsZone $zone, CommandContext $context): array
    {
        $record = (array) $command->get('record', []);
        $confirm = (bool) $command->get('confirm_protected', false);
        $reason = $command->get('reason');
        $existing = $command->get('record_id') !== null ? DnsRecord::query()->find((string) $command->get('record_id')) : null;
        if (in_array($command->get('change'), ['update', 'delete'], true) && $existing === null) {
            throw new DomainError('dns_record_not_found', 'The record to change does not exist.', 404, ['field' => 'record_id']);
        }
        $change = match ((string) $command->get('change', 'add')) {
            'add' => $this->dns->stageAdd($zone, $record, $context, $reason),
            'update' => $this->dns->stageUpdate($zone, $existing, $record, $context, $reason, $confirm),
            'delete' => $this->dns->stageDelete($zone, $existing, $context, $reason, $confirm),
            default => throw new DomainError('dns_change_invalid', 'change must be add, update or delete.', 422, ['field' => 'change']),
        };

        return ['id' => $change->id, 'op' => $change->op, 'record' => $change->record, 'previous' => $change->previous, 'state' => $change->state];
    }

    private function version(DnsZoneVersion $version): array
    {
        return ['version' => $version->version, 'serial' => $version->serial, 'committed_at' => $version->committed_at?->toIso8601String(), 'records' => count((array) $version->records)];
    }
}
