<?php

declare(strict_types=1);

namespace Onhost\Domain\Provisioning\Workflows;

use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Domain\Provisioning\Workflow\StepContext;
use Onhost\Domain\Provisioning\Workflow\StepResult;
use Onhost\Domain\Provisioning\Workflow\Workflow;
use Onhost\Domain\Services\Models\Deployment;
use Onhost\Domain\Services\ServiceFeatures;
use Onhost\Domain\Services\Web\CommandRunner;
use Onhost\Domain\Services\Web\DeployService;
use Onhost\Providers\Contracts\WebToolsProvider;
use Onhost\Providers\Shell\Q;

/**
 * One deployment: prepare (record, agent, deploy key on the node) → fetch (git clone of the ref into a release
 * folder) → build (the customer's build command) → switch (run path on aaPanel, web symlink on ISPConfig, rsync
 * where symlinks are not allowed) → hooks → prune old releases. Rollback switches to a kept release. Every step
 * appends to the deployment log; a failure leaves the previous release serving.
 */
final class DeployWorkflow implements Workflow
{
    public static function kind(): string
    {
        return 'service.deploy';
    }

    public function queue(Operation $operation): string
    {
        $instance = $operation->provider_instance_id ? ProviderInstance::query()->find($operation->provider_instance_id) : null;

        return 'provider-'.($instance?->provider ?? 'default');
    }

    public function steps(Operation $operation): array
    {
        return (string) data_get($operation->desired, 'action') === 'deploy.rollback'
            ? [$this->prepareStep(), $this->rollbackStep()]
            : [$this->prepareStep(), $this->fetchStep(), $this->buildStep(), $this->switchStep(), $this->hooksStep(), $this->pruneStep()];
    }

    public function compensate(StepContext $context): void
    {
        $deployment = Deployment::query()->where('operation_id', $context->operation->id)->first();
        if ($deployment !== null && in_array($deployment->state, ['running', 'queued'], true)) {
            $context->container->make(DeployService::class)->finish($deployment, false, (string) $context->get('log', '')."\nFAILED: ".(string) ($context->operation->error['message'] ?? 'deployment failed'));
        }
    }

    private function prepareStep(): ServiceStep
    {
        return new class extends ServiceStep
        {
            public function label(): string
            {
                return 'Příprava deploye';
            }

            public function run(StepContext $context): StepResult
            {
                $service = $this->service($context);
                $deploy = $context->container->make(DeployService::class);
                $source = $deploy->source($service);
                $deployment = $deploy->open($service, $context->operation, (array) $context->operation->desired);
                $tools = $this->capability($context, WebToolsProvider::class);
                $ref = $this->ref($context);
                if (! $tools->shellAvailable($ref)) {
                    $agent = $tools->ensureAgent($ref);
                    if ($agent->isAsync() && $agent->async !== null) {
                        return StepResult::wait($agent->async, ['deployment_id' => $deployment->id, 'log' => 'agent user being created']);
                    }
                }
                $transport = $tools->transport($ref);
                $base = $context->instance()->provider === 'aapanel' ? '.onhost' : '../private/.onhost';
                $transport->mkdir($base);
                $transport->write($base.'/deploy_key', $deploy->privateKey($service)."\n");
                $transport->chmod($base.'/deploy_key', 0600);
                $transport->mkdir($base.'/releases');
                $release = now()->format('YmdHis').'-'.substr(preg_replace('/[^A-Za-z0-9]/', '', (string) ($deployment->ref ?? $source->branch)) ?: 'head', 0, 12);

                return StepResult::done(['deployment_id' => $deployment->id, 'source_id' => $source->id, 'base' => $base, 'release' => $release, 'log' => "release {$release}\n"]);
            }
        };
    }

