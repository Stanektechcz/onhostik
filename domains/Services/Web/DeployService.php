<?php

declare(strict_types=1);

namespace Onhost\Domain\Services\Web;

use Illuminate\Support\Str;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Services\Models\Deployment;
use Onhost\Domain\Services\Models\DeploySource;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\ServiceFeatures;
use Onhost\Domain\Services\ServiceService;
use Onhost\Platform\Audit\AuditRecorder;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Events\GenericEvent;
use Onhost\Platform\Outbox\OutboxPublisher;
use Onhost\Platform\Secrets\SecretRef;
use Onhost\Platform\Secrets\SecretStore;
use phpseclib3\Crypt\EC;

/**
 * Git deploy for a site: the customer connects a repository (GitHub, GitLab or any SSH remote) with a deploy key the
 * platform generates, an optional build command and post-deploy hooks; pushes arrive on a signed webhook and every
 * deployment is a release folder on the node switched atomically (run path on aaPanel, web symlink on ISPConfig),
 * so rollback is a switch back. The workflow (DeployWorkflow) does the node work; this service keeps the records.
 */
final class DeployService
{
    public function __construct(private readonly SecretStore $secrets, private readonly AuditRecorder $audit, private readonly OutboxPublisher $outbox, private readonly ServiceFeatures $features) {}

    /**
     * `$withSecrets = false` is what a read-only role gets (H334): the build environment keeps its names and loses its
     * values — they are API keys and database URLs far more often than not.
     *
     * @return array<string,mixed>
     */
    public function status(Service $service, bool $withSecrets = true): array
    {
        $source = DeploySource::query()->where('service_id', $service->id)->first();
        $last = $source?->last_deployment_id ? Deployment::query()->find($source->last_deployment_id) : null;

        return [
            'configured' => $source !== null,
            'source' => $source === null ? null : self::masked($this->present($source), $withSecrets),
            'last_deployment' => $last === null ? null : $this->presentDeployment($last),
            'webhook_url' => $source === null ? null : $this->webhookUrl($source),
            'strategy' => (string) data_get($service->desired_spec, 'executor', '') === 'aapanel' ? 'run_path' : 'symlink',
        ];
    }

    /** @return list<array<string,mixed>> */
    public function deployments(Service $service, int $limit = 20): array
    {
        return Deployment::query()->where('service_id', $service->id)->orderByDesc('created_at')->limit($limit)->get()->map(fn (Deployment $d) => $this->presentDeployment($d))->all();
    }

