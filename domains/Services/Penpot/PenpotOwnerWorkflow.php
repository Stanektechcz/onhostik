<?php

declare(strict_types=1);

namespace Onhost\Domain\Services\Penpot;

use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Provisioning\Workflow\StepContext;
use Onhost\Domain\Provisioning\Workflow\StepResult;
use Onhost\Domain\Provisioning\Workflow\Workflow;
use Onhost\Domain\Provisioning\Workflows\ServiceStep;
use Onhost\Domain\Services\Models\Service;
use Onhost\Platform\Errors\ProviderException;
use Onhost\Platform\Events\GenericEvent;
use Onhost\Platform\Outbox\OutboxPublisher;
use Onhost\Providers\Penpot\PenpotDockerProvider;

/**
 * The owner sets the password of their Penpot account (TASK-0123): `manage.py update-profile` inside the stack's backend. The
 * password rides in `desired.password` and nowhere else; OperationSecrets forgets it when the run ends.
 */
final class PenpotOwnerWorkflow implements Workflow
{
    public static function kind(): string
    {
        return 'penpot.owner_password';
    }

    public function queue(Operation $operation): string
    {
        return 'provider-penpot';
    }

    public function steps(Operation $operation): array
    {
        return [
            new class extends ServiceStep
            {
                public function label(): string
                {
                    return 'Heslo vlastníka Penpotu';
                }

                public function run(StepContext $context): StepResult
                {
                    $service = $this->service($context);
                    $email = (string) data_get($service->tags, 'penpot.owner_email', '');
                    if ($email === '') {
                        return StepResult::fail('The Penpot owner account is not known for this service', false);
                    }
                    try {
                        $this->capability($context, PenpotDockerProvider::class)->setOwnerPassword($this->ref($context, 'stack'), $email, (string) $context->desired('password', ''));
                    } catch (ProviderException $e) {
                        return self::fromProviderException($e);
                    }
                    $fresh = Service::query()->findOrFail($service->id);
                    $tags = (array) $fresh->tags;
                    $tags['penpot'] = array_replace((array) ($tags['penpot'] ?? []), ['owner_password_set' => true, 'owner_password_at' => now()->toIso8601String()]);
                    $fresh->forceFill(['tags' => $tags])->save();
                    $context->container->make(OutboxPublisher::class)->publish(GenericEvent::of('penpot.owner_password.changed', 'service', $service->id, ['label' => (string) ($fresh->label ?: $fresh->hostname), 'owner_email' => $email, 'actor_id' => $context->actor->actorId], $service->organization_id));

                    return StepResult::done(['owner_email' => $email]);
                }
            },
        ];
    }

    public function compensate(StepContext $context): void
    {
        // nothing was changed that could be taken back: Penpot either took the new password or kept the old one
    }
}
