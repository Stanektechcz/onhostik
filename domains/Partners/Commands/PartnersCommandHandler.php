<?php

declare(strict_types=1);

namespace Onhost\Domain\Partners\Commands;

use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Partners\Models\Partner;
use Onhost\Domain\Partners\Models\PartnerChangeRequest;
use Onhost\Domain\Partners\Models\PartnerPayout;
use Onhost\Domain\Partners\PartnerPresenters;
use Onhost\Domain\Partners\PartnerService;
use Onhost\Platform\Commands\Command;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Commands\CommandHandler;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Money\Money;

final class PartnersCommandHandler implements CommandHandler
{
    public function __construct(private readonly PartnerService $partners) {}

    public function handle(Command $command, CommandContext $context): mixed
    {
        if ($command instanceof PartnerPortalCommand) {
            return $this->portal($command, $context);
        }
        if (! $command instanceof PartnerCommand) {
            throw new \LogicException('Unsupported command '.get_class($command));
        }

        return match ($command->op()) {
            'approve' => PartnerPresenters::partner($this->partners->approve($this->partner($command), $context), true),
            'state' => PartnerPresenters::partner($this->partners->setState($this->partner($command), (string) $command->get('state'), (string) $command->get('reason', ''), $context), true),
            'payout.approve' => PartnerPresenters::payout($this->partners->approvePayout($this->payout($command), $context)),
            'payout.reject' => PartnerPresenters::payout($this->partners->rejectPayout($this->payout($command), (string) $command->get('reason', ''), $context)),
            'payout.pay' => PartnerPresenters::payout($this->partners->markPayoutPaid($this->payout($command), (string) $command->get('reference', ''), $context)),
            'tiers.recompute' => ['recomputed' => $this->partners->recomputeAllTiers()],
            'model.decide' => PartnerService::presentRequest($this->partners->decideModelChange(PartnerChangeRequest::query()->find((string) $command->get('request_id')) ?? throw DomainError::notFound('partner_request'), (string) $command->get('decision'), $command->get('note') !== null ? (string) $command->get('note') : null, $context)), // §5m-1
            default => throw new DomainError('partner_op_unknown', "Unknown partner operation {$command->op()}.", 422),
        };
    }

    private function portal(PartnerPortalCommand $command, CommandContext $context): mixed
    {
        $organization = Organization::query()->find($command->organizationId);
        if ($organization === null) {
            throw DomainError::notFound('organization');
        }
        if ($command->op() === 'apply') {
            return PartnerPresenters::partner($this->partners->apply($organization, $command->payload, $context));
        }
        $partner = $this->partners->partnerFor($organization);
        if ($partner === null) {
            throw new DomainError('partner_missing', 'This organization is not a partner.', 404);
        }

        return match ($command->op()) {
            'payout.request' => PartnerPresenters::payout($this->partners->requestPayout($partner, Money::decimal((string) $command->get('amount', '0'), $partner->currency), (string) $command->get('iban', ''), $context, (string) $command->get('method', 'bank_transfer'))),
            'whitelabel' => PartnerPresenters::partner($this->partners->updateWhitelabel($partner, $command->payload, $context)),
            'change.request' => PartnerService::presentRequest($this->partners->requestChange($partner, (string) $command->get('kind'), (string) $command->get('value'), $command->get('note') !== null ? (string) $command->get('note') : null, $context)), // §5n-1
            'model.request' => PartnerService::presentRequest($this->partners->requestModelChange($partner, (string) $command->get('model'), $command->get('note') !== null ? (string) $command->get('note') : null, $context)), // §5m-1
            default => throw new DomainError('partner_op_unknown', "Unknown partner operation {$command->op()}.", 422),
        };
    }

    private function partner(PartnerCommand $command): Partner
    {
        $id = (string) $command->get('partner_id');
        $partner = Partner::query()->find($id) ?? Partner::query()->where('code', strtoupper($id))->first();
        if ($partner === null) {
            throw DomainError::notFound('partner');
        }

        return $partner;
    }

    private function payout(PartnerCommand $command): PartnerPayout
    {
        $id = (string) $command->get('payout_id');
        $payout = PartnerPayout::query()->find($id) ?? PartnerPayout::query()->where('number', strtoupper($id))->first();
        if ($payout === null) {
            throw DomainError::notFound('partner_payout');
        }

        return $payout;
    }
}
