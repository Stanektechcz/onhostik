<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Onhost\Domain\Identity\Authorization\TokenScopes;
use Onhost\Domain\Identity\Models\ServiceAccount;
use Onhost\Domain\Identity\ServiceAccounts\ServiceAccountCommand;
use Onhost\Domain\Identity\ServiceAccounts\ServiceAccountRules;
use Onhost\Domain\Identity\ServiceAccounts\ServiceAccountView;
use Onhost\Domain\Identity\Tokens\TokenLifetime;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Platform\Commands\CommandScope;

/**
 * An organization's service accounts (TASK-0079, audit 2026-10 package D6): the owner lists them, creates one with a role and a
 * first token, renames it, issues and revokes its tokens and removes it. Every write is a ServiceAccountCommand on the bus (HIGH,
 * fresh step-up); the owner check comes first here so that anybody else hears "owner only" rather than "step up first". API tokens
 * do not reach these routes at all (TokenRouteScope: no family) — a token never manages tokens.
 */
final class ServiceAccountController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $organization = $this->owned($request);
        $accounts = ServiceAccount::query()->where('organization_id', $organization->id)->orderBy('name')->orderBy('id')->get();

        return response()->json(['data' => $accounts->map(fn (ServiceAccount $account) => ServiceAccountView::account($account))->all(), 'roles' => ServiceAccountRules::roles(), 'scopes' => TokenScopes::ALL]);
    }

    public function show(Request $request, string $account): JsonResponse
    {
        [, $model] = $this->ownedAccount($request, $account);

        return response()->json(['data' => ServiceAccountView::account($model)]);
    }

    public function store(Request $request): JsonResponse
    {
        $organization = $this->owned($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'], 'description' => ['nullable', 'string', 'max:1000'],
            'role' => ['required', 'string', 'in:'.implode(',', ServiceAccountRules::roles())],
            'token_name' => ['nullable', 'string', 'max:120'],
        ] + self::tokenRules());

        return $this->dispatch(new ServiceAccountCommand($organization->id, $this->onceKey($request, 'service_account.create'), ['op' => 'create'] + $data), $this->api->context($request, $organization), 201);
    }

    public function update(Request $request, string $account): JsonResponse
    {
        [$organization] = $this->ownedAccount($request, $account);
        $data = $request->validate(['name' => ['sometimes', 'required', 'string', 'max:120'], 'description' => ['sometimes', 'nullable', 'string', 'max:1000']]);

        return $this->dispatch(new ServiceAccountCommand($organization->id, $this->idempotencyKey($request, "service_account.update:{$account}"), ['op' => 'update', 'account_id' => $account] + $data), $this->api->context($request, $organization));
    }

    public function destroy(Request $request, string $account): JsonResponse
    {
        [$organization] = $this->ownedAccount($request, $account);

        return $this->dispatch(new ServiceAccountCommand($organization->id, "service_account.delete:{$account}", ['op' => 'delete', 'account_id' => $account]), $this->api->context($request, $organization));
    }

    public function issueToken(Request $request, string $account): JsonResponse
    {
        [$organization] = $this->ownedAccount($request, $account);
        $data = $request->validate(['name' => ['required', 'string', 'max:120']] + self::tokenRules());

        return $this->dispatch(new ServiceAccountCommand($organization->id, $this->onceKey($request, "service_account.token:{$account}"), ['op' => 'issue_token', 'account_id' => $account] + $data), $this->api->context($request, $organization), 201);
    }

    public function revokeToken(Request $request, string $account, string $token): JsonResponse
    {
        [$organization] = $this->ownedAccount($request, $account);

        return $this->dispatch(new ServiceAccountCommand($organization->id, "service_account.revoke:{$account}:{$token}", ['op' => 'revoke_token', 'account_id' => $account, 'token_id' => $token]), $this->api->context($request, $organization));
    }

    /** The organization of the request, when the person asking may manage tokens there AND is its owner. */
    private function owned(Request $request): Organization
    {
        $organization = $this->api->organization($request);
        $this->api->authorize($request, 'api_token.manage', CommandScope::organization($organization->id));
        ServiceAccountRules::assertOwner($organization, $this->api->user($request)->id);

        return $organization;
    }

    /**
     * The owned organization and its account `$id` — found before the body is validated, so another organization's account is
     * not found whatever is sent (TenantIsolationSweepTest), and the handler looks it up again inside the bus.
     *
     * @return array{0: Organization, 1: ServiceAccount}
     */
    private function ownedAccount(Request $request, string $id): array
    {
        $organization = $this->owned($request);

        return [$organization, ServiceAccountRules::accountOf($organization, $id)];
    }

    /** @return array<string, list<string>> */
    private static function tokenRules(): array
    {
        return [
            'scopes' => ['required', 'array', 'min:1'], 'scopes.*' => ['string', 'in:'.implode(',', TokenScopes::ALL)],
            'expires_in_days' => ['nullable', 'integer', 'min:1', 'max:'.TokenLifetime::maxDays()], // R9: the operator's cap
        ];
    }
}