    /**
     * Connect or update the repository; returns the source plus, on first connection, the webhook secret shown once.
     *
     * @param  array<string,mixed>  $input
     * @return array{source:array<string,mixed>, webhook_secret:?string, public_key:string}
     */
    public function configure(Service $service, array $input, CommandContext $context): array
    {
        if (empty($this->features->features($service)['deploy']['enabled'])) {
            throw new DomainError('feature_unavailable', 'Git deploy is not available for this service.', 422);
        }
        $repository = trim((string) ($input['repository'] ?? ''));
        [$provider, $cloneUrl, $repoLabel] = self::parseRepository($repository);
        // what is stored has the length of its column: the API took a repository of 300 and a build command of 2 000 characters
        // for columns of 250 / 500 — SQLite did not mind, PostgreSQL answers 500. The limit is said here, where the assistant and
        // the CLI pass through as well.
        if (strlen($repoLabel) > 250 || strlen($cloneUrl) > 500) {
            throw new DomainError('action_param_invalid', 'The repository address is too long (250 characters at most).', 422, ['field' => 'repository']);
        }
        $branch = trim((string) ($input['branch'] ?? 'main'));
        if (! preg_match('#^[A-Za-z0-9._/-]{1,120}$#', $branch)) {
            throw new DomainError('action_param_invalid', 'branch must be a git branch name.', 422, ['field' => 'branch']);
        }
        $build = trim((string) ($input['build_command'] ?? ''));
        if (strlen($build) > 500) {
            throw new DomainError('action_param_invalid', 'The build command is too long (500 characters at most); put a longer one into a script in the repository.', 422, ['field' => 'build_command']);
        }
        if ($build !== '') {
            CommandRunner::guard($build);
        }
        $hooks = [];
        foreach ((array) ($input['hooks'] ?? []) as $hook) {
            $hook = trim((string) $hook);
            if ($hook !== '') {
                $hooks[] = CommandRunner::guard($hook);
            }
        }
        $env = [];
        foreach ((array) ($input['env'] ?? []) as $k => $v) {
            if (preg_match('/^[A-Z_][A-Z0-9_]*$/', (string) $k)) {
                $env[(string) $k] = substr((string) $v, 0, 1000);
            }
        }
        $deployPath = trim(preg_replace('~[^\w\/.-]~', '', str_replace('\\', '/', (string) ($input['deploy_path'] ?? ''))) ?? '', '/');
        if (str_contains($deployPath, '..')) {
            throw new DomainError('action_param_invalid', 'deploy_path must stay inside the repository.', 422, ['field' => 'deploy_path']);
        }

        $source = DeploySource::query()->where('service_id', $service->id)->first();
        $secretShown = null;
        if ($source === null) {
            $key = EC::createKey('Ed25519');
            $public = trim($key->getPublicKey()->toString('OpenSSH', ['comment' => 'onhost-deploy-'.$service->id]));
            $this->secrets->write(SecretRef::parse("db://services/{$service->id}/deploy-key"), ['private' => $key->toString('OpenSSH'), 'public' => $public]);
            $secretShown = Str::random(40);
            $this->secrets->write(SecretRef::parse("db://services/{$service->id}/deploy-webhook"), ['secret' => $secretShown]);
            $source = new DeploySource(['service_id' => $service->id, 'organization_id' => $service->organization_id, 'public_key' => $public, 'webhook_secret_hash' => hash('sha256', $secretShown)]);
        }
        $source->forceFill([
            'provider' => $provider, 'repository' => $repoLabel, 'clone_url' => $cloneUrl, 'branch' => $branch, 'build_command' => $build !== '' ? $build : null, 'deploy_path' => $deployPath !== '' ? $deployPath : null,
            'env' => $env, 'hooks' => $hooks, 'auto_deploy' => array_key_exists('auto_deploy', $input) ? filter_var($input['auto_deploy'], FILTER_VALIDATE_BOOLEAN) : ($source->auto_deploy ?? true), 'keep_releases' => max(2, min(20, (int) ($input['keep_releases'] ?? $source->keep_releases ?? 5))),
        ])->save();
        $this->audit->record($context->withScope($service->organization_id), 'service.deploy.configure', 'succeeded', ['repository' => $repoLabel, 'branch' => $branch], 'service', $service->id);

        return ['source' => $this->present($source), 'webhook_secret' => $secretShown, 'public_key' => (string) $source->public_key];
    }

    /** A new webhook secret (the old one stops working at once); shown once. */
    public function rotateWebhookSecret(Service $service, CommandContext $context): string
    {
        $source = $this->source($service);
        $secret = Str::random(40);
        $this->secrets->write(SecretRef::parse("db://services/{$service->id}/deploy-webhook"), ['secret' => $secret]);
        $source->forceFill(['webhook_secret_hash' => hash('sha256', $secret)])->save();
        $this->audit->record($context->withScope($service->organization_id), 'service.deploy.rotate_secret', 'succeeded', [], 'service', $service->id);

        return $secret;
    }

    public function disconnect(Service $service, CommandContext $context): void
    {
        $source = DeploySource::query()->where('service_id', $service->id)->first();
        if ($source === null) {
            return;
        }
        if ((string) data_get($service->desired_spec, 'executor', '') === 'aapanel' && $source->last_deployment_id !== null) {
            try { // the site goes back to serving its root; the release folders stay until the customer removes them
                [$tools, $ref] = $this->features->toolsFor($service);
                $tools->setDocumentRoot($ref, '/');
            } catch (\Throwable) {
                // the node may be unreachable; the run path can be reset from the files tab
            }
        }
        foreach (['deploy-key', 'deploy-webhook'] as $name) {
            try {
                $this->secrets->write(SecretRef::parse("db://services/{$service->id}/{$name}"), []);
            } catch (\Throwable) {
                // nothing to clear
            }
        }
        $source->delete();
        $this->audit->record($context->withScope($service->organization_id), 'service.deploy.disconnect', 'succeeded', [], 'service', $service->id);
    }

