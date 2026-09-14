<?php

declare(strict_types=1);

namespace Onhost\Domain\Loyalty\Commands;

use Onhost\Domain\Loyalty\LoyaltyService;
use Onhost\Domain\Loyalty\MissionService;
use Onhost\Domain\Loyalty\Models\Referral;
use Onhost\Domain\Loyalty\ReferralService;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Platform\Commands\Command;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Commands\CommandHandler;
use Onhost\Platform\Errors\DomainError;

final class LoyaltyCommandHandler implements CommandHandler
{
    public function __construct(private readonly LoyaltyService $loyalty, private readonly ReferralService $referrals, private readonly MissionService $missions) {}

    public function handle(Command $command, CommandContext $context): mixed
    {
        if ($command instanceof AccountLoyaltyCommand) {
            $organization = Organization::query()->findOrFail($command->organizationId);

            return match ($command->op()) {
                'referral.code' => $this->referrals->summary($organization),
                'missions.evaluate' => $this->missions->evaluate($organization) + ['summary' => $this->missions->summary($organization->refresh(), null, (string) $command->get('locale', 'cs'))],
                default => throw new DomainError('op_unknown', 'Unknown loyalty operation.', 422),
            };
        }
        if (! $command instanceof LoyaltyCommand) {
            throw new \LogicException('Unsupported command '.get_class($command));
        }

        return match ($command->op()) {
            'levels' => ['levels' => $this->loyalty->setLevels((array) $command->get('levels', []), $context->actorType.':'.($context->actorId ?? 'system'))],
            'missions' => ['missions' => $this->missions->setCatalogue((array) $command->get('missions', []), $context->actorType.':'.($context->actorId ?? 'system'))], // the catalogue in settings (audit §5k-5)
            'campaigns' => ['campaigns' => $this->missions->setCampaigns((array) $command->get('campaigns', []), $context->actorType.':'.($context->actorId ?? 'system'))], // campaigns (audit §5l-5)
            'referral.review' => $this->referrals->present($this->referrals->review(Referral::query()->find((string) $command->get('referral_id')) ?? throw DomainError::notFound('referral'), (string) $command->get('decision'), $context, $command->get('note') !== null ? (string) $command->get('note') : null)), // fraud review (audit §5l-4)
            'award' => (function () use ($command, $context) {
                $organization = $this->organization($command);
                $points = (int) $command->get('points');
                if ($points < 1 || $points > 10000) {
                    throw new DomainError('loyalty_points_invalid', 'Award between 1 and 10 000 points.', 422, ['field' => 'points']);
                }

                return $this->loyalty->award($organization->id, 'manual', 'staff:'.now()->format('YmdHis.u'), $points, (string) $command->get('note', 'Odměna od podpory'), $context);
            })(),
            // the streak discount (audit §5j-3): finance grants (or removes with 0) the permanent percentage
            'streak.approve' => (function () use ($command, $context) {
                $organization = $this->missions->approveStreakDiscount($this->organization($command), (float) $command->get('percent', 0), $context, $command->get('note') !== null ? (string) $command->get('note') : null);

                return ['organization_id' => $organization->id, 'discount' => data_get($organization->settings, 'loyalty_discount'), 'streak' => $this->missions->streak($organization)];
            })(),
            default => throw new DomainError('op_unknown', 'Unknown loyalty operation.', 422),
        };
    }

    private function organization(LoyaltyCommand $command): Organization
    {
        return Organization::query()->find((string) $command->get('organization_id')) ?? throw DomainError::notFound('organization');
    }
}
