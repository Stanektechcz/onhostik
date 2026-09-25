<?php

declare(strict_types=1);

use Onhost\Platform\Commands\Command;
use Onhost\Platform\Commands\CommandBus;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Commands\CommandHandler;
use Onhost\Platform\Commands\CommandScope;

/*
 * `CommandContext::verifiedApprovalIds` is what the authorizer consumed for THE command a handler runs (TASK-0022). A command
 * dispatched from inside a handler that already ran with a second person's approval must not inherit that approval: each
 * handler sees only what was consumed for its own command.
 */

class VerifiedApprovalsProbeCommand implements Command
{
    /** @var list<list<string>> */
    public static array $seen = [];

    public function __construct(public readonly string $organizationId, public readonly string $key) {}

    public function permission(): ?string
    {
        return 'project.manage';
    }

    public function scope(): ?CommandScope
    {
        return CommandScope::organization($this->organizationId);
    }

    public function idempotencyKey(): string
    {
        return $this->key;
    }

    public function name(): string
    {
        return 'test.verified_approvals.probe';
    }

    public function toAudit(): array
    {
        return [];
    }
}

class VerifiedApprovalsProbeCommandHandler implements CommandHandler
{
    public function handle(Command $command, CommandContext $context): mixed
    {
        VerifiedApprovalsProbeCommand::$seen[] = $context->verifiedApprovalIds;

        return ['ok' => true];
    }
}

it('does not hand an outer command\'s consumed approvals to a command dispatched inside its handler', function () {
    VerifiedApprovalsProbeCommand::$seen = [];
    [$owner, $org] = $this->customerWithOrganization();
    // the context a handler receives after the bus consumed a second person's approval for ITS command
    $outer = $this->contextFor($owner, $org)->withVerifiedApprovals(['apr-outer']);

    app(CommandBus::class)->dispatch(new VerifiedApprovalsProbeCommand($org->id, 'probe-nested-1'), $outer);

    expect(VerifiedApprovalsProbeCommand::$seen)->toBe([[]]);
});
