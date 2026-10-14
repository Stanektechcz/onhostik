<?php

declare(strict_types=1);

namespace Onhost\Domain\Advisories\Commands;

use Onhost\Domain\Advisories\AdvisoryCases;
use Onhost\Domain\Advisories\Models\AdvisoryCase;
use Onhost\Platform\Commands\Command;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Commands\CommandHandler;
use Onhost\Platform\Errors\DomainError;

final class AdvisoryCommandHandler implements CommandHandler
{
    public function __construct(private readonly AdvisoryCases $cases) {}

    public function handle(Command $command, CommandContext $context): mixed
    {
        if (! $command instanceof AdvisoryCommand) {
            throw new \LogicException('Unsupported command '.get_class($command));
        }
        $case = AdvisoryCase::query()->find((string) $command->get('case_id')) ?? throw DomainError::notFound('advisory_case');
        $note = $command->get('note') === null ? null : (string) $command->get('note');

        $case = match ($command->op()) {
            'case.triage' => $this->cases->triage($case, (string) $command->get('decision'), $note, $context),
            'case.approve' => (function () use ($case, $command, $note, $context): AdvisoryCase {
                $risky = (bool) $command->get('include_risky', false);
                // the four eyes were asked for the number of sites the console showed; a plan that grew past the limit since needs them again
                if (! $command->requiresApproval() && AdvisoryCases::targets($case, $risky) > AdvisoryCommand::singleApproverMax()) {
                    throw new DomainError('advisory_plan_changed', 'The plan now changes more sites than one person may approve. Reload the case and approve it again.', 409, ['plan_revision' => $case->plan_revision]);
                }

                return $this->cases->approve($case, $risky, (int) $command->get('plan_revision'), $note, $context);
            })(),
            'case.notify' => $this->cases->notify($case, $note, $context),
            'case.draft_post' => (function () use ($case): AdvisoryCase {
                $this->cases->draftPost($case, true);

                return $case->refresh();
            })(),
            default => throw new DomainError('advisory_op_unknown', "Unknown advisory operation {$command->op()}.", 422, ['field' => 'op']),
        };

        return ['id' => $case->id, 'state' => $case->state, 'wave' => $case->wave, 'post_id' => $case->post_id, 'announced_at' => $case->announced_at?->toIso8601String()];
    }
}
