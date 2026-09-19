<?php

declare(strict_types=1);

namespace Onhost\Domain\Support\Commands;

use Onhost\Domain\Support\Models\Ticket;
use Onhost\Domain\Support\Models\WorkOffer;
use Onhost\Domain\Support\WorkOfferService;
use Onhost\Platform\Commands\Command;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Commands\CommandHandler;
use Onhost\Platform\Errors\DomainError;

final class WorkOfferCommandHandler implements CommandHandler
{
    public function __construct(private readonly WorkOfferService $offers) {}

    public function handle(Command $command, CommandContext $context): mixed
    {
        if ($command instanceof WorkOfferDecisionCommand) {
            $offer = WorkOffer::query()->where('organization_id', $command->organizationId)->find((string) $command->get('offer_id'));
            if ($offer === null) { // an offer of another organization does not exist for this one
                throw DomainError::notFound('work offer');
            }

            return ['data' => WorkOfferService::present($this->offers->decide($offer, (bool) $command->get('approve'), $context, $command->get('note'), $command->get('author_name')))];
        }
        if (! $command instanceof WorkOfferStaffCommand) {
            throw new \LogicException('Unsupported command '.get_class($command));
        }

        return ['data' => WorkOfferService::present(match ($command->op()) {
            'propose' => $this->offers->propose(Ticket::query()->find((string) $command->get('ticket_id')) ?? throw DomainError::notFound('ticket'), $command->payload, $context),
            'withdraw' => $this->offers->withdraw($this->offer($command), $context, (string) $command->get('reason', '')),
            'complete' => $this->offers->complete($this->offer($command), $context),
            default => throw new DomainError('op_unknown', 'Unknown work offer operation.', 422, ['field' => 'op']),
        })];
    }

    private function offer(WorkOfferStaffCommand $command): WorkOffer
    {
        return WorkOffer::query()->find((string) $command->get('offer_id')) ?? throw DomainError::notFound('work offer');
    }
}
