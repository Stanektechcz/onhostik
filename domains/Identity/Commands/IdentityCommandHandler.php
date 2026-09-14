<?php

declare(strict_types=1);

namespace Onhost\Domain\Identity\Commands;

use Onhost\Domain\Identity\Models\PersonalAccessToken;
use Onhost\Domain\Identity\Models\User;
use Onhost\Platform\Commands\Command;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Commands\CommandHandler;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Events\GenericEvent;
use Onhost\Platform\Outbox\OutboxPublisher;

final class IdentityCommandHandler implements CommandHandler
{
    public function __construct(private readonly OutboxPublisher $outbox) {}

    public function handle(Command $command, CommandContext $context): mixed
    {
        if (! $command instanceof ApiTokenCommand) {
            throw new \LogicException('Unsupported command '.get_class($command));
        }
        $user = $context->actorType === 'user' && $context->actorId !== null ? User::query()->find($context->actorId) : null;
        if ($user === null) {
            throw DomainError::forbidden('API tokens belong to a signed-in user.');
        }

        return match ($command->op()) {
            'create' => $this->create($command, $user, $context),
            'revoke' => $this->revoke($command, $user, $context),
            default => throw new DomainError('api_token_op_unknown', "Unknown token operation {$command->op()}.", 422),
        };
    }

    private function create(ApiTokenCommand $command, User $user, CommandContext $context): array
    {
        $scopes = array_values(array_unique(array_map('strval', (array) $command->get('scopes', []))));
        $unknown = array_diff($scopes, ApiTokenCommand::SCOPES);
        if ($scopes === [] || $unknown !== []) {
            throw new DomainError('api_token_scopes_invalid', 'Choose at least one documented scope: '.implode(', ', ApiTokenCommand::SCOPES), 422, ['field' => 'scopes', 'unknown' => array_values($unknown)]);
        }
        $days = (int) $command->get('expires_in_days', 90);
        $expires = now()->addDays(max(1, min(365, $days)));
        $name = trim((string) $command->get('name', ''));
        if ($name === '') {
            throw new DomainError('api_token_name_required', 'Name the token so it can be recognised in the audit log.', 422, ['field' => 'name']);
        }
        $created = $user->createToken($name, array_merge($scopes, ['org:'.$command->organizationId]), $expires);
        /** @var PersonalAccessToken $model */
        $model = $created->accessToken;
        $model->forceFill(['organization_id' => $command->organizationId])->save();
        $this->outbox->publish(GenericEvent::of('api_token.created', 'user', $user->id, ['token_id' => (string) $model->id, 'name' => $name, 'scopes' => $scopes, 'expires_at' => $expires->toIso8601String()], $command->organizationId));

        return ['token' => $created->plainTextToken, 'id' => (string) $model->id, 'name' => $name, 'scopes' => $scopes, 'expires_at' => $expires->toIso8601String()];
    }

    private function revoke(ApiTokenCommand $command, User $user, CommandContext $context): array
    {
        $token = $user->tokens()->whereKey((string) $command->get('token_id'))->first();
        if ($token === null) {
            throw DomainError::notFound('API token');
        }
        $token->forceFill(['revoked_at' => now()])->save();
        $this->outbox->publish(GenericEvent::of('api_token.revoked', 'user', $user->id, ['token_id' => (string) $token->id], $command->organizationId));

        return ['revoked' => true, 'id' => (string) $token->id];
    }
}
