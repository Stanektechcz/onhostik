<?php

declare(strict_types=1);

namespace Onhost\Domain\Provisioning\Workflows;

use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Domain\Provisioning\Workflow\AbstractStep;
use Onhost\Domain\Provisioning\Workflow\StepContext;
use Onhost\Domain\Provisioning\Workflow\StepResult;
use Onhost\Domain\Provisioning\Workflow\Workflow;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\VerifiedRestoreRun;
use Onhost\Domain\Services\Web\RestoreTarget;
use Onhost\Domain\Services\Web\VerifiedRestore;
use Onhost\Providers\Contracts\AsyncHandle;
use Throwable;

/**
 * W2-4 verified restore (`restore.verify`): copy → restore → checks → cleanup → protocol.
 *
 * The operation carries the production service in `desired.service_id` but is NOT bound to it (`service_id` stays null,
 * ServiceService::requestAction): a run waits for a site to be made and removed on the node, and a service lock held for that
 * long would refuse the customer's own backups and changes in the meantime. Nothing of the run writes to the production service —
 * it reads the set from the platform's backup disk and writes only into the copy. One run per service at a time is held by
 * `VerifiedRestore::assertCanStart` instead.
 *
 * No step fails the operation for a finding: a step that finds a problem writes it into the protocol and the run goes on to the
 * cleanup, so a failed restore never leaves its copy behind. Only something unexpected fails the operation; `compensate` then
 * still asks for the cleanup and closes the protocol.
 */
final class VerifiedRestoreWorkflow implements Workflow
{
    /** How long the copy may take to be made, and to be removed, before the run gives up waiting (seconds). */
    public const READY_DEADLINE = 2 * 3600;

    public const CLEANUP_DEADLINE = 2 * 3600;

    public static function kind(): string
    {
        return 'service.restore_verify';
    }

    public function queue(Operation $operation): string
    {
        $instance = $operation->provider_instance_id ? ProviderInstance::query()->find($operation->provider_instance_id) : null;

        return 'provider-'.($instance === null ? 'default' : $instance->provider);
    }

    public function steps(Operation $operation): array
    {
        return [$this->prepareStep(), $this->restoreStep(), $this->checkStep(), $this->cleanupStep(), $this->protocolStep()];
    }

    public function compensate(StepContext $context): void
    {
        $run = VerifiedRestoreRun::query()->where('operation_id', $context->operation->id)->first();
        if ($run === null || $run->state === VerifiedRestoreRun::FINISHED) {
            return;
        }
        $reason = (string) ($context->operation->error['message'] ?? 'run failed');
        $target = $context->container->make(RestoreTarget::class);
        if ($run->stepStatus('cleanup') === null && data_get($run->target, 'service_id') !== null) {
            try {
                $asked = $target->cleanup($run);
                $run->recordStep('cleanup', $asked['requested'] && $target->cleanedUp($run->fresh() ?? $run) === true ? VerifiedRestoreRun::PASS : VerifiedRestoreRun::FAIL, 'po selhání běhu ('.$reason.'): '.$asked['detail']);
            } catch (Throwable $e) {
                $run->recordStep('cleanup', VerifiedRestoreRun::FAIL, 'po selhání běhu: '.mb_substr($e->getMessage(), 0, 160));
            }
        }
        $context->container->make(VerifiedRestore::class)->finish($run->fresh() ?? $run);
    }

