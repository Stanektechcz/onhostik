<?php

declare(strict_types=1);

namespace Onhost\Domain\Domains\Commands;

use Onhost\Domain\Domains\DomainService;
use Onhost\Domain\Domains\Models\Domain;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Platform\Commands\Command;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Commands\CommandHandler;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Support\Hostname;

final class DomainsCommandHandler implements CommandHandler
{
    public function __construct(private readonly DomainService $domains) {}

    public function handle(Command $command, CommandContext $context): mixed
    {
        if (! $command instanceof DomainCommand) {
            throw new \LogicException('Unsupported command '.get_class($command));
        }
        $organization = Organization::query()->findOrFail($command->organizationId);
        $op = $command->op();
        if (in_array($op, ['contact', 'register', 'transfer_in'], true)) {
            return match ($op) {
                'contact' => ['contact' => $this->domains->createContact($organization, (array) $command->get('contact', $command->payload), $context)],
                'register' => $this->operation($this->domains->register($organization, (string) $command->get('fqdn'), (array) $command->payload, $context, $command->idempotencyKey)),
                'transfer_in' => $this->operation($this->domains->transferIn($organization, (string) $command->get('fqdn'), (string) $command->get('auth_info'), (array) $command->payload, $context, $command->idempotencyKey)),
            };
        }
        $domain = $this->domain($command);

        return match ($op) {
            'renew' => $this->operation($this->domains->renew($domain, (int) $command->get('years', $domain->renewal_period ?: 1), $context, $command->idempotencyKey)),
            'nameservers' => $this->operation($this->domains->updateNameservers($domain, (array) $command->get('nameservers', []), $context, $command->idempotencyKey, (string) $command->get('dns_provider', 'external'))),
            'use_onhost_dns' => $this->operation($this->domains->useOnhostDns($domain, $context, $command->idempotencyKey, (string) $command->get('template', 'parking'), (array) $command->get('vars', []))),
            'auto_renew' => ['domain_id' => $domain->id, 'auto_renew' => $this->domains->setAutoRenew($domain, (bool) $command->get('enabled'), $context)->auto_renew],
            'transfer_lock' => ['domain_id' => $domain->id, 'transfer_lock' => $this->domains->setTransferLock($domain, (bool) $command->get('locked'), $context)->transfer_lock],
            'auth_info' => (function () use ($domain, $context) {
                $result = $this->domains->requestAuthInfo($domain, $context);

                return ['domain_id' => $domain->id, 'delivery' => $result['delivery'], 'auth_info' => $result['delivery'] === 'inline' ? $result['auth_info'] : 'sent_to_registrant', 'expires_at' => $result['expires_at'] ?? null];
            })(),
            'publish_ds' => (function () use ($domain, $context) {
                $this->domains->publishDs($domain, $context);

                return ['domain_id' => $domain->id, 'dnssec' => true];
            })(),
            default => throw new DomainError('domain_op_unknown', "Unknown domain operation {$op}.", 422),
        };
    }

    private function domain(DomainCommand $command): Domain
    {
        $fqdn = Hostname::canonical((string) $command->get('fqdn'));
        $domain = Domain::query()->where('fqdn_ascii', $fqdn)->first();
        if ($domain === null || $domain->organization_id !== $command->organizationId) {
            throw DomainError::notFound('domain');
        }

        return $domain;
    }

    private function operation(Operation $operation): array
    {
        return ['operation_id' => $operation->id, 'state' => $operation->state, 'kind' => $operation->kind, 'domain_id' => $operation->domain_id];
    }
}
