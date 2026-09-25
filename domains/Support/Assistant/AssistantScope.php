<?php

declare(strict_types=1);

namespace Onhost\Domain\Support\Assistant;

use Illuminate\Database\Eloquent\Builder;
use Onhost\Domain\Identity\Authorization\Authorizer;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Services\Commands\ServiceActionCommand;
use Onhost\Domain\Services\Models\Service;
use Onhost\Platform\Commands\CommandScope;

/**
 * What the assistant may see and offer in one conversation: exactly what the signed-in person may.
 *
 * The assistant used to read with the ORGANIZATION's eyes — every member who could open the chat could ask it about
 * invoices, variable symbols, the credit, every service and every ticket, whatever their own role allowed in the panel.
 * A support contact is not shown the billing page; the chat told them anyway. A guest with one shared service would have
 * been told about all of them.
 *
 * Every read tool and every proposal now asks this object, and this object asks the authorizer — the same one the API
 * asks. Staff helping a customer see what `staff.customer.read` shows them and are offered what `staff.service.manage`
 * lets them do; nothing the assistant proposes runs anywhere but through the API, under the person's own permissions.
 */
final class AssistantScope
{
    /**
     * @param  list<string>  $projectIds  projects whose services the person reads through a project role
     * @param  list<string>  $serviceIds  single services shared with the person
     */
    private function __construct(
        public readonly Organization $organization,
        public readonly User $user,
        private readonly Authorizer $authorizer,
        public readonly bool $staff,
        public readonly bool $allServices,
        public readonly array $projectIds,
        public readonly array $serviceIds,
        public readonly bool $billing,
        public readonly bool $wallet,
        public readonly bool $orders,
        public readonly bool $domains,
        public readonly bool $tickets,
        public readonly bool $ticketsWrite,
    ) {}

    public static function for(Organization $organization, User $user, Authorizer $authorizer): self
    {
        $at = CommandScope::organization($organization->id);
        $can = fn (string $permission): bool => $authorizer->can($user, $permission, $at);
        $all = $can('service.read');

        return new self(
            $organization, $user, $authorizer, false, $all,
            $all ? [] : $authorizer->projectIdsWhere($user, 'service.read', $organization->id),
            $all ? [] : $authorizer->resourceIdsWhere($user, 'service.read', $organization->id),
            $can('billing.invoice.read'), $can('billing.wallet.read'), $can('organization.read'), $can('domain.read'), $can('support.ticket.read'), $can('support.ticket.write'),
        );
    }

    /** A staff member working on a customer's account: the customer-360 view, and the service actions their staff role allows. */
    public static function staff(Organization $organization, User $staffUser, Authorizer $authorizer): self
    {
        $sees = $authorizer->can($staffUser, 'staff.customer.read', CommandScope::global());

        return new self($organization, $staffUser, $authorizer, true, $sees, [], [], $sees, $sees, $sees, $sees, $sees, false);
    }

    /** May this person open the assistant about this organization at all? */
    public static function mayChat(Organization $organization, User $user, Authorizer $authorizer): bool
    {
        return $authorizer->can($user, 'support.chat.use', CommandScope::organization($organization->id))
            || $authorizer->resourceIdsWhere($user, 'support.chat.use', $organization->id) !== []; // a guest whose shared service came with the assistant
    }

    /** @return Builder<Service> the organization's services this person may see */
    public function services(): Builder
    {
        $query = Service::query()->where('organization_id', $this->organization->id);
        if (! $this->allServices) {
            $query->where(fn (Builder $q) => $q->whereIn('project_id', $this->projectIds)->orWhereIn('id', $this->serviceIds));
        }

        return $query;
    }

    public function seesAnyService(): bool
    {
        return $this->allServices || $this->projectIds !== [] || $this->serviceIds !== [];
    }

    public function service(string $id): ?Service
    {
        return $id === '' ? null : $this->services()->whereKey($id)->first();
    }

    /** Would the API let this person run the action? A button that ends in "forbidden" is not offered. */
    public function mayRun(Service $service, string $action): bool
    {
        if ($this->staff) {
            return $this->authorizer->can($this->user, 'staff.service.manage', CommandScope::global());
        }

        if (! array_key_exists($action, ServiceActionCommand::PERMISSIONS)) { // the model can name any string; the map has no default (TASK-0029)
            return false;
        }

        return $this->authorizer->can($this->user, ServiceActionCommand::permissionFor($action), CommandScope::resource($service->id, $service->organization_id, $service->project_id));
    }
}