    private function fetchStep(): ServiceStep
    {
        return new class extends ServiceStep
        {
            public function label(): string
            {
                return 'Stažení z repozitáře';
            }

            public function run(StepContext $context): StepResult
            {
                $tools = $this->capability($context, WebToolsProvider::class);
                $ref = $this->ref($context);
                $source = $context->container->make(DeployService::class)->source($this->service($context));
                $deployment = Deployment::query()->findOrFail((string) $context->get('deployment_id'));
                $root = $tools->transport($ref)->root();
                $base = self::abs($root, (string) $context->get('base'));
                $release = $base.'/releases/'.$context->get('release');
                $wanted = (string) ($deployment->ref ?: $source->branch);
                $isSha = (bool) preg_match('/^[0-9a-f]{7,40}$/', $wanted);
                $git = 'GIT_SSH_COMMAND='.Q::arg('ssh -i '.$base.'/deploy_key -o StrictHostKeyChecking=accept-new -o IdentitiesOnly=yes').' git';
                $clone = $isSha
                    ? "{$git} clone --quiet ".Q::arg($source->clone_url).' '.Q::arg($release).' && cd '.Q::arg($release)." && {$git} checkout --quiet ".Q::arg($wanted)
                    : "{$git} clone --quiet --depth 1 --branch ".Q::arg($wanted).' '.Q::arg($source->clone_url).' '.Q::arg($release);
                $run = $tools->shell($ref)->run($clone.' && cd '.Q::arg($release).' && git rev-parse HEAD && git log -1 --pretty=%s && rm -rf .git', ['timeout' => 600, 'user' => $tools->siteUser($ref)]);
                $log = (string) $context->get('log', '')."$ git clone {$source->repository} ({$wanted})\n".$run->output()."\n";
                if (! $run->ok()) {
                    $context->container->make(DeployService::class)->finish($deployment, false, $log);

                    return StepResult::fail('git clone failed: '.mb_substr($run->output(), 0, 300), false, ['log' => $log]);
                }
                $lines = preg_split('/\r?\n/', trim($run->stdout)) ?: [];
                $message = trim((string) array_pop($lines));
                $sha = trim((string) array_pop($lines));
                $deployment->forceFill(['sha' => preg_match('/^[0-9a-f]{40}$/', $sha) ? $sha : null, 'message' => $deployment->message ?: mb_substr($message, 0, 250), 'release' => (string) $context->get('release')])->save();

                return StepResult::done(['log' => $log, 'release_path' => $release, 'sha' => $sha]);
            }

            private static function abs(string $root, string $base): string
            {
                return str_starts_with($base, '../') ? dirname($root).'/'.substr($base, 3) : $root.'/'.$base;
            }
        };
    }

    private function buildStep(): ServiceStep
    {
        return new class extends ServiceStep
        {
            public function label(): string
            {
                return 'Build';
            }

            public function run(StepContext $context): StepResult
            {
                $source = $context->container->make(DeployService::class)->source($this->service($context));
                $log = (string) $context->get('log', '');
                if ((string) $source->build_command === '') {
                    return StepResult::done(['log' => $log."(no build command)\n"]);
                }
                $tools = $this->capability($context, WebToolsProvider::class);
                $ref = $this->ref($context);
                $run = $tools->shell($ref)->run(CommandRunner::guard((string) $source->build_command), ['cwd' => (string) $context->get('release_path'), 'user' => $tools->siteUser($ref), 'timeout' => 900, 'env' => (array) ($source->env ?? [])]);
                $log .= "$ {$source->build_command}\n".$run->output()."\n";
                if (! $run->ok()) {
                    $context->container->make(DeployService::class)->finish(Deployment::query()->findOrFail((string) $context->get('deployment_id')), false, $log);

                    return StepResult::fail('build failed (exit '.$run->exitCode.')', false, ['log' => $log]);
                }

                return StepResult::done(['log' => $log]);
            }
        };
    }

    private function switchStep(): ServiceStep
    {
        return new class extends ServiceStep
        {
            public function label(): string
            {
                return 'Přepnutí release';
            }

            public function run(StepContext $context): StepResult
            {
                $tools = $this->capability($context, WebToolsProvider::class);
                $ref = $this->ref($context);
                $source = $context->container->make(DeployService::class)->source($this->service($context));
                $release = (string) $context->get('release_path');
                $serve = $release.((string) $source->deploy_path !== '' ? '/'.trim((string) $source->deploy_path, '/') : '');
                $log = (string) $context->get('log', '');
                if ($context->instance()->provider === 'aapanel') {
                    $root = $tools->transport($ref)->root();
                    $tools->setDocumentRoot($ref, substr($serve, strlen($root)));
                    $log .= 'run path → '.substr($serve, strlen($root))."\n";
                } else {
                    $log .= $this->switchSymlink($tools, $ref, $serve, $context);
                }
                $deployment = Deployment::query()->findOrFail((string) $context->get('deployment_id'));
                $deployment->forceFill(['release' => (string) $context->get('release')])->save();

                return StepResult::done(['log' => $log, 'serve' => $serve]);
            }

            private function switchSymlink(WebToolsProvider $tools, $ref, string $serve, StepContext $context): string
            {
                $shell = $tools->shell($ref);
                $strategy = (string) $context->instance()->option('deploy_strategy', 'symlink');
                if ($strategy === 'rsync') {
                    $run = $shell->run('rsync -a --delete --exclude .onhost '.Q::arg(rtrim($serve, '/').'/').' "$HOME/web/"', ['timeout' => 900]);

                    return "rsync → web/\n".$run->output()."\n";
                }
                $script = 'cd "$HOME" && if [ -d web ] && [ ! -L web ]; then mkdir -p private/.onhost/releases && mv web private/.onhost/releases/initial-'.now()->format('YmdHis').'; fi && ln -sfn '.Q::arg($serve).' web.tmp && mv -T web.tmp web && readlink web';
                $run = $shell->run($script, ['timeout' => 120]);
                if (! $run->ok()) {
                    $fallback = $shell->run('rm -f "$HOME/web.tmp"; mkdir -p "$HOME/web" && rsync -a --delete --exclude .onhost '.Q::arg(rtrim($serve, '/').'/').' "$HOME/web/"', ['timeout' => 900]);

                    return 'symlink refused ('.trim($run->output())."), copied instead\n".$fallback->output()."\n";
                }

                return 'web → '.trim($run->stdout)."\n";
            }
        };
    }

