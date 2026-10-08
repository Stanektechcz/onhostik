<?php

declare(strict_types=1);

namespace Onhost\Domain\Integrations;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Onhost\Domain\Identity\Authorization\Authorizer;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Integrations\Models\ActionHook;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Organizations\Models\OrganizationMembership;
use Onhost\Domain\Services\Commands\ServiceActionCommand;
use Onhost\Domain\Services\CustomerActionParams;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\ServiceFeatures;
use Onhost\Domain\Services\ServiceService;
use Onhost\Platform\Audit\AuditRecorder;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Commands\CommandScope;
use Onhost\Platform\Errors\DomainError;

/**
 * Action hooks: a signed URL that runs one predefined action on one service — a "Back up now" or "Deploy" button for
 * Discord bots, CI pipelines, cron jobs or a phone shortcut. The token is shown once; every trigger runs as the user
 * who created the hook (permissions and plan limits apply) and is audited and rate-limited.
 */
final class ActionHookService
{
    /** Never an action that needs a fresh step-up (staging.push since TASK-0029): a URL called by a bot cannot give one. */
    /** Triggers within this many seconds of the first run are one operation. */
    private const BURST_SECONDS = 10;

    public const ALLOWED = ['backup', 'power', 'deploy.run', 'staging.refresh', 'wp.update', 'wp.cache', 'cdn.purge', 'cron.run', 'ssl.issue', 'https.force', 'php.set', 'redirect.set', 'monitoring.set'];

    public function __construct(private readonly ServiceService $services, private readonly ServiceFeatures $features, private readonly Authorizer $authorizer, private readonly AuditRecorder $audit) {}

    /** @return list<array<string,mixed>> */
    public function list(Organization $organization, ?Service $service = null): array
    {
        $query = ActionHook::query()->where('organization_id', $organization->id)->orderByDesc('created_at');
        if ($service !== null) {
            $query->where('service_id', $service->id);
        }

        return $query->get()->map(fn (ActionHook $h) => $this->present($h))->all();
    }

    /**
     * @param  array<string,mixed>  $params
     * @return array{hook:array<string,mixed>, token:string, url:string}
     */
    public function create(Organization $organization, User $user, Service $service, string $name, string $action, array $params, CommandContext $context): array
    {
        if (! in_array($action, self::ALLOWED, true)) {
            throw new DomainError('action_param_invalid', 'action must be one of '.implode(', ', self::ALLOWED).'.', 422, ['field' => 'action']);
        }
        if (! in_array($action, $this->features->actions($service), true)) {
            throw new DomainError('feature_unavailable', "The service does not offer {$action}.", 422);
        }
        $params = CustomerActionParams::filter($action, $params); // a stored action is a customer's words too (H21)
        $permission = ServiceActionCommand::permissionFor($action, $params); // the action's own permission, as the bus asks it (TASK-0029 D29.7)
        if (ServiceActionCommand::needsFreshStepUp($action)) {
            throw new DomainError('step_up_required', 'Tato akce vyžaduje čerstvé ověření totožnosti; hook ho dát nemůže.', 403, ['requirement' => 'step_up']);
        }
        if (! $this->authorizer->can($user, $permission, CommandScope::organization($organization->id))) {
            throw DomainError::forbidden("Missing permission {$permission}");
        }
        if (ActionHook::query()->where('organization_id', $organization->id)->count() >= 50) {
            throw new DomainError('hook_limit', 'At most 50 action hooks per organization.', 422);
        }
        $token = 'ahk_'.Str::random(40);
        $hook = ActionHook::query()->create(['organization_id' => $organization->id, 'service_id' => $service->id, 'created_by' => $user->id, 'name' => mb_substr(trim($name), 0, 80) ?: $action, 'action' => $action, 'params' => $params, 'token_hash' => hash('sha256', $token), 'enabled' => true]);
        $this->audit->record($context->withScope($organization->id), 'integration.hook.create', 'succeeded', ['action' => $action, 'name' => $hook->name], 'action_hook', $hook->id);

        return ['hook' => $this->present($hook), 'token' => $token, 'url' => $this->url($token)];
    }

