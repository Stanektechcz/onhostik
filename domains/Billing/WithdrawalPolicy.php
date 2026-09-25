<?php

declare(strict_types=1);

namespace Onhost\Domain\Billing;

use Carbon\CarbonImmutable;
use Onhost\Domain\Billing\Models\ChargebackRequest;
use Onhost\Domain\Billing\Models\Withdrawal;
use Onhost\Domain\Invoicing\AccountingClock;
use Onhost\Domain\Orders\Models\Consent;
use Onhost\Domain\Orders\Models\Order;
use Onhost\Domain\Orders\Models\OrderItem;
use Onhost\Domain\Orders\OrderStateMachine;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Provisioning\AutomationLedger;
use Onhost\Domain\Services\IncludedServices;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Platform\Errors\DomainError;

/**
 * Who may withdraw from what (TASK-0025, owner decision 17). A consumer may leave a distance contract within fourteen days
 * of concluding it — the day the order was placed — without giving a reason; the notice keeps the deadline when it is SENT
 * in time. The decisive class is the one the order was placed as: a consumer who adds a company id later keeps the right, a
 * business never had it. A registered domain is fully performed the moment the registry records it (a statutory exception),
 * so it is never withdrawn once it exists. An add-on goes with the service it belongs to; a site carried by another service
 * has no contract of its own.
 */
final class WithdrawalPolicy
{
    public const RULE = 'billing.withdrawal';

    public const TERMS_URL = '/dokumenty/odstoupeni';

    public const STATES = [ServiceStateMachine::ACTIVE, ServiceStateMachine::DEGRADED, ServiceStateMachine::SUSPENDED, ServiceStateMachine::FAILED];

    public function __construct(private readonly AutomationLedger $ledger) {}

    public function enabled(): bool
    {
        return $this->ledger->enabled(self::RULE);
    }

    public static function days(): int
    {
        return max(14, (int) config('onhost.withdrawal.days', 14)); // statutory: never shorter
    }

    /**
     * The order line a service was bought with, its order, the deadline and the class the order was placed as.
     *
     * @return array{order:Order, item:OrderItem, contract_start:CarbonImmutable, deadline:CarbonImmutable, customer_class:string}
     *
     * @throws DomainError a refusal named by its code (see assertService)
     */
    public function forService(Service $service, CarbonImmutable $sentAt): array
    {
        $this->assertEnabled();
        if ($service->family === 'addon') {
            throw self::notApplicable('addon', 'Doplněk se odstupuje spolu se službou, ke které patří.');
        }
        if (IncludedServices::isIncluded($service)) {
            throw self::notApplicable('included_site', 'Tento web patří k jiné službě a nemá vlastní smlouvu; odstoupit lze od služby, ke které patří.');
        }
        if ($service->family === 'domain' || in_array($service->product_key, (array) config('onhost.withdrawal.excluded_products', ['domain']), true)) {
            throw self::notApplicable('domain_registered', 'Registrace domény je dokončena jejím zápisem do registru; od provedené registrace nelze odstoupit.');
        }
        $item = self::itemOf($service);
        $order = $item === null ? null : Order::query()->find($item->order_id);
        if ($item === null || $order === null || $order->placed_at === null) {
            throw self::notApplicable('no_order', 'Služba nevznikla objednávkou na dálku; napište prosím podpoře.');
        }
        $terms = $this->assertOrder($order, $sentAt);
        if (Withdrawal::query()->where('subject_key', 'item:'.$item->id)->exists()) {
            throw new DomainError('withdrawal_already_recorded', 'Odstoupení od této smlouvy už bylo přijato.', 409, ['withdrawal_id' => Withdrawal::query()->where('subject_key', 'item:'.$item->id)->value('id')]);
        }
        if (ChargebackRequest::query()->where('service_id', $service->id)->whereIn('state', [ChargebackRequest::CANCELLING, ChargebackRequest::REFUNDED])->exists()) {
            throw new DomainError('chargeback_in_progress', 'Služba se už ruší s vrácením kreditu; o její ukončení se postaráme v rámci té žádosti.', 409);
        }
        if (! in_array($service->state, self::STATES, true)) {
            throw new DomainError('service_state_invalid', 'Na službě právě běží jiná operace; zkuste to prosím za chvíli.', 409, ['state' => $service->state]);
        }

        return ['order' => $order, 'item' => $item] + $terms;
    }