    private function prepareStep(): AbstractStep
    {
        return new class extends AbstractStep
        {
            public function label(): string
            {
                return 'Zkušební kopie webu';
            }

            public function run(StepContext $context): StepResult
            {
                $verified = $context->container->make(VerifiedRestore::class);
                $production = Service::query()->findOrFail((string) $context->desired('service_id'));
                $run = $verified->begin($context->operation, $production);
                if ($verified->backupOf($run) === null) {
                    return StepResult::done(['run_id' => $run->id]); // nothing to restore: straight to the protocol, nothing was made
                }
                if (data_get($run->target, 'service_id') === null) {
                    $made = $context->container->make(RestoreTarget::class)->prepare($production, $run, $context->actor);
                    if ($made['service_id'] === null) {
                        $run->recordStep('target_prepared', VerifiedRestoreRun::FAIL, 'kopii se nepodařilo založit: '.$made['error']);

                        return StepResult::done(['run_id' => $run->id]);
                    }
                    $run->mergeTarget(['service_id' => $made['service_id'], 'domain' => $made['domain'], 'databases' => []]);
                }

                return $this->look($context, $run, new AsyncHandle('onhost_restore_target', (string) data_get($run->target, 'service_id'), null, ['run_id' => $run->id], 15, 6 * 3600));
            }

            public function poll(StepContext $context, AsyncHandle $handle): StepResult
            {
                return $this->look($context, VerifiedRestoreRun::query()->where('operation_id', $context->operation->id)->firstOrFail(), $handle);
            }

            private function look(StepContext $context, VerifiedRestoreRun $run, AsyncHandle $handle): StepResult
            {
                $ready = $context->container->make(RestoreTarget::class)->ready($run);
                if ($ready === true) {
                    $run->recordStep('target_prepared', VerifiedRestoreRun::PASS, 'kopie '.data_get($run->target, 'domain').' založena na uzlu produkce');

                    return StepResult::done(['run_id' => $run->id]);
                }
                $late = $run->started_at !== null && $run->started_at->lessThan(now()->subSeconds(VerifiedRestoreWorkflow::READY_DEADLINE));
                if ($ready === false || $late) {
                    $run->recordStep('target_prepared', VerifiedRestoreRun::FAIL, $late ? 'kopie nebyla hotová do '.(VerifiedRestoreWorkflow::READY_DEADLINE / 60).' minut' : 'založení kopie selhalo');

                    return StepResult::done(['run_id' => $run->id]);
                }

                return StepResult::wait($handle, ['run_id' => $run->id]);
            }
        };
    }

    private function restoreStep(): AbstractStep
    {
        return new class extends AbstractStep
        {
            public function label(): string
            {
                return 'Obnova zálohy do kopie';
            }

            public function run(StepContext $context): StepResult
            {
                $run = VerifiedRestoreRun::query()->where('operation_id', $context->operation->id)->firstOrFail();
                $backup = $context->container->make(VerifiedRestore::class)->backupOf($run);
                if ($backup === null || $run->stepStatus('target_prepared') !== VerifiedRestoreRun::PASS) {
                    return StepResult::done();
                }
                $production = Service::query()->findOrFail((string) $context->desired('service_id'));
                try {
                    $result = $context->container->make(RestoreTarget::class)->restore($run, $production, $backup);
                } catch (Throwable $e) {
                    $run->recordStep('files_restored', VerifiedRestoreRun::FAIL, 'obnova do kopie selhala: '.mb_substr($e->getMessage(), 0, 200));

                    return StepResult::done();
                }
                // what was made in the copy is written down before anything else: the cleanup removes exactly that
                $run->mergeTarget(['databases' => $result['created'], 'wordpress' => $result['wordpress'], 'probe_allowed' => $result['probe_allowed'], 'probe_refusal' => $result['probe_refusal']]);
                $run->recordStep('files_restored', $result['files']['status'], $result['files']['detail']);
                $run->recordStep('database_imported', $result['databases']['status'], $result['databases']['detail']);
                $run->forceFill(['suppression' => $result['suppression']])->save();
                $run->recordStep('side_effects_suppressed', $result['suppression']['not_applied'] === [] ? VerifiedRestoreRun::PASS : VerifiedRestoreRun::NOT_VERIFIED,
                    count($result['suppression']['applied']).' opatření provedeno, '.count($result['suppression']['not_applied']).' nebylo možné provést (viz seznam)');

                return StepResult::done(['databases' => $result['databases']['rows']]);
            }
        };
    }

    private function checkStep(): AbstractStep
    {
        return new class extends AbstractStep
        {
            public function label(): string
            {
                return 'Kontroly kopie';
            }

            public function run(StepContext $context): StepResult
            {
                $run = VerifiedRestoreRun::query()->where('operation_id', $context->operation->id)->firstOrFail();
                $restored = in_array($run->stepStatus('files_restored'), [VerifiedRestoreRun::PASS, VerifiedRestoreRun::NOT_VERIFIED], true)
                    && in_array($run->stepStatus('database_imported'), [VerifiedRestoreRun::PASS, VerifiedRestoreRun::NOT_VERIFIED], true);
                if ($run->stepStatus('target_prepared') !== VerifiedRestoreRun::PASS || ! $restored) {
                    return StepResult::done();
                }
                $target = $context->container->make(RestoreTarget::class);
                if (data_get($run->target, 'probe_allowed') !== true) {
                    $run->recordStep('http_200', VerifiedRestoreRun::NOT_VERIFIED, (string) data_get($run->target, 'probe_refusal', 'kopie nebyla dotázána'));
                    $run->recordStep('login', VerifiedRestoreRun::NOT_VERIFIED, 'nebylo ověřeno');
                } else {
                    $probe = $target->probe($run);
                    $run->recordStep('http_200', $probe['status'] === 200 ? VerifiedRestoreRun::PASS : VerifiedRestoreRun::FAIL, $probe['detail']);
                    $identity = $context->container->make(VerifiedRestore::class)->identity(Service::query()->findOrFail((string) $context->desired('service_id')));
                    if ($identity === null) {
                        $run->recordStep('login', VerifiedRestoreRun::NOT_VERIFIED, 'nebylo ověřeno: testovací účet není nastaven');
                    } else {
                        $login = $target->login($run, $identity);
                        $run->recordStep('login', $login['status'], $login['detail']);
                    }
                }
                $run->forceFill(['checks_done_at' => now()])->save();

                return StepResult::done(['http' => $run->stepStatus('http_200')]);
            }
        };
    }

