<?php

declare(strict_types=1);

namespace Onhost\Domain\Services\Penpot;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Onhost\Domain\Catalog\CatalogService;
use Onhost\Domain\Catalog\Models\Product;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Provisioning\Models\Region;
use Onhost\Domain\Provisioning\Scheduling\NodeScheduler;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Platform\Commands\Command;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Commands\CommandHandler;
use Onhost\Platform\Errors\DomainError;

/** Runs PaperParentCommand (J2, TASK-0154): the guards first, then one row written or closed — never a panel, never money. */
final class PaperParentCommandHandler implements CommandHandler
{
    public function __construct(private readonly CatalogService $catalog) {}

    /** @return array{service_id:string, state:string, created?:bool} */
    public function handle(Command $command, CommandContext $context): array
    {
        if (! $command instanceof PaperParentCommand) {
            throw new \LogicException('Unsupported command '.get_class($command));
        }
        if ($context->actorType !== 'system') {
            throw new DomainError('paper_parent_cli_only', 'The paper parent is created only from the command line (onhost:penpot:paper-parent).', 403);
        }
        if (app()->environment('production')) {
            throw new DomainError('paper_parent_production', 'A paper parent is a staging test aid; it is never created or ended in production.', 409);
        }
        $organization = Organization::query()->find((string) $command->get('organization_id'));
        if ($organization === null) {
            throw DomainError::notFound('organization');
        }
        if (! NodeScheduler::sandboxFor($organization->id)) {
            throw new DomainError('paper_parent_not_sandbox', 'The organisation is not a sandbox: turn sandbox on in the staff console first.', 409);
        }

        return match ((string) $command->get('op')) {
            'create' => $this->create($command, $organization),
            'end' => $this->end($command, $organization),
            default => throw new DomainError('op_unknown', 'Unknown paper parent operation.', 422, ['field' => 'op']),
        };
    }

    /** @return array{service_id:string, state:string, created:bool} */
    private function create(PaperParentCommand $command, Organization $organization): array
    {
        $existing = self::paperParents($organization)->whereIn('state', PenpotParents::PARENT_STATES)->first();
        if ($existing !== null) {
            return ['service_id' => (string) $existing->id, 'state' => (string) $existing->state, 'created' => false];
        }
        $productKey = (string) $command->get('product_key', 'web-hosting');
        $product = Product::query()->where('key', $productKey)->first();
        if ($product === null || ! in_array((string) $product->family, PaperParentCommand::FAMILIES, true)) {
            throw new DomainError('paper_parent_product_invalid', 'A paper parent is a web hosting (family web or managed).', 422, ['field' => 'product_key']);
        }
        $version = $this->catalog->resolve($productKey, (string) $command->get('plan', 'start'), 'CZK', 'month')['version'];

        $service = DB::transaction(fn () => Service::query()->create([
            'organization_id' => $organization->id, 'product_key' => $product->key, 'plan_version_id' => $version->id, 'family' => $product->family,
            'name' => 'STAGING papírový rodič (Penpot E2E, bez zdroje)', 'label' => PaperParentCommand::LABEL, 'state' => ServiceStateMachine::ACTIVE,
            'activated_at' => now(), 'region_code' => (string) (Region::query()->orderBy('code')->value('code') ?? 'cz1'), 'entitlements' => [],
            'sla_class' => 'standard', 'tags' => [PaperParentCommand::TAG => true], 'desired_spec' => [],
        ]));

        return ['service_id' => (string) $service->id, 'state' => (string) $service->state, 'created' => true];
    }

    /** @return array{service_id:string, state:string} */
    private function end(PaperParentCommand $command, Organization $organization): array
    {
        $service = self::paperParents($organization)->find((string) $command->get('service_id'));
        if ($service === null) {
            throw DomainError::notFound('paper parent'); // an ordinary service is never ended by this tool
        }
        if ($service->state === ServiceStateMachine::TERMINATED) {
            return ['service_id' => (string) $service->id, 'state' => (string) $service->state];
        }
        if (PenpotParents::of($service)->isNotEmpty()) {
            throw new DomainError('paper_parent_has_penpot', 'Cancel the Penpot of this parent first (its workflow removes the stack); the parent ends after it.', 409);
        }
        // no workflow on purpose: nothing exists at a panel, so there is nothing to tear down
        $service->forceFill(['state' => ServiceStateMachine::TERMINATED, 'terminated_at' => now()])->save();

        return ['service_id' => (string) $service->id, 'state' => (string) $service->state];
    }

    /** @return Builder<Service> */
    private static function paperParents(Organization $organization): Builder
    {
        return Service::query()
            ->where('organization_id', $organization->id)
            ->where('label', PaperParentCommand::LABEL)
            ->where('tags->'.PaperParentCommand::TAG, true)
            ->whereNull('provider_instance_id');
    }
}
