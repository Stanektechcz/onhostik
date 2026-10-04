<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Support\CurrentOrganization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Onhost\Domain\Identity\Authorization\TokenScopes;
use Onhost\Domain\Identity\Commands\WebSessionCommand;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Identity\WebSessions;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Platform\Audit\AuditRecorder;
use Onhost\Platform\Errors\DomainError;

/**
 * What belongs to the signed-in browser (TASK-0070, audit 2026-10 C11): the person's open web sessions — listed, one of them
 * ended, every other one ended (through the bus, WebSessionCommand) — and the organization the portal works in for a person in
 * several organizations. Portal only: an API token is refused here (TokenRouteScope refuses it before, this says it again).
 */
final class WebSessionController extends ApiController
{
    public function index(Request $request, WebSessions $sessions): JsonResponse
    {
        $user = $this->browserUser($request);

        return response()->json(['data' => $sessions->listOpen((string) $user->id, $this->currentSession($request))]);
    }

    public function end(Request $request, string $session): JsonResponse
    {
        $user = $this->browserUser($request);
        if (preg_match('/^ws_[a-z0-9]{10,36}$/', $session) !== 1) {
            throw DomainError::notFound('session'); // not an id of ours; the same answer as somebody else's (and the key stays short)
        }

        return $this->dispatch(new WebSessionCommand("web_session.end:{$user->id}:{$session}", ['op' => 'end', 'session_id' => $session, 'keep' => $this->currentSession($request)]), $this->api->context($request));
    }

    public function endOthers(Request $request): JsonResponse
    {
        $user = $this->browserUser($request);

        return $this->dispatch(new WebSessionCommand($this->onceKey($request, "web_session.end_others:{$user->id}"), ['op' => 'end_others', 'keep' => $this->currentSession($request)]), $this->api->context($request));
    }

    /**
     * The organization this browser works in. Only one of the person's current memberships — staff included (the staff console
     * reaches customers by its own routes). The page reloads after the switch, so the boot object, the panel's data and every
     * X-Organization the bridge sends name the chosen organization.
     */
    public function switchOrganization(Request $request, AuditRecorder $audit): JsonResponse
    {
        $user = $this->browserUser($request);
        $data = $request->validate(['organization_id' => ['required', 'string', 'max:40']]);
        $organization = Organization::query()->find($data['organization_id']);
        if ($organization === null) {
            throw DomainError::forbidden('You are not a member of this organization.'); // the same answer as a foreign one: ids are not probed
        }
        CurrentOrganization::choose($request, $user, $organization->id);
        $audit->record($this->api->context($request, $organization), 'identity.organization.switch', 'succeeded', ['organization_id' => $organization->id], 'organization', $organization->id);

        return response()->json(['data' => ['organization' => ['id' => $organization->id, 'name' => $organization->name]]]);
    }

    private function browserUser(Request $request): User
    {
        $user = $this->api->user($request);
        if (TokenScopes::tokenOf($user) !== null) {
            throw DomainError::forbidden('This endpoint is not available to API tokens; use the portal.');
        }

        return $user;
    }

    private function currentSession(Request $request): ?string
    {
        $id = $request->hasSession() ? $request->session()->get(WebSessions::SESSION_KEY) : null;

        return is_string($id) && $id !== '' ? $id : null;
    }
}