    /**
     * A paid order nothing of which was delivered yet (held for review, or everything failed).
     *
     * @return array{order:Order, contract_start:CarbonImmutable, deadline:CarbonImmutable, customer_class:string}
     */
    public function forOrder(Order $order, CarbonImmutable $sentAt): array
    {
        $this->assertEnabled();
        if (! in_array($order->state, [OrderStateMachine::PAID, OrderStateMachine::FAILED], true) || $order->placed_at === null) {
            throw new DomainError('withdrawal_not_applicable', in_array($order->state, [OrderStateMachine::NEW, OrderStateMachine::PENDING_PAYMENT], true)
                ? 'Nezaplacenou objednávku zrušíte přímo, bez odstoupení.' : 'Z objednávky už běží služby; odstoupit lze od každé služby zvlášť.', 422, ['why' => 'order_state', 'state' => $order->state]);
        }
        if (OrderItem::query()->where('order_id', $order->id)->where(fn ($q) => $q->whereNotNull('service_id')->orWhereNotIn('state', ['pending', 'failed']))->exists()) {
            throw new DomainError('withdrawal_not_applicable', 'Z objednávky už běží služby; odstoupit lze od každé služby zvlášť.', 422, ['why' => 'delivered']);
        }
        $terms = $this->assertOrder($order, $sentAt);
        if (Withdrawal::query()->where('subject_key', 'order:'.$order->id)->exists()) {
            throw new DomainError('withdrawal_already_recorded', 'Odstoupení od této smlouvy už bylo přijato.', 409, ['withdrawal_id' => Withdrawal::query()->where('subject_key', 'order:'.$order->id)->value('id')]);
        }

        return ['order' => $order] + $terms;
    }

    /**
     * The same checks as a question: what the panel shows before the customer decides.
     *
     * @return array{eligible:bool, reason:?string, message:?string, why:?string, contract_start:?string, deadline:?string, customer_class:?string}
     */
    public function check(Service|Order $subject, ?CarbonImmutable $sentAt = null): array
    {
        $sentAt ??= CarbonImmutable::now();
        $order = $subject instanceof Order ? $subject : self::orderOf($subject);
        try {
            $terms = $subject instanceof Service ? $this->forService($subject, $sentAt) : $this->forOrder($subject, $sentAt);

            return ['eligible' => true, 'reason' => null, 'message' => null, 'why' => null, 'contract_start' => $terms['contract_start']->toIso8601String(), 'deadline' => $terms['deadline']->toIso8601String(), 'customer_class' => $terms['customer_class']];
        } catch (DomainError $e) {
            $start = $order === null ? null : CarbonImmutable::make($order->placed_at);

            return ['eligible' => false, 'reason' => $e->error, 'message' => $e->getMessage(), 'why' => isset($e->extra['why']) ? (string) $e->extra['why'] : null,
                'contract_start' => $start?->toIso8601String(), 'deadline' => $start === null ? null : self::deadlineFrom($start)->toIso8601String(), 'customer_class' => $order === null ? null : $this->classAtOrder($order)];
        }
    }

    /** The order line a service was bought with: its first line that was not a later plan change or upgrade. */
    private static function itemOf(Service $service): ?OrderItem
    {
        return OrderItem::query()->where('service_id', $service->id)->orderBy('created_at')->get()
            ->first(fn (OrderItem $i) => empty(data_get($i->config, 'plan_change')) && empty(data_get($i->config, 'upgrade_of')));
    }

    private static function orderOf(Service $service): ?Order
    {
        $item = self::itemOf($service);

        return $item === null ? null : Order::query()->find($item->order_id);
    }

