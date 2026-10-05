<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Support\ApiContext;
use Closure;
use Illuminate\Http\Request;
use Onhost\Domain\Identity\Authorization\Authorizer;
use Onhost\Domain\Identity\Authorization\PermissionCatalog;
use Onhost\Domain\Identity\Authorization\StaffActor;
use Onhost\Domain\Identity\Models\ServiceAccount;
use Onhost\Domain\Identity\Models\User;
use Onhost\Platform\Commands\CommandScope;
use Onhost\Platform\Errors\DomainError;
use Symfony\Component\HttpFoundation\Response;

/**
 * The structural guard of /v1/staff/* (phase D, package D2): only a person who acts as staff (StaffActor — staff mode, an
 * active staff account acting for themselves, never a token) and holds at least one staff-audience permission at platform
 * level reaches a staff controller.
 *
 * Every controller and the bus still ask their own permission; this guard exists so that one that forgets cannot hand a staff
 * endpoint to a customer, and so that a refusal does not tell anybody outside staff which identifiers exist (404) or what an
 * endpoint expects (422). It runs before route model binding (bootstrap/app.php puts it ahead of SubstituteBindings).
 *
 * A customer key held through a global binding is no staff permission: it is the platform's reach into customer scopes, not a
 * staff role (Authorizer::isReach) — such a person is refused here like the console role `none`.
 */
final class EnsureStaff
{
    public function __construct(private readonly ApiContext $api, private readonly Authorizer $authorizer) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user instanceof ServiceAccount) { // a signed-in principal, just not staff: 403, not a 401 that tells a valid pipeline credential to sign in again
            throw new DomainError('staff_only', 'This endpoint is for ONhost staff with a staff permission.', 403);
        }
        if (! $user instanceof User) {
            throw new DomainError('unauthenticated', 'Sign in to continue.', 401);
        }
        if (! StaffActor::acts($this->api->context($request)) || ! $this->holdsStaffPermission($user)) {
            throw new DomainError('staff_only', 'This endpoint is for ONhost staff with a staff permission.', 403);
        }

        return $next($request);
    }

    private function holdsStaffPermission(User $user): bool
    {
        $catalog = PermissionCatalog::all();
        foreach ($this->authorizer->permissionsAt($user, CommandScope::global()) as $permission) {
            if (($catalog[$permission]['audience'] ?? null) === 'staff') {
                return true;
            }
        }

        return false;
    }
}
