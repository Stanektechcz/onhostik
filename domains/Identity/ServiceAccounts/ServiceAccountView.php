<?php

declare(strict_types=1);

namespace Onhost\Domain\Identity\ServiceAccounts;

use Onhost\Domain\Identity\Authorization\Models\PolicyBinding;
use Onhost\Domain\Identity\Models\PersonalAccessToken;
use Onhost\Domain\Identity\Models\ServiceAccount;

/** How a service account is shown to its owner (TASK-0079): never a secret — a token's plain text is in the create answer only. */
final class ServiceAccountView
{
    /** @return array<string, mixed> */
    public static function account(ServiceAccount $account): array
    {
        $role = self::role($account);
        $tokens = $account->accessTokens()->orderByDesc('created_at')->orderByDesc('id')->get()->map(fn (PersonalAccessToken $token) => self::token($token))->all();

        return [
            'id' => (string) $account->getKey(),
            'name' => $account->name,
            'description' => $account->description,
            'state' => $account->state,
            'role' => $role,
            'created_by' => $account->created_by,
            'created_at' => $account->created_at?->toIso8601String(),
            'tokens' => $tokens,
        ];
    }

    /** The account's organization role (its one binding there), or null when it has none. */
    public static function role(ServiceAccount $account): ?string
    {
        $role = PolicyBinding::query()->where('principal_type', 'service_account')->where('principal_id', (string) $account->getKey())
            ->where('scope_type', 'organization')->where('scope_id', (string) $account->organization_id)->value('role_key');

        return is_string($role) ? $role : null;
    }

    /** @return array<string, mixed> */
    public static function token(PersonalAccessToken $token): array
    {
        return [
            'id' => (string) $token->getKey(),
            'name' => $token->name,
            'scopes' => array_values(array_filter((array) $token->abilities, fn ($ability) => ! str_starts_with((string) $ability, 'org:'))),
            'last_used_at' => $token->last_used_at?->toIso8601String(),
            'expires_at' => $token->expires_at?->toIso8601String(),
            'revoked_at' => $token->revoked_at?->toIso8601String(),
            'created_at' => $token->created_at?->toIso8601String(),
        ];
    }
}