    /**
     * What the cart tells a consumer before the order (TASK-0025): a domain registration cannot be withdrawn once it is done.
     *
     * @param  array<int|string,mixed>  $lines  quote lines
     * @return ?array{text:string, text_en:string, lines:list<string>, terms_url:string}
     */
    public static function checkoutNotice(array $lines, string $customerClass): ?array
    {
        if ($customerClass === 'b2b') {
            return null;
        }
        $excluded = (array) config('onhost.withdrawal.excluded_products', ['domain']);
        $names = [];
        foreach ($lines as $line) {
            if (! is_array($line)) {
                continue;
            }
            if (in_array((string) ($line['product_key'] ?? ''), $excluded, true) || (string) ($line['family'] ?? '') === 'domain') {
                $names[] = (string) ($line['name'] ?? data_get($line, 'config.fqdn') ?? $line['product_key'] ?? '');
            }
        }
        if ($names === []) {
            return null;
        }

        return ['text' => 'Registrace domény je dokončena jejím zápisem do registru, a od provedené registrace proto jako spotřebitel odstoupit nelze. Od ostatních služeb v objednávce můžete odstoupit do 14 dnů od objednávky.',
            'text_en' => 'A domain registration is complete once the registry records it, so as a consumer you cannot withdraw from a registration that has been made. You may withdraw from the other services of the order within 14 days of ordering.', 'lines' => $names, 'terms_url' => self::TERMS_URL];
    }

    public static function deadlineFrom(CarbonImmutable $contractStart): CarbonImmutable
    {
        $day = CarbonImmutable::parse(AccountingClock::date($contractStart), AccountingClock::timezone());

        return $day->addDays(self::days())->endOfDay();
    }

    /** The class the order was placed as: kept with the order since TASK-0025; before, a recorded waiver meant a consumer. */
    public function classAtOrder(Order $order): string
    {
        $kept = (string) data_get($order->meta, 'customer_class', '');
        if (in_array($kept, ['b2c', 'b2b'], true)) {
            return $kept;
        }
        if (Consent::query()->where('order_id', $order->id)->where('document_key', 'withdrawal_waiver')->exists()) {
            return 'b2c'; // the checkout asked the waiver of consumers only
        }

        return (string) (Organization::query()->whereKey($order->organization_id)->value('customer_class') ?: 'b2c');
    }

    /** @return array{contract_start:CarbonImmutable, deadline:CarbonImmutable, customer_class:string} */
    private function assertOrder(Order $order, CarbonImmutable $sentAt): array
    {
        $class = $this->classAtOrder($order);
        if ($class !== 'b2c') {
            throw new DomainError('withdrawal_consumers_only', 'Odstoupit od smlouvy bez udání důvodu může jen spotřebitel; objednávka byla uzavřena na firmu.', 403, ['customer_class' => $class]);
        }
        $start = CarbonImmutable::make($order->placed_at) ?? throw new DomainError('withdrawal_not_applicable', 'Objednávka nebyla odeslána.', 422, ['why' => 'no_order']);
        $deadline = self::deadlineFrom($start);
        if ($sentAt->greaterThan($deadline)) {
            throw new DomainError('withdrawal_period_over', 'Lhůta 14 dnů pro odstoupení uplynula '.$deadline->format('j. n. Y').'.', 409, ['deadline' => $deadline->toIso8601String()]);
        }
        if ($sentAt->lessThan($start)) {
            throw new DomainError('withdrawal_sent_before_order', 'Odstoupení nemůže předcházet objednávce.', 422, ['field' => 'sent_at']);
        }

        return ['contract_start' => $start, 'deadline' => $deadline, 'customer_class' => $class];
    }

    private function assertEnabled(): void
    {
        if (! $this->enabled()) {
            throw new DomainError('withdrawal_disabled', 'Odstoupení v panelu zatím není zapnuté; pošlete ho prosím e-mailem nebo dopisem podle poučení v dokumentu o odstoupení.', 409, ['terms_url' => self::TERMS_URL]);
        }
    }

    private static function notApplicable(string $why, string $message): DomainError
    {
        return new DomainError('withdrawal_not_applicable', $message, 422, ['why' => $why]);
    }
}
