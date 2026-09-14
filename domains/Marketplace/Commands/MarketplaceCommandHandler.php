<?php

declare(strict_types=1);

namespace Onhost\Domain\Marketplace\Commands;

use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Marketplace\MarketplaceService;
use Onhost\Domain\Marketplace\Models\MarketplaceListing;
use Onhost\Domain\Marketplace\Models\MarketplaceOrder;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Partners\Models\Partner;
use Onhost\Platform\Commands\Command;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Commands\CommandHandler;
use Onhost\Platform\Errors\DomainError;

final class MarketplaceCommandHandler implements CommandHandler
{
    public function __construct(private readonly MarketplaceService $marketplace) {}

    public function handle(Command $command, CommandContext $context): mixed
    {
        if ($command instanceof MarketplaceStaffCommand) {
            return match ($command->op()) {
                'listing.state' => $this->marketplace->presentListing($this->marketplace->setListingState($this->listing((string) $command->get('listing_id')), (string) $command->get('state'), $command->get('reason') !== null ? (string) $command->get('reason') : null, $context), true),
                'dispute.resolve' => $this->marketplace->presentOrder($this->marketplace->resolveDispute($this->order((string) $command->get('order_id')), (string) $command->get('decision'), (string) $command->get('reason', ''), $context), true),
                default => throw new DomainError('op_unknown', 'Unknown marketplace operation.', 422),
            };
        }
        if (! $command instanceof MarketplaceCommand) {
            throw new DomainError('command_unsupported', 'Unsupported command.', 500);
        }
        $organization = Organization::query()->findOrFail($command->organizationId);
        $input = array_diff_key($command->payload, array_flip(['op', 'listing_id', 'order_id']));

        return match ($command->op()) {
            'order' => $this->marketplace->presentOrder($this->marketplace->order($organization, $context->actorType === 'user' && $context->actorId ? User::query()->find($context->actorId) : null, $this->listing((string) $command->get('listing_id')), $input, $context)),
            'accept' => $this->marketplace->presentOrder($this->marketplace->accept($this->order((string) $command->get('order_id')), $organization, $context)),
            'dispute' => $this->marketplace->presentOrder($this->marketplace->dispute($this->order((string) $command->get('order_id')), $organization, (string) $command->get('reason', ''), $context)),
            'cancel' => $this->marketplace->presentOrder($this->marketplace->cancel($this->order((string) $command->get('order_id')), $organization, $context)),
            'listing.create' => $this->marketplace->presentListing($this->marketplace->createListing($this->partner($organization), $input, $context)),
            'listing.update' => $this->marketplace->presentListing($this->marketplace->updateListing($this->partner($organization), $this->listing((string) $command->get('listing_id')), $input, $context)),
            'listing.state' => $this->marketplace->presentListing($this->marketplace->setListingState($this->listing((string) $command->get('listing_id')), (string) $command->get('state'), null, $context, $this->partner($organization))),
            'order.start' => $this->marketplace->presentOrder($this->marketplace->start($this->order((string) $command->get('order_id')), $this->partner($organization), $context)),
            'order.evidence' => $this->marketplace->attachEvidence($this->order((string) $command->get('order_id')), $this->partner($organization), (string) $command->get('key'), (string) $command->get('tmp_path'), (string) $command->get('name'), (string) $command->get('mime'), (int) $command->get('size'), $context), // §5p-3
            'order.deliver' => $this->marketplace->presentOrder($this->marketplace->deliver($this->order((string) $command->get('order_id')), $this->partner($organization), (string) $command->get('note', ''), $context, (array) $command->get('evidence', []))),
            default => throw new DomainError('op_unknown', 'Unknown marketplace operation.', 422),
        };
    }

    private function partner(Organization $organization): Partner
    {
        $partner = Partner::query()->where('organization_id', $organization->id)->first();
        if ($partner === null || $partner->state !== 'active') {
            throw new DomainError('partner_not_active', 'The partner portal is available to approved partners.', 403, ['state' => $partner?->state]);
        }

        return $partner;
    }

    private function listing(string $id): MarketplaceListing
    {
        return MarketplaceListing::query()->find($id) ?? throw DomainError::notFound('marketplace_listing');
    }

    private function order(string $id): MarketplaceOrder
    {
        return MarketplaceOrder::query()->find($id) ?? throw DomainError::notFound('marketplace_order');
    }
}
