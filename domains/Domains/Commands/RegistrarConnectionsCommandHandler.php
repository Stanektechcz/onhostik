<?php

declare(strict_types=1);

namespace Onhost\Domain\Domains\Commands;

use Onhost\Domain\Domains\DomainPairingService;
use Onhost\Domain\Domains\Models\Domain;
use Onhost\Domain\Domains\Models\RegistrarConnection;
use Onhost\Domain\Domains\RegistrarConnectionService;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Services\Models\Service;
use Onhost\Platform\Commands\Command;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Commands\CommandHandler;
use Onhost\Platform\Errors\DomainError;

final class RegistrarConnectionsCommandHandler implements CommandHandler
{
    public function __construct(private readonly RegistrarConnectionService $connections, private readonly DomainPairingService $pairing) {}

    public function handle(Command $command, CommandContext $context): mixed
    {
        if (! $command instanceof RegistrarConnectionCommand) {
            throw new \LogicException('Unsupported command '.get_class($command));
        }
        $organization = Organization::query()->findOrFail($command->organizationId);

        return match ($command->op()) {
            'connect' => ['connection' => $this->connections->connect($organization, $command->payload, $context)],
            'probe' => (function () use ($organization, $command, $context) {
                $connection = $this->connection($organization, $command);
                $result = $this->connections->probe($connection, $context);

                return ['connection' => $connection->refresh(), 'result' => $result];
            })(),
            'sync', 'staff_sync' => (function () use ($organization, $command, $context) {
                $connection = $this->connection($organization, $command);
                $summary = $this->connections->sync($connection, $context);

                return ['connection' => $connection->refresh(), 'summary' => $summary];
            })(),
            'settings' => ['connection' => $this->connections->updateSettings($this->connection($organization, $command), array_diff_key($command->payload, array_flip(['op', 'connection_id'])), $context)],
            'disconnect' => ['connection' => $this->connections->disconnect($this->connection($organization, $command), $context), 'disconnected' => true],
            'disable' => ['connection' => $this->connections->setEnabled($this->connection($organization, $command), false, $context, $command->get('reason') === null ? null : (string) $command->get('reason'))],
            'enable' => ['connection' => $this->connections->setEnabled($this->connection($organization, $command), true, $context)],
            'pair' => ['pairing' => $this->pairing->pair($this->domain($organization, $command), $this->service($organization, $command), $context)],
            'unpair' => ['pairing' => $this->pairing->unpair($this->domain($organization, $command), $context)],
            default => throw new DomainError('registrar_connection_op_unknown', "Unknown operation {$command->op()}.", 422),
        };
    }

    private function connection(Organization $organization, RegistrarConnectionCommand $command): RegistrarConnection
    {
        $connection = RegistrarConnection::query()->where('organization_id', $organization->id)->find((string) $command->get('connection_id'));
        if ($connection === null) {
            throw DomainError::notFound('registrar_connection');
        }

        return $connection;
    }

    private function domain(Organization $organization, RegistrarConnectionCommand $command): Domain
    {
        $domain = Domain::query()->where('organization_id', $organization->id)->find((string) $command->get('domain_id'));
        if ($domain === null) {
            throw DomainError::notFound('domain');
        }

        return $domain;
    }

    private function service(Organization $organization, RegistrarConnectionCommand $command): Service
    {
        $service = Service::query()->where('organization_id', $organization->id)->find((string) $command->get('service_id'));
        if ($service === null) {
            throw DomainError::notFound('service');
        }

        return $service;
    }
}
