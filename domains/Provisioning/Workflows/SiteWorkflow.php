<?php

declare(strict_types=1);

namespace Onhost\Domain\Provisioning\Workflows;

use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Domain\Provisioning\Workflow\StepContext;
use Onhost\Domain\Provisioning\Workflow\StepResult;
use Onhost\Domain\Provisioning\Workflow\Workflow;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Domain\Services\Web\ServiceSites;
use Onhost\Platform\Events\GenericEvent;
use Onhost\Platform\Outbox\OutboxPublisher;
use Onhost\Providers\Contracts\AsyncHandle;
use Onhost\Providers\Contracts\InfrastructureProvider;

/**
 * The further sites a hosting plan sells (`ServiceSites`): adding one and taking one away. A site of the plan is a
 * site of its own — own document root, PHP version, certificate, databases and backups — so it is provisioned by the
 * ordinary website saga as a service carried by the one the customer pays for, and taken away by its own
 * cancellation, which archives it first.
 *
 * Adding one runs as an operation OF THE SERVICE the customer pays for, which is what lets the same run give the
 * new site its share of the plan's space: the share is taken from the free part first and from the service's own
 * site for the rest, in one place, without a second operation racing the first.
 */
final class SiteWorkflow implements Workflow
{
    public static function kind(): string
    {
        return 'service.site';
    }

    public function queue(Operation $operation): string
    {
        $instance = $operation->provider_instance_id ? ProviderInstance::query()->find($operation->provider_instance_id) : null;

        return 'provider-'.($instance instanceof ProviderInstance ? $instance->provider : 'default');
    }

    public function steps(Operation $operation): array
    {
        return (string) data_get($operation->desired, 'action') === 'site.delete'
            ? [$this->removeStep()]
            : [$this->spaceStep(), $this->createStep()];
    }

    public function compensate(StepContext $context): void
    {
        $context->container->make(OutboxPublisher::class)->publish(GenericEvent::of('service.site.failed', 'service', $context->service->id, [
            'action' => data_get($context->operation->desired, 'action'), 'domain' => data_get($context->operation->desired, 'domain'),
            'error' => (string) ($context->operation->error['message'] ?? 'failed'),
        ], $context->service->organization_id));
    }

    /** The share the new site is given comes out of the plan: what is free, and the rest out of the service's own site. */
    private function spaceStep(): ServiceStep
    {
        return new class extends ServiceStep
        {
            public function label(): string
            {
                return 'Rozdělení prostoru tarifu';
            }

            public function run(StepContext $context): StepResult
            {
                $owner = $this->service($context);
                $share = (int) $context->desired('nvme_gb', 0);
                $ownerShare = (int) $context->desired('owner_nvme_gb', 0);
                if ($ownerShare <= 0 || $ownerShare >= ServiceSites::share($owner, $owner)) {
                    return StepResult::skip(); // the free part of the plan was enough; nothing of the running site changes
                }
                // the panel is told first: a share written down but never applied is a promise the node does not keep
                $result = $this->capability($context, InfrastructureProvider::class)->resize($this->ref($context), $context->spec('website', [
                    'entitlements' => array_replace((array) $owner->entitlements, ['nvme_gb' => $ownerShare]),
                ]));
                $tags = (array) ($owner->tags ?? []);
                $tags['sites'] = array_merge((array) ($tags['sites'] ?? []), ['quota_gb' => $ownerShare]);
                $owner->forceFill(['tags' => $tags])->save();

                return $this->settle($result, ['owner_nvme_gb' => $ownerShare, 'site_nvme_gb' => $share]);
            }
        };
    }

    private function createStep(): ServiceStep
    {
        return new class extends ServiceStep
        {
            public function label(): string
            {
                return 'Založení dalšího webu';
            }

            public function run(StepContext $context): StepResult
            {
                $owner = $this->service($context);
                $sites = $context->container->make(ServiceSites::class);
                $existing = ServiceSites::of($owner)->first(fn (Service $site) => strtolower((string) ($site->hostname ?? '')) === strtolower((string) $context->desired('domain')));
                $site = $existing instanceof Service && $existing->id !== $owner->id
                    ? $existing // the step ran again after the service row was written
                    : $sites->create($owner, [
                        'domain' => (string) $context->desired('domain'),
                        'php_version' => (string) $context->desired('php_version', '8.3'),
                        'share' => (int) $context->desired('nvme_gb', 0),
                        'owner_share' => (int) $context->desired('owner_nvme_gb', 0),
                    ], $context->actor);
                if ($site->state === ServiceStateMachine::ACTIVE) {
                    return StepResult::done(['site_service_id' => $site->id, 'domain' => $site->hostname]);
                }

                return StepResult::wait(new AsyncHandle('onhost_service', $site->id, null, [], 15, 2 * 3600), ['site_service_id' => $site->id, 'domain' => $site->hostname]);
            }

            public function poll(StepContext $context, AsyncHandle $handle): StepResult
            {
                $site = Service::query()->find($handle->handle);
                if ($site === null) {
                    return StepResult::fail('the new site disappeared', false);
                }
                if ($site->state === ServiceStateMachine::ACTIVE) {
                    return StepResult::done(['site_service_id' => $site->id, 'domain' => $site->hostname]);
                }
                if (in_array($site->state, [ServiceStateMachine::FAILED, ServiceStateMachine::TERMINATED], true)) {
                    return StepResult::fail('the new site could not be created on the node', false, ['site_service_id' => $site->id]);
                }

                return StepResult::wait($handle);
            }
        };
    }

    private function removeStep(): ServiceStep
    {
        return new class extends ServiceStep
        {
            public function label(): string
            {
                return 'Odebrání webu ze služby';
            }

            public function run(StepContext $context): StepResult
            {
                $owner = $this->service($context);
                $sites = $context->container->make(ServiceSites::class);
                $site = $sites->siteOf($owner, (string) $context->desired('site_id'));
                if (in_array($site->state, [ServiceStateMachine::TERMINATED, ServiceStateMachine::TERMINATING], true) || $site->terminate_at !== null) {
                    return StepResult::done(['site_service_id' => $site->id, 'already' => true]);
                }
                $operation = $sites->remove($owner, $site, $context->actor, $context->operation->id);

                return StepResult::done(['site_service_id' => $site->id, 'site_operation_id' => $operation->id, 'domain' => $site->hostname]);
            }
        };
    }
}
