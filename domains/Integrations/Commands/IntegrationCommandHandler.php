<?php

declare(strict_types=1);

namespace Onhost\Domain\Integrations\Commands;

use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Integrations\ActionHookService;
use Onhost\Domain\Integrations\DiscordService;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Services\Models\Service;
use Onhost\Platform\Commands\Command;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Commands\CommandHandler;
use Onhost\Platform\Errors\DomainError;

final class IntegrationCommandHandler implements CommandHandler
{
    public function __construct(private readonly DiscordService $discord, private readonly ActionHookService $hooks) {}

    public function handle(Command $command, CommandContext $context): mixed
    {
        $organization = Organization::query()->find($command->organizationId);
        if ($organization === null) {
            throw DomainError::notFound('organization');
        }
        $user = $context->actorId ? User::query()->find($context->actorId) : null;
        if ($user === null) {
            throw DomainError::forbidden('A signed-in user is required.');
        }
        $params = (array) $command->get('params', []);

        return match ((string) $command->get('op')) {
            'discord.link_code' => $this->discord->createLinkCode($organization, $user, $context),
            'discord.unlink' => (function () use ($organization, $params, $context) {
                $this->discord->unlink($organization, (string) ($params['link_id'] ?? ''), $context);

                return ['unlinked' => true];
            })(),
            'hook.create' => (function () use ($organization, $user, $params, $context) {
                $service = Service::query()->where('organization_id', $organization->id)->find((string) ($params['service_id'] ?? ''));
                if ($service === null) {
                    throw DomainError::notFound('service');
                }

                return $this->hooks->create($organization, $user, $service, (string) ($params['name'] ?? ''), (string) ($params['action'] ?? ''), (array) ($params['params'] ?? []), $context);
            })(),
            'hook.delete' => (function () use ($organization, $params, $context) {
                $this->hooks->delete($organization, (string) ($params['hook_id'] ?? ''), $context);

                return ['deleted' => true];
            })(),
            default => throw new DomainError('op_unknown', 'Unknown integration operation.', 422),
        };
    }
}