    /**
     * A push arrived: verify the signature, match the branch, start a deployment.
     *
     * @return array{accepted:bool, reason:string, deployment_id:?string}
     */
    public function webhook(DeploySource $source, string $payload, ?string $signature, string $event, ServiceService $services): array
    {
        $secret = (string) ($this->secrets->read(SecretRef::parse("db://services/{$source->service_id}/deploy-webhook"))['secret'] ?? '');
        $expected = 'sha256='.hash_hmac('sha256', $payload, $secret);
        if ($secret === '' || $signature === null || ! hash_equals($expected, trim($signature))) {
            throw new DomainError('webhook_signature_invalid', 'The webhook signature does not match.', 401);
        }
        if (! $source->auto_deploy) {
            return ['accepted' => false, 'reason' => 'auto_deploy_off', 'deployment_id' => null];
        }
        $body = json_decode($payload, true);
        $body = is_array($body) ? $body : [];
        if (! in_array($event, ['push', 'Push Hook', 'ping'], true)) {
            return ['accepted' => false, 'reason' => "event {$event} ignored", 'deployment_id' => null];
        }
        if ($event === 'ping') {
            return ['accepted' => true, 'reason' => 'pong', 'deployment_id' => null];
        }
        $ref = (string) ($body['ref'] ?? '');
        if ($ref !== 'refs/heads/'.$source->branch) {
            return ['accepted' => false, 'reason' => "ref {$ref} is not the deploy branch", 'deployment_id' => null];
        }
        $service = Service::query()->findOrFail($source->service_id);
        $context = CommandContext::system('deploy-webhook');
        $operation = $services->requestAction($service, 'deploy.run', $context, 'deploy:webhook:'.$source->id.':'.substr((string) ($body['after'] ?? Str::random(8)), 0, 12), ['ref' => (string) ($body['after'] ?? ''), 'trigger' => 'webhook', 'message' => (string) ($body['head_commit']['message'] ?? ''), 'author' => (string) ($body['head_commit']['author']['name'] ?? ($body['pusher']['name'] ?? ''))]);

        return ['accepted' => true, 'reason' => 'deployment queued', 'deployment_id' => Deployment::query()->where('operation_id', $operation->id)->value('id')];
    }

    /** The deployment record a workflow works on (created by the prepare step). */
    public function open(Service $service, Operation $operation, array $desired): Deployment
    {
        $source = $this->source($service);
        $existing = Deployment::query()->where('operation_id', $operation->id)->first();
        if ($existing !== null) {
            return $existing;
        }
        $trigger = (string) ($desired['trigger'] ?? ($desired['action'] === 'deploy.rollback' ? 'rollback' : 'manual'));
        $deployment = Deployment::query()->create([
            'service_id' => $service->id, 'deploy_source_id' => $source->id, 'organization_id' => $service->organization_id, 'triggered_by' => $trigger, 'ref' => $desired['ref'] ?? $source->branch,
            'message' => isset($desired['message']) ? mb_substr((string) $desired['message'], 0, 250) : null, 'author' => isset($desired['author']) ? mb_substr((string) $desired['author'], 0, 120) : null, 'state' => 'running', 'operation_id' => $operation->id, 'started_at' => now(),
        ]);
        $this->outbox->publish(GenericEvent::of('deploy.started', 'service', $service->id, ['deployment_id' => $deployment->id, 'ref' => $deployment->ref, 'trigger' => $trigger], $service->organization_id));

        return $deployment;
    }

    public function finish(Deployment $deployment, bool $ok, string $log, ?string $sha = null, ?string $release = null): void
    {
        $deployment->forceFill(['state' => $ok ? 'succeeded' : 'failed', 'log' => mb_substr($log, -60000), 'sha' => $sha ?? $deployment->sha, 'release' => $release ?? $deployment->release, 'finished_at' => now()])->save();
        $source = DeploySource::query()->find($deployment->deploy_source_id);
        if ($ok && $source !== null) {
            $source->forceFill(['last_deployment_id' => $deployment->id, 'last_deployed_at' => now()])->save();
        }
        $this->outbox->publish(GenericEvent::of($ok ? 'deploy.succeeded' : 'deploy.failed', 'service', $deployment->service_id, ['deployment_id' => $deployment->id, 'ref' => $deployment->ref, 'sha' => $deployment->sha, 'release' => $deployment->release, 'error' => $ok ? null : mb_substr(trim(substr($log, -400)), 0, 400)], $deployment->organization_id));
    }

    public function source(Service $service): DeploySource
    {
        $source = DeploySource::query()->where('service_id', $service->id)->first();
        if ($source === null) {
            throw new DomainError('deploy_not_configured', 'Connect a repository first.', 409);
        }

        return $source;
    }

