<?php

declare(strict_types=1);

namespace Onhost\Domain\Identity\Commands;

use Onhost\Domain\Identity\Authorization\ApprovalService;
use Onhost\Platform\Commands\GlobalCommand;

/**
 * The second person decides a request for approval: payload {approval_id, decision: approved|rejected, note?}.
 * The permission is HIGH in the catalogue, so deciding takes a fresh step-up — an approval given from a session
 * somebody left open is not a second person.
 */
final class ApprovalDecisionCommand extends GlobalCommand
{
    public function permission(): string
    {
        return ApprovalService::PERMISSION;
    }

    public function name(): string
    {
        return 'iam.approval.decide';
    }
}
