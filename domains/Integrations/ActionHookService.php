<?php

declare(strict_types=1);

namespace Onhost\Domain\Integrations;

use Illuminate\Support\Str;
use Onhost\Domain\Identity\Authorization\Authorizer;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Integrations\Models\ActionHook;
use Onhost\Domain\Organizations\Models\Organization;
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
    public const ALLOWED = ['backup', 'power', 'deploy.run', 'staging.refresh', 'staging.push', 'wp.update', 'wp.cache', 'cdn.purge', 'cron.run', 'ssl.issue', 'https.force', 'php.set', 'redirect.set', 'monitoring.set'];

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
        if (! $this->authorizer->can($user, 'service.manage', CommandScope::organization($organization->id))) {
            throw DomainError::forbidden('Missing permission service.manage');
        }
        if (ActionHook::query()->where('organization_id', $organization->id)->count() >= 50) {
            throw new DomainError('hook_limit', 'At most 50 action hooks per organization.', 422);
        }
        $params = CustomerActionParams::filter($action, $params); // a stored action is a customer's words too (H21)
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
        if (! $this->authorizer->can($user, 'service.manage', CommandScope::organization($service->organization_id))) {
            $hook->forceFill(['last_result' => 'forbidden'])->save();

            return ['accepted' => false, 'reason' => 'forbidden', 'operation_id' => null, 'state' => null, 'hook' => $hook->name];
        }
        $context = new CommandContext('user', $user->id, $service->organization_id, null, $ip, 'action-hook', 'hook:'.$hook->id, 'action hook '.$hook->name);
        try {
            $operation = $this->services->requestAction($service, $hook->action, $context, 'hook:'.$hook->id.':'.intdiv(time(), 10), CustomerActionParams::filter((string) $hook->action, (array) $hook->params)); // hooks stored before the filter existed are read through it as well
        } catch (DomainError $e) {
            $hook->forceFill(['uses' => $hook->uses + 1, 'last_used_at' => now(), 'last_result' => mb_substr($e->error, 0, 40)])->save();
            $this->audit->record($context, 'integration.hook.trigger', 'failed', ['action' => $hook->action, 'error' => $e->error], 'action_hook', $hook->id);

            return ['accepted' => false, 'reason' => $e->error, 'operation_id' => null, 'state' => null, 'hook' => $hook->name];
        }
        $hook->forceFill(['uses' => $hook->uses + 1, 'last_used_at' => now(), 'last_operation_id' => $operation->id, 'last_result' => 'accepted'])->save();
        $this->audit->record($context, 'integration.hook.trigger', 'succeeded', ['action' => $hook->action, 'operation_id' => $operation->id], 'action_hook', $hook->id);

        return ['accepted' => true, 'reason' => null, 'operation_id' => $operation->id, 'state' => $operation->state, 'hook' => $hook->name];
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