    private function cleanupStep(): AbstractStep
    {
        return new class extends AbstractStep
        {
            public function label(): string
            {
                return 'Úklid kopie';
            }

            public function run(StepContext $context): StepResult
            {
                $run = VerifiedRestoreRun::query()->where('operation_id', $context->operation->id)->firstOrFail();
                if ($run->checks_done_at === null) {
                    $run->forceFill(['checks_done_at' => now()])->save(); // the RTO clock stops where the checks stopped, also when they did not run
                }
                if (data_get($run->target, 'service_id') === null) {
                    $run->recordStep('cleanup', VerifiedRestoreRun::PASS, 'žádná kopie nevznikla, není co uklízet');

                    return StepResult::done();
                }

                return $this->ask($context, $run, new AsyncHandle('onhost_restore_cleanup', (string) data_get($run->target, 'service_id'), null, ['run_id' => $run->id], 15, 6 * 3600));
            }

            public function poll(StepContext $context, AsyncHandle $handle): StepResult
            {
                return $this->ask($context, VerifiedRestoreRun::query()->where('operation_id', $context->operation->id)->firstOrFail(), $handle);
            }

            private function ask(StepContext $context, VerifiedRestoreRun $run, AsyncHandle $handle): StepResult
            {
                $target = $context->container->make(RestoreTarget::class);
                if (data_get($run->target, 'cleanup_requested_at') === null) {
                    $asked = $target->cleanup($run);
                    if ($asked['requested']) {
                        $run->mergeTarget(['cleanup_requested_at' => now()->toIso8601String(), 'cleanup_detail' => $asked['detail']]);
                    } elseif ($run->checks_done_at !== null && $run->checks_done_at->lessThan(now()->subSeconds(VerifiedRestoreWorkflow::CLEANUP_DEADLINE))) {
                        $run->recordStep('cleanup', VerifiedRestoreRun::FAIL, 'úklid se nepodařilo vyžádat: '.$asked['detail']);

                        return StepResult::done();
                    } else {
                        return StepResult::wait($handle); // the copy is still being made; asked again at the next look
                    }
                }
                $gone = $target->cleanedUp($run->fresh() ?? $run);
                if ($gone === true) {
                    $run->recordStep('cleanup', VerifiedRestoreRun::PASS, 'kopie i její databáze odstraněny; ověřeno dotazem na panel');

                    return StepResult::done();
                }
                $since = (string) data_get($run->target, 'cleanup_requested_at', '');
                $late = $since !== '' && now()->subSeconds(VerifiedRestoreWorkflow::CLEANUP_DEADLINE)->greaterThan($since);
                if ($gone === false || $late) {
                    $run->recordStep('cleanup', VerifiedRestoreRun::FAIL, ($late ? 'kopie po '.(VerifiedRestoreWorkflow::CLEANUP_DEADLINE / 60).' minutách stále existuje' : 'kopie nebo její část zůstala').': '.(string) data_get($run->target, 'cleanup_detail', ''));

                    return StepResult::done();
                }

                return StepResult::wait($handle);
            }
        };
    }

    private function protocolStep(): AbstractStep
    {
        return new class extends AbstractStep
        {
            public function label(): string
            {
                return 'Protokol';
            }

            public function run(StepContext $context): StepResult
            {
                $run = $context->container->make(VerifiedRestore::class)->finish(VerifiedRestoreRun::query()->where('operation_id', $context->operation->id)->firstOrFail());

                return StepResult::done(['run_id' => $run->id, 'outcome' => $run->outcome, 'rpo_seconds' => $run->rpo_seconds, 'rto_seconds' => $run->rto_seconds]);
            }
        };
    }
}
