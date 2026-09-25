<?php

declare(strict_types=1);

namespace Onhost\Domain\Services\Limits;

use Onhost\Domain\Identity\Authorization\ApprovalService;
use Onhost\Domain\Identity\Authorization\Models\Approval;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Orders\CheckoutService;
use Onhost\Domain\Orders\LimitRaiseLine;
use Onhost\Domain\Orders\Models\Order;
use Onhost\Domain\Orders\QuoteService;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;

/**
 * A raise at no charge (owner decision 8 + 13: money given away takes a second person).
 *
 * The proof is explicit: the approval the bus consumed for THIS command (`CommandContext::verifiedApprovalIds`, never an id the
 * caller merely offered and never a record found by searching for a payload hash), and that approval must name the organization,
 * the service, the number, the units and the price it waives — the price the console saw when it asked (`listPrice`). One
 * operator alone (`ONHOST_FOUR_EYES=false`) records `waived:single-operator`, like every other four-eyes action.
 *
 * What is given lasts one period: the order is a normal order of total 0 (the invoice shows the price and the waiver as its
 * discount), the raise gets a subscription that ends with its period and carries the list price, so switching its renewal
 * back on bills the option price (it renewed at 0 for ever before — review round 1).
 */
final class LimitRaiseService
{
    /** The command name the approval is recorded under (StaffCustomerCommand::name()). */
    public const ACTION = 'staff.customer.limit_raise.free';

    public function __construct(
        private readonly QuoteService $quotes,
        private readonly CheckoutService $checkout,
        private readonly LimitRaiseLine $lines,
    ) {}

    /**
     * What a raise of these units would cost per period — the price a free raise waives. Validates the raise the way the order
     * will (ownership, state, subscription, the number, the units) without writing anything.
     *
     * @return array{currency: string, net_minor: int, period: string}
     */
    public function listPrice(Organization $organization, string $serviceId, string $metric, int $units): array
    {
        $claimed = [];
        $line = $this->lines->build($organization, ['product_key' => LimitRaises::PRODUCT, 'config' => ['limit_raise' => ['service_id' => $serviceId, 'metric' => $metric, 'units' => $units]]],
            LimitRaiseLine::currencyFor($organization, $serviceId), 'l1', $claimed);

        return ['currency' => (string) $line['unit_net']->currency->value, 'net_minor' => (int) $line['unit_net']->minor, 'period' => (string) $line['period']];
    }

    /** @param array<string,mixed> $price what the request was asked (and approved) against */
    public function grantFree(Organization $organization, string $serviceId, string $metric, int $units, string $note, array $price, string $idempotencyKey, CommandContext $context): Order
    {
        $note = trim($note);
        if ($note === '') {
            throw new DomainError('note_required', 'Uveďte, proč se navýšení dává zdarma (tiket, kompenzace).', 422, ['field' => 'note']);
        }
        $bound = self::price($price);
        $waiver = new LimitRaiseWaiver($this->proof($organization, $serviceId, $metric, $units, $bound, $context), (string) $context->actorId, $note);
        $quote = $this->quotes->quote([['product_key' => LimitRaises::PRODUCT, 'config' => ['limit_raise' => ['service_id' => $serviceId, 'metric' => $metric, 'units' => $units]]]],
            LimitRaiseLine::currencyFor($organization, $serviceId), [], 1, null, $organization, 'cs', $waiver); // the service's billing currency
        $line = collect((array) $quote->lines)->first();
        $now = ['currency' => (string) $quote->currency, 'net_minor' => (int) data_get($line, 'unit_net', -1), 'period' => (string) data_get($line, 'period', '')];
        if ($now !== $bound) { // the option price moved after the approval: what was approved is not what would be given
            throw new DomainError('limit_raise_price_changed', 'Cena navýšení se od žádosti změnila; požádejte o schválení znovu.', 409, ['price' => $now, 'approved' => $bound]);
        }
        $staff = $context->actorType === 'user' && $context->actorId !== null ? User::query()->find($context->actorId) : null;
        $consents = [];
        foreach ($this->checkout->requiredDocuments($quote, $organization) as $key) { // as an assisted order: staff record whose request it is
            $consents[$key] = ['person' => mb_substr(($staff !== null ? (string) $staff->name : 'ONhost').' · navýšení zdarma: '.$note, 0, 250), 'language' => $organization->locale];
        }

        return $this->checkout->placeOrder($quote, $organization, null, $consents, ['mode' => 'wallet'], mb_substr('staff-limit-free:'.$idempotencyKey, 0, 190), $context, 'staff')['order'];
    }

    /**
     * @param  array{currency: string, net_minor: int, period: string}  $price
     * @return list<string>
     */
    private function proof(Organization $organization, string $serviceId, string $metric, int $units, array $price, CommandContext $context): array
    {
        $ids = $context->verifiedApprovalIds;
        if (! ApprovalService::enabled()) {
            if ($ids === ['waived:single-operator']) {
                return $ids;
            }
        } else {
            foreach ($ids as $id) {
                $approval = Approval::query()->find($id);
                if ($approval !== null && self::names($approval, $organization, $serviceId, $metric, $units, $price, (string) $context->actorId)) {
                    return [$approval->id];
                }
            }
        }

        throw new DomainError('limit_raise_waiver_unproven', 'Navýšení zdarma potřebuje schválení druhé osoby právě pro tuto službu, limit, počet a cenu.', 403, ['requirement' => 'approval']);
    }

    /** @param array{currency: string, net_minor: int, period: string} $price */
    private static function names(Approval $approval, Organization $organization, string $serviceId, string $metric, int $units, array $price, string $actorId): bool
    {
        $asked = (array) data_get($approval->payload, 'command.payload', []);

        return $approval->state === 'consumed' && $approval->action === self::ACTION
            && $approval->requested_by === $actorId && $approval->decided_by !== null && $approval->decided_by !== $actorId
            && ($asked['organization_id'] ?? null) === $organization->id && ($asked['service_id'] ?? null) === $serviceId
            && ($asked['metric'] ?? null) === $metric && (int) ($asked['units'] ?? 0) === $units
            && self::price((array) ($asked['price'] ?? [])) === $price;
    }

    /**
     * @param  array<string,mixed>  $price
     * @return array{currency: string, net_minor: int, period: string}
     */
    private static function price(array $price): array
    {
        return ['currency' => strtoupper((string) ($price['currency'] ?? '')), 'net_minor' => (int) ($price['net_minor'] ?? -1), 'period' => (string) ($price['period'] ?? '')];
    }
}