    public function delete(Organization $organization, string $hookId, CommandContext $context): void
    {
        $hook = ActionHook::query()->where('organization_id', $organization->id)->find($hookId);
        if ($hook === null) {
            throw DomainError::notFound('action hook');
        }
        $hook->delete();
        $this->audit->record($context->withScope($organization->id), 'integration.hook.delete', 'succeeded', ['action' => $hook->action], 'action_hook', $hook->id);
    }

    /**
     * A call from outside: run the hook's action as its creator.
     *
     * @return array{accepted:bool, reason:?string, operation_id:?string, state:?string, hook:string}
     */
    public function trigger(string $token, ?string $ip): array
    {
        $hook = preg_match('/^ahk_[A-Za-z0-9]{40}$/', $token) ? ActionHook::query()->where('token_hash', hash('sha256', $token))->first() : null;
        if ($hook === null) {
            throw DomainError::notFound('action hook');
        }
        if (! $hook->enabled) {
            return ['accepted' => false, 'reason' => 'hook_disabled', 'operation_id' => null, 'state' => null, 'hook' => $hook->name];
        }
        $service = Service::query()->find($hook->service_id);
        $user = $hook->created_by ? User::query()->find($hook->created_by) : null;
        if ($service === null || $user === null) {
            $hook->forceFill(['enabled' => false, 'last_result' => 'stale'])->save();

            return ['accepted' => false, 'reason' => 'hook_stale', 'operation_id' => null, 'state' => null, 'hook' => $hook->name];
        }
        // the creator must be a member NOW (TASK-0035, IF-15): a binding somewhere else (a staff role) is no way back into an
        // organization the person left; the hook is switched off as the listener would have done it
        if (! OrganizationMembership::query()->where('organization_id', $hook->organization_id)->where('user_id', $user->id)->current()->exists()) {
            $this->disable($hook, 'member_removed', CommandContext::system('action hook of a person who is no member'));

            return ['accepted' => false, 'reason' => 'hook_disabled', 'operation_id' => null, 'state' => null, 'hook' => $hook->name];
        }
        // The hook asks the permission of its own action, as the bus would (TASK-0029 D29.7): an action nobody mapped any more is
        // not run, and one that needs a fresh step-up (a staging.push stored by an older release) cannot be run by a URL. The hook
        // stays enabled and says why, so its owner sees it in the list.
        $action = (string) $hook->action;
        $refused = null;
        $params = [];
        $permission = null;
        try {
            $params = CustomerActionParams::filter($action, (array) $hook->params); // hooks stored before the filter existed are read through it as well
            $permission = ServiceActionCommand::permissionFor($action, $params);
        } catch (DomainError $e) {
            $refused = $e->error;
        }
        if ($refused === null && ServiceActionCommand::needsFreshStepUp($action)) {
            $refused = 'step_up_required';
        }
        if ($refused === null && ! $this->authorizer->can($user, (string) $permission, CommandScope::organization($service->organization_id))) {
            $refused = 'forbidden';
        }
        if ($refused !== null) {
            $hook->forceFill(['last_result' => mb_substr($refused, 0, 40)])->save();

            return ['accepted' => false, 'reason' => $refused, 'operation_id' => null, 'state' => null, 'hook' => $hook->name];
        }
        $context = new CommandContext('user', $user->id, $service->organization_id, null, $ip, 'action-hook', 'hook:'.$hook->id, 'action hook '.$hook->name);
        try {
            $operation = $this->services->requestAction($service, $action, $context, $this->burstKey($hook), $params, authorizedPermission: $permission); // the run re-checks the same permission (H315)
        } catch (DomainError $e) {
            $hook->forceFill(['uses' => $hook->uses + 1, 'last_used_at' => now(), 'last_result' => mb_substr($e->error, 0, 40)])->save();
            $this->audit->record($context, 'integration.hook.trigger', 'failed', ['action' => $hook->action, 'error' => $e->error], 'action_hook', $hook->id);

            return ['accepted' => false, 'reason' => $e->error, 'operation_id' => null, 'state' => null, 'hook' => $hook->name];
        }
        $hook->forceFill(['uses' => $hook->uses + 1, 'last_used_at' => now(), 'last_operation_id' => $operation->id, 'last_result' => 'accepted'])->save();
        $this->audit->record($context, 'integration.hook.trigger', 'succeeded', ['action' => $hook->action, 'operation_id' => $operation->id], 'action_hook', $hook->id);

        return ['accepted' => true, 'reason' => null, 'operation_id' => $operation->id, 'state' => $operation->state, 'hook' => $hook->name];
    }

