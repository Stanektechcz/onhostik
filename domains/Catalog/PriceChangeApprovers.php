<?php

declare(strict_types=1);

namespace Onhost\Domain\Catalog;

use Onhost\Domain\Identity\Authorization\ApprovalService;
use Onhost\Domain\Identity\Authorization\Authorizer;
use Onhost\Domain\Identity\Models\User;
use Onhost\Platform\Commands\CommandScope;

/**
 * Whether a price or plan change can get its second person (owner decision 13, CatalogCommand::requiresApproval). The
 * approver must be somebody else who may decide approvals AND could make the change themselves (catalog.manage): a
 * product manager may ask, an IAM admin may decide approvals in general, neither of them approves a price. With fewer than
 * two such people the only one of them can never have a change of their own approved — the price list freezes for them.
 */
final class PriceChangeApprovers
{
    public function __construct(private readonly Authorizer $authorizer) {}

    /** @return array{ok: bool, approvers: int, detail: string} */
    public function status(): array
    {
        if (! ApprovalService::enabled()) {
            return ['ok' => true, 'approvers' => 0, 'detail' => 'ONHOST_FOUR_EYES=false: a price or plan change takes one person and a step-up (single-operator mode)'];
        }
        $approvers = ApprovalService::deciders()->filter(fn (User $user) => $this->authorizer->can($user, 'catalog.manage', CommandScope::global()))->count();

        return ['ok' => $approvers >= 2, 'approvers' => $approvers, 'detail' => "{$approvers} member(s) of staff can approve a price change (iam.approval.decide and catalog.manage)"
            .($approvers >= 2 ? '' : ' — the price list is frozen for the only one of them: give billing_finance_admin to a second person, or run ONHOST_FOUR_EYES=false deliberately (docs/runbooks/approvals.md)')];
    }
}