    private function hooksStep(): ServiceStep
    {
        return new class extends ServiceStep
        {
            public function label(): string
            {
                return 'Post-deploy kroky';
            }

            public function run(StepContext $context): StepResult
            {
                $source = $context->container->make(DeployService::class)->source($this->service($context));
                $tools = $this->capability($context, WebToolsProvider::class);
                $ref = $this->ref($context);
                $log = (string) $context->get('log', '');
                foreach ((array) ($source->hooks ?? []) as $hook) {
                    $run = $tools->shell($ref)->run(CommandRunner::guard((string) $hook), ['cwd' => (string) $context->get('serve'), 'user' => $tools->siteUser($ref), 'timeout' => 300, 'env' => (array) ($source->env ?? [])]);
                    $log .= "$ {$hook}\n".$run->output()."\n";
                    if (! $run->ok()) {
                        $log .= "(hook exited with {$run->exitCode}; the release stays live)\n";
                    }
                }
                $context->container->make(DeployService::class)->finish(Deployment::query()->findOrFail((string) $context->get('deployment_id')), true, $log, (string) $context->get('sha') ?: null, (string) $context->get('release'));
                $context->container->make(ServiceFeatures::class)->forget($this->service($context));

                return StepResult::done(['log' => $log]);
            }
        };
    }

    private function pruneStep(): ServiceStep
    {
        return new class extends ServiceStep
        {
            public function label(): string
            {
                return 'Úklid starých release';
            }

            public function run(StepContext $context): StepResult
            {
                $source = $context->container->make(DeployService::class)->source($this->service($context));
                $tools = $this->capability($context, WebToolsProvider::class);
                $ref = $this->ref($context);
                $root = $tools->transport($ref)->root();
                $base = (string) $context->get('base');
                $releases = (str_starts_with($base, '../') ? dirname($root).'/'.substr($base, 3) : $root.'/'.$base).'/releases';
                $keep = max(2, (int) $source->keep_releases);
                $tools->shell($ref)->run('cd '.Q::arg($releases).' 2>/dev/null && ls -1t | tail -n +'.($keep + 1).' | xargs -r rm -rf', ['timeout' => 300]);
                Deployment::query()->where('service_id', $this->service($context)->id)->where('state', 'succeeded')->orderByDesc('finished_at')->skip($keep)->take(100)->get()->each(fn (Deployment $d) => $d->forceFill(['release' => null])->save());

                return StepResult::done(['pruned' => true]);
            }
        };
    }

    private function rollbackStep(): ServiceStep
    {
        return new class extends ServiceStep
        {
            public function label(): string
            {
                return 'Rollback na dřívější release';
            }

            public function run(StepContext $context): StepResult
            {
                $service = $this->service($context);
                $target = Deployment::query()->where('service_id', $service->id)->find((string) $context->desired('deployment_id'));
                $current = Deployment::query()->findOrFail((string) $context->get('deployment_id'));
                if ($target === null || (string) $target->release === '') {
                    $context->container->make(DeployService::class)->finish($current, false, 'the release to roll back to is no longer kept on the node');

                    return StepResult::fail('release not available', false);
                }
                $tools = $this->capability($context, WebToolsProvider::class);
                $ref = $this->ref($context);
                $source = $context->container->make(DeployService::class)->source($service);
                $root = $tools->transport($ref)->root();
                $base = (string) $context->get('base');
                $release = (str_starts_with($base, '../') ? dirname($root).'/'.substr($base, 3) : $root.'/'.$base).'/releases/'.$target->release;
                $serve = $release.((string) $source->deploy_path !== '' ? '/'.trim((string) $source->deploy_path, '/') : '');
                if (! $tools->shell($ref)->run('test -d '.Q::arg($serve), ['timeout' => 20])->ok()) {
                    $context->container->make(DeployService::class)->finish($current, false, "release folder {$target->release} is missing on the node");

                    return StepResult::fail('release folder missing', false);
                }
                if ($context->instance()->provider === 'aapanel') {
                    $tools->setDocumentRoot($ref, substr($serve, strlen($root)));
                } else {
                    $tools->shell($ref)->run('cd "$HOME" && ln -sfn '.Q::arg($serve).' web.tmp && mv -T web.tmp web', ['timeout' => 60]);
                }
                $current->forceFill(['sha' => $target->sha, 'message' => 'rollback to '.$target->release])->save();
                $context->container->make(DeployService::class)->finish($current, true, "rolled back to {$target->release}\n", $target->sha, $target->release);

                return StepResult::done(['release' => $target->release]);
            }
        };
    }
}
