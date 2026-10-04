<?php

declare(strict_types=1);

namespace Onhost\Domain\Identity\ServiceAccounts;

use Onhost\Domain\Identity\Authorization\Authorizer;
use Onhost\Domain\Identity\Authorization\Models\PolicyBinding;
use Onhost\Domain\Identity\Models\PersonalAccessToken;
use Onhost\Domain\Identity\Models\ServiceAccount;
use Onhost\Domain\Identity\Tokens\TokenLifetime;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Organizations\OwnerRecoveries;
use Onhost\Platform\Commands\Command;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Commands\CommandHandler;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Events\GenericEvent;
use Onhost\Platform\Outbox\OutboxPublisher;

/**
 * TASK-0079 (audit 2026-10, D6): an organization's service accounts and their tokens. The account is the organization's — it
 * outlives the person who set it up, acts with the organization role it was given (a `service_account` binding the Authorizer
 * reads) and only through the scopes of its tokens. A token's plain text is returned once, by the op that made it; the replay
 * store and the audit keep it masked (IdempotencyStore, Redactor). A revoked token and a removed account stop at the next request
 * (PersonalAccessToken::findToken, the soft-deleted tokenable, ApiContext::serviceAccountOrganization).
 */
final class ServiceAccountCommandHandler implements CommandHandler
{
    public function __construct(private readonly OutboxPublisher $outbox, private readonly Authorizer $authorizer) {}

    public function handle(Command $command, CommandContext $context): mixed
    {
        if (! $command instanceof ServiceAccountCommand) {
            throw new \LogicException('Unsupported command '.get_class($command));
        }
        $organization = Organization::query()->find($command->organizationId) ?? throw DomainError::notFound('organization');
        if ($context->actorType !== 'user') {
            throw DomainError::forbidden('Service accounts are managed by the owner of the organization in the portal.');
        }
        ServiceAccountRules::assertOwner($organization, $context->actorId);
        $owner = (string) $context->actorId;

        return match ($command->op()) {
            'create' => $this->create($command, $organization, $owner),
            'update' => $this->update($command, $organization),
            'delete' => $this->delete($command, $organization),
            'issue_token' => $this->issueToken($command, $organization, ServiceAccountRules::accountOf($organization, (string) $command->get('account_id'))),
            'revoke_token' => $this->revokeToken($command, $organization),
            default => throw new DomainError('service_account_op_unknown', "Unknown service account operation {$command->op()}.", 422),
        };
    }

    /** @return array<string, mixed> */
    private function create(ServiceAccountCommand $command, Organization $organization, string $owner): array
    {
        $name = ServiceAccountRules::name($command->get('name'));
        $role = ServiceAccountRules::role($command->get('role'));
        ServiceAccountRules::scopes($command->get('scopes')); // refused before anything is written
        $description = $command->get('description');
        $account = ServiceAccount::query()->create([
            'organization_id' => $organization->id, 'name' => $name, 'description' => is_string($description) && trim($description) !== '' ? mb_substr(trim($description), 0, 1000) : null,
            'state' => 'active', 'created_by' => $owner,
        ]);
        PolicyBinding::query()->create([
            'principal_type' => 'service_account', 'principal_id' => (string) $account->getKey(), 'role_key' => $role,
            'scope_type' => 'organization', 'scope_id' => $organization->id, 'organization_id' => $organization->id, 'granted_by' => $owner,
        ]);
        $token = $this->issueToken($command, $organization, $account, $command->get('token_name') ?? $name);

        return ['data' => ServiceAccountView::account($account->refresh())] + $token;
    }

    /** @return array<string, mixed> */
    private function update(ServiceAccountCommand $command, Organization $organization): array
    {
        $account = ServiceAccountRules::accountOf($organization, (string) $command->get('account_id'));
        $changes = [];
        if (array_key_exists('name', $command->payload)) {
            $changes['name'] = ServiceAccountRules::name($command->get('name'));
        }
        if (array_key_exists('description', $command->payload)) {
            $description = $command->get('description');
            $changes['description'] = is_string($description) && trim($description) !== '' ? mb_substr(trim($description), 0, 1000) : null;
        }
        $account->forceFill($changes)->save();

        return ['data' => ServiceAccountView::account($account)];
    }

    /** Every token of the account is revoked, its roles go, and the account is removed (kept as a soft-deleted row for the audit). */
    private function delete(ServiceAccountCommand $command, Organization $organization): array
    {
        $account = ServiceAccountRules::accountOf($organization, (string) $command->get('account_id'));
        $revoked = $account->accessTokens()->whereNull('revoked_at')->get();
        foreach ($revoked as $token) {
            $this->revoke($token, $account, $organization);
        }
        PolicyBinding::query()->where('principal_type', 'service_account')->where('principal_id', (string) $account->getKey())->delete();
        $account->forceFill(['state' => 'deleted'])->save();
        $account->delete();
        $this->authorizer->forget($account);

        return ['deleted' => true, 'id' => (string) $account->getKey(), 'revoked_tokens' => $revoked->count()];
    }

    /** @return array{token: string, id: string, name: string, scopes: list<string>, expires_at: string} */
    private function issueToken(ServiceAccountCommand $command, Organization $organization, ServiceAccount $account, mixed $name = null): array
    {
        $scopes = ServiceAccountRules::scopes($command->get('scopes'));
        $tokenName = ServiceAccountRules::name($name ?? $command->get('name'));
        OwnerRecoveries::assertNoHold($organization, 'api_token'); // D21: no credential leaves an organization whose owner is being recovered
        $expires = now()->addDays(TokenLifetime::days($command->get('expires_in_days'))); // R9: every token ends, never past the operator's cap
        $created = $account->createToken($tokenName, array_merge($scopes, ['org:'.$organization->id]), $expires);
        /** @var PersonalAccessToken $model */
        $model = $created->accessToken;
        $model->forceFill(['organization_id' => $organization->id, 'created_by' => $account->created_by])->save();
        $this->outbox->publish(GenericEvent::of('api_token.created', 'service_account', (string) $account->getKey(), ['token_id' => (string) $model->getKey(), 'name' => $tokenName, 'scopes' => $scopes, 'expires_at' => $expires->toIso8601String()], $organization->id));

        return ['token' => $created->plainTextToken, 'id' => (string) $model->getKey(), 'name' => $tokenName, 'scopes' => $scopes, 'expires_at' => $expires->toIso8601String()];
    }

    /** @return array{revoked: true, id: string} */
    private function revokeToken(ServiceAccountCommand $command, Organization $organization): array
    {
        $account = ServiceAccountRules::accountOf($organization, (string) $command->get('account_id'));
        $id = (string) $command->get('token_id');
        $token = ctype_digit($id) ? $account->accessTokens()->whereKey($id)->first() : null; // a word for the bigint key is a 500 on PostgreSQL
        if (! $token instanceof PersonalAccessToken) {
            throw DomainError::notFound('API token');
        }
        if (! $token->isRevoked()) {
            $this->revoke($token, $account, $organization);
        }

        return ['revoked' => true, 'id' => (string) $token->getKey()];
    }

    private function revoke(PersonalAccessToken $token, ServiceAccount $account, Organization $organization): void
    {
        $token->forceFill(['revoked_at' => now()])->save();
        $this->outbox->publish(GenericEvent::of('api_token.revoked', 'service_account', (string) $account->getKey(), ['token_id' => (string) $token->getKey()], $organization->id));
    }
}
