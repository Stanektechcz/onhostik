<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Onhost\Domain\Identity\Authorization\Authorizer;
use Onhost\Domain\Identity\Models\ServiceAccount;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Orders\CreditOrderPolicy;
use Onhost\Domain\Orders\Models\Order;
use Onhost\Domain\Orders\OrderStateMachine;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Organizations\Models\OrganizationMembership;
use Onhost\Platform\Commands\CommandScope;

/**
 * Read-only look before `ONHOST_ORDER_CREDIT_APPROVAL` is switched on (owner decision 20, TASK-0021): which members and service
 * accounts may order but may not spend the credit (their credit orders will start to wait), which organizations have nobody
 * to approve, and which orders are waiting now. It writes nothing.
 */
final class CreditApprovalReport extends Command
{
    protected $signature = 'onhost:orders:credit-approval-report {--organization= : one organization id} {--limit=500 : organizations to look at}';

    protected $description = 'Read-only: who will need approval for credit orders, organizations without an approver, orders waiting now';

    public function handle(Authorizer $authorizer): int
    {
        $this->line('Switch onhost.orders.credit_approval.enabled: '.(CreditOrderPolicy::enabled() ? 'ON' : 'off').' · gated modes: '.implode(', ', CreditOrderPolicy::gatedModes()).' · expiry: '.(int) config('onhost.orders.credit_approval.expire_days', 7).' days');
        $query = Organization::query()->orderBy('created_at')->limit(max(1, (int) $this->option('limit')));
        if (is_string($this->option('organization')) && $this->option('organization') !== '') {
            $query->whereKey((string) $this->option('organization'));
        }
        $affected = [];
        $withoutApprover = [];
        foreach ($query->get() as $organization) {
            $scope = CommandScope::organization($organization->id);
            $memberIds = OrganizationMembership::query()->where('organization_id', $organization->id)->current()->pluck('user_id')->all();
            foreach (User::query()->whereIn('id', $memberIds)->where('state', 'active')->get() as $user) {
                if ($user->id !== $organization->owner_user_id && $authorizer->can($user, 'catalog.order.create', $scope) && ! $authorizer->can($user, CreditOrderPolicy::PERMISSION, $scope)) {
                    $affected[] = [$organization->name, $organization->id, 'user', $user->email];
                }
            }
            foreach (ServiceAccount::query()->where('organization_id', $organization->id)->get() as $account) {
                if ($account->isActive() && $authorizer->can($account, 'catalog.order.create', $scope) && ! $authorizer->can($account, CreditOrderPolicy::PERMISSION, $scope)) {
                    $affected[] = [$organization->name, $organization->id, 'service account', (string) $account->name];
                }
            }
            if (CreditOrderPolicy::approvers($organization->id)->isEmpty()) {
                $withoutApprover[] = [$organization->name, $organization->id];
            }
        }

        $this->newLine();
        $this->info('Credit orders of these principals will wait for approval ('.count($affected).'):');
        $this->table(['Organization', 'Id', 'Kind', 'Who'], $affected);
        $this->info('Organizations with nobody to approve ('.count($withoutApprover).'):');
        $this->table(['Organization', 'Id'], $withoutApprover);
        $pending = Order::query()->where('state', OrderStateMachine::NEW)->where('meta->approval->state', 'pending')->orderBy('placed_at')->limit(200)->get();
        $this->info('Orders waiting for approval now ('.$pending->count().'):');
        $this->table(['Order', 'Organization', 'Total', 'Placed'], $pending->map(fn (Order $o) => [$o->number, $o->organization_id, $o->total()->format(), (string) $o->placed_at])->all());

        return self::SUCCESS;
    }
}