    /**
     * The idempotency key of a run: every trigger within BURST_SECONDS of the FIRST one shares it, so a burst is one operation.
     * A sliding window opened by Cache::add (atomic: one request wins the window, the others read its token); a fixed
     * clock bucket (intdiv(time(), 10)) split two requests a few milliseconds apart whenever a boundary fell between them.
     */
    private function burstKey(ActionHook $hook): string
    {
        $cacheKey = 'action-hook:burst:'.$hook->id;
        $token = Str::random(16);
        if (Cache::add($cacheKey, $token, self::BURST_SECONDS)) {
            return 'hook:'.$hook->id.':'.$token;
        }

        return 'hook:'.$hook->id.':'.(string) (Cache::get($cacheKey) ?? $token); // expired between add and get: this run opens the window
    }

    /**
     * Enabled hooks whose creator could no longer make them (TASK-0035, IF-15 / audit G1): not a current member of the
     * organization, or a role without the permission of the hook's action. A trigger already asked the creator's permission,
     * but the hook stayed enabled — a URL waiting for the day its creator is let back in, or given the role again. Narrowed to
     * one person by the listener, over every organization by the one-off operator:integrations:orphan-links command.
     *
     * @return list<array{hook:ActionHook, reason:string}>
     */
    public function orphans(?string $organizationId = null, ?string $userId = null): array
    {
        $hooks = ActionHook::query()->where('enabled', true)
            ->when($organizationId !== null, fn ($q) => $q->where('organization_id', $organizationId))
            ->when($userId !== null, fn ($q) => $q->where('created_by', $userId))
            ->orderBy('created_at')->limit(5000)->get();
        $out = [];
        foreach ($hooks as $hook) {
            $reason = $this->orphanReason($hook);
            if ($reason !== null) {
                $out[] = ['hook' => $hook, 'reason' => $reason];
            }
        }

        return $out;
    }

    /** Switched off, never deleted: the owner sees in the list which hook stopped and why, and makes a new one if it is still wanted. */
    public function disable(ActionHook $hook, string $reason, CommandContext $context): void
    {
        if (! $hook->enabled) {
            return;
        }
        $hook->forceFill(['enabled' => false, 'last_result' => mb_substr($reason, 0, 40)])->save();
        $this->audit->record($context->withScope($hook->organization_id), 'integration.hook.disable', 'succeeded', ['reason' => $reason, 'created_by' => $hook->created_by, 'action' => $hook->action], 'action_hook', $hook->id);
    }

    private function orphanReason(ActionHook $hook): ?string
    {
        $user = $hook->created_by ? User::query()->find($hook->created_by) : null;
        $member = $user !== null && OrganizationMembership::query()->where('organization_id', $hook->organization_id)->where('user_id', $user->id)->current()->exists();
        if (! $member) {
            return 'member_removed';
        }
        $this->authorizer->forget($user);
        try {
            $permission = ServiceActionCommand::permissionFor((string) $hook->action, CustomerActionParams::filter((string) $hook->action, (array) $hook->params));
        } catch (DomainError) {
            return null; // an action nobody maps any more is refused by the trigger and says so there; it is not a question of who made it
        }

        return $this->authorizer->can($user, $permission, CommandScope::organization($hook->organization_id)) ? null : 'role_changed';
    }

    public function url(string $token): string
    {
        return rtrim((string) config('onhost.portal_url', config('app.url')), '/').'/v1/hooks/run/'.$token;
    }

    /** @return array<string,mixed> */
    private function present(ActionHook $h): array
    {
        return ['id' => $h->id, 'service_id' => $h->service_id, 'name' => $h->name, 'action' => $h->action, 'params' => (array) $h->params, 'enabled' => (bool) $h->enabled, 'uses' => $h->uses, 'last_used_at' => $h->last_used_at?->toIso8601String(), 'last_result' => $h->last_result, 'created_at' => $h->created_at?->toIso8601String()];
    }
}
