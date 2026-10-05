<?php

declare(strict_types=1);

namespace Onhost\Domain\Provisioning\Workflows;

use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Domain\Provisioning\Workflow\StepContext;
use Onhost\Domain\Provisioning\Workflow\StepResult;
use Onhost\Domain\Provisioning\Workflow\Workflow;
use Onhost\Domain\Services\CustomIso\CustomIsoDrive;
use Onhost\Domain\Services\Models\CustomIso;
use Onhost\Domain\Services\Models\Service;
use Onhost\Platform\Errors\DomainError;

/**
 * A customer's own image on a server (TASK-0110, owner decision G-R5): `iso.attach` copies it to the node (waiting for the
 * hypervisor's import task) and attaches it as the CD drive; `iso.detach` puts back the drive and boot order found before;
 * `iso.delete` detaches it from this server first, then deletes every copy and the platform's file. The rules (plan, rescue
 * session, whose image) are checked before the operation is queued (CustomIsoPolicy::params) and again here by CustomIsoDrive.
 */
final class CustomIsoWorkflow implements Workflow
{
    public static function kind(): string
    {
        return 'service.iso';
    }

    public function queue(Operation $operation): string
    {
        $instance = $operation->provider_instance_id ? ProviderInstance::query()->find($operation->provider_instance_id) : null;

        return 'provider-'.($instance->provider ?? 'default');
    }

    public function steps(Operation $operation): array
    {
        return match ((string) data_get($operation->desired, 'action')) {
            'iso.attach' => [$this->copyStep(), $this->attachStep()],
            'iso.detach' => [$this->detachStep()],
            'iso.delete' => [$this->detachStep(), $this->deleteStep()],
            default => throw new DomainError('service_action_unknown', 'Unknown image action.', 422),
        };
    }

    public function compensate(StepContext $context): void {}

    /** The organization's image the operation names, still kept. */
    public static function image(Service $service, mixed $id): CustomIso
    {
        $iso = is_string($id) && $id !== '' ? CustomIso::reachableFrom($service)->find($id) : null;

        return $iso ?? throw new DomainError('iso_gone', 'Toto ISO už v knihovně organizace není.', 409);
    }

    private function copyStep(): ServiceStep
    {
        return new class extends ServiceStep
        {
            public function label(): string
            {
                return 'Nahrání ISO na server';
            }

            public function run(StepContext $context): StepResult
            {
                $service = $this->service($context);
                $iso = CustomIsoWorkflow::image($service, $context->desired('iso_id'));

                return $this->settle($context->container->make(CustomIsoDrive::class)->ensureOnNode($service, $iso), ['iso_id' => $iso->id]);
            }
        };
    }

    private function attachStep(): ServiceStep
    {
        return new class extends ServiceStep
        {
            public function label(): string
            {
                return 'Připojení ISO';
            }

            public function run(StepContext $context): StepResult
            {
                $service = $this->service($context);
                $iso = CustomIsoWorkflow::image($service, $context->desired('iso_id'));
                $result = $context->container->make(CustomIsoDrive::class)->attach($service, $iso, (bool) $context->desired('boot_first', true), (bool) $context->desired('reboot', false), $context->actor);

                return StepResult::done($result);
            }
        };
    }

    private function detachStep(): ServiceStep
    {
        return new class extends ServiceStep
        {
            public function label(): string
            {
                return 'Odpojení ISO';
            }

            public function run(StepContext $context): StepResult
            {
                $service = $this->service($context);
                $drive = $context->container->make(CustomIsoDrive::class);
                if ((string) $context->desired('action') === 'iso.delete') {
                    $iso = CustomIsoWorkflow::image($service, $context->desired('iso_id'));
                    if ($iso->attached_service_id !== $service->id) {
                        return StepResult::done(['detached' => false]); // not on this server: nothing to put back here
                    }

                    return StepResult::done($drive->detach($service, false, $context->actor, 'the image is being deleted'));
                }

                return StepResult::done($drive->detach($service, (bool) $context->desired('reboot', false), $context->actor));
            }
        };
    }

    private function deleteStep(): ServiceStep
    {
        return new class extends ServiceStep
        {
            public function label(): string
            {
                return 'Smazání ISO';
            }

            public function run(StepContext $context): StepResult
            {
                $service = $this->service($context);
                $iso = CustomIsoWorkflow::image($service, $context->desired('iso_id'));

                return StepResult::done($context->container->make(CustomIsoDrive::class)->delete($iso, $context->actor, $service));
            }
        };
    }
}