    public function privateKey(Service $service): string
    {
        return (string) ($this->secrets->read(SecretRef::parse("db://services/{$service->id}/deploy-key"))['private'] ?? '');
    }

    public function webhookUrl(DeploySource $source): string
    {
        return rtrim((string) config('onhost.portal_url', config('app.url')), '/').'/v1/hooks/deploy/'.$source->id;
    }

    /** @return array{0:string,1:string,2:string} provider, ssh clone url, label */
    public static function parseRepository(string $repository): array
    {
        $repository = trim($repository);
        // an https URL is kept as given: public repositories clone without the deploy key; owner/name and git@ forms use SSH with the site's key
        if (preg_match('#^https://github\.com/([A-Za-z0-9_.-]+)/([A-Za-z0-9_.-]+?)(?:\.git)?/?$#', $repository, $m)) {
            return ['github', "https://github.com/{$m[1]}/{$m[2]}.git", "{$m[1]}/{$m[2]}"];
        }
        if (preg_match('#^https://gitlab\.com/([A-Za-z0-9_.\/-]+?)(?:\.git)?/?$#', $repository, $m)) {
            return ['gitlab', "https://gitlab.com/{$m[1]}.git", $m[1]];
        }
        if (preg_match('#^(?:git@github\.com:)?([A-Za-z0-9_.-]+)/([A-Za-z0-9_.-]+?)(?:\.git)?/?$#', $repository, $m)) {
            return ['github', "git@github.com:{$m[1]}/{$m[2]}.git", "{$m[1]}/{$m[2]}"];
        }
        if (preg_match('#^(?:gitlab\.com/|git@gitlab\.com:)([A-Za-z0-9_.\/-]+?)(?:\.git)?/?$#', $repository, $m)) {
            return ['gitlab', "git@gitlab.com:{$m[1]}.git", $m[1]];
        }
        if (preg_match('#^https://[A-Za-z0-9.-]+/[A-Za-z0-9_.\/-]+?(?:\.git)?/?$#', $repository)) {
            return ['generic', $repository, preg_replace('#^https://[^/]+/#', '', rtrim($repository, '/')) ?? $repository];
        }
        if (preg_match('#^(?:ssh://)?[A-Za-z0-9_.-]+@[A-Za-z0-9.-]+(?::\d+)?[:/][A-Za-z0-9_.\/-]+(?:\.git)?$#', $repository)) {
            return ['generic', $repository, preg_replace('#^.*[:/]([^/]+/[^/]+?)(?:\.git)?$#', '$1', $repository) ?? $repository];
        }
        throw new DomainError('action_param_invalid', 'repository must be owner/name on GitHub, a GitLab path or an SSH clone URL.', 422, ['field' => 'repository']);
    }

    /**
     * @param  array<string,mixed>  $source
     * @return array<string,mixed>
     */
    private static function masked(array $source, bool $withSecrets): array
    {
        if (! $withSecrets) {
            $source['env'] = array_map(fn () => null, (array) ($source['env'] ?? []));
            $source['env_hidden'] = true;
        }

        return $source;
    }

    /** @return array<string,mixed> */
    private function present(DeploySource $source): array
    {
        return [
            'id' => $source->id, 'provider' => $source->provider, 'repository' => $source->repository, 'clone_url' => $source->clone_url, 'branch' => $source->branch, 'public_key' => $source->public_key,
            'build_command' => $source->build_command, 'deploy_path' => $source->deploy_path, 'env' => (array) ($source->env ?? []), 'hooks' => (array) ($source->hooks ?? []), 'auto_deploy' => (bool) $source->auto_deploy,
            'keep_releases' => $source->keep_releases, 'last_deployed_at' => $source->last_deployed_at?->toIso8601String(), 'webhook_url' => $this->webhookUrl($source),
        ];
    }

    /** @return array<string,mixed> */
    private function presentDeployment(Deployment $d): array
    {
        return ['id' => $d->id, 'trigger' => $d->triggered_by, 'ref' => $d->ref, 'sha' => $d->sha, 'message' => $d->message, 'author' => $d->author, 'release' => $d->release, 'state' => $d->state, 'started_at' => $d->started_at?->toIso8601String(), 'finished_at' => $d->finished_at?->toIso8601String(), 'log' => $d->log, 'operation_id' => $d->operation_id];
    }
}
