<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Onhost\Domain\Billing\Models\BillingPeriod;
use Onhost\Domain\Billing\Models\DunningCase;
use Onhost\Domain\Billing\Models\RatedUsage;
use Onhost\Domain\Billing\Models\Subscription;
use Onhost\Domain\Billing\RatingService;
use Onhost\Domain\Billing\SubscriptionService;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Services\Models\Service;
use Onhost\Platform\Commands\CommandScope;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Money\Money;

/** Subscriptions, metered usage of the current period and open dunning for the signed-in organization. */
final class BillingController extends ApiController
{
    public function subscriptions(Request $request): JsonResponse
    {
        $organization = $this->api->organization($request);
        $this->api->authorize($request, 'billing.invoice.read', CommandScope::organization($organization->id));

        return $this->api->paginate($request, Subscription::query()->where('organization_id', $organization->id), fn (Subscription $s) => $this->subscription($s), 'next_renewal_at');
    }

    public function cancelSubscription(Request $request, SubscriptionService $subscriptions, string $subscription): JsonResponse
    {
        $model = $this->resolve($request, $subscription);
        $data = $request->validate(['cancel' => ['required', 'boolean']]);
        $updated = $subscriptions->cancelAtPeriodEnd($model, (bool) $data['cancel'], $this->api->context($request, Organization::query()->find($model->organization_id)));

        return response()->json(['data' => $this->subscription($updated)]);
    }

    public function autoRenew(Request $request, SubscriptionService $subscriptions, string $subscription): JsonResponse
    {
        $model = $this->resolve($request, $subscription);
        $data = $request->validate(['enabled' => ['required', 'boolean']]);

        return response()->json(['data' => $this->subscription($subscriptions->setAutoRenew($model, (bool) $data['enabled'], $this->api->context($request, Organization::query()->find($model->organization_id))))]);
    }

    /** Current calendar month: rated usage per service, applied caps, total; plus the price card the customer saw (per hour + cap). */
    public function usage(Request $request, RatingService $rating): JsonResponse
    {
        $organization = $this->api->organization($request);
        $this->api->authorize($request, 'billing.wallet.read', CommandScope::organization($organization->id));
        $currency = $organization->currency;
        $period = BillingPeriod::query()->where('organization_id', $organization->id)->where('currency', $currency)->whereDate('period_start', now()->startOfMonth()->toDateString())->first();
        $perService = [];
        if ($period !== null) {
            $rows = RatedUsage::query()->where('billing_period_id', $period->id)->selectRaw('service_id, sum(amount_minor) as amount, count(*) as events, sum(case when charged_transaction_id is null and amount_minor > 0 then amount_minor else 0 end) as unpaid')->groupBy('service_id')->get();
            foreach ($rows as $row) {
                $service = Service::query()->withTrashed()->find($row->service_id);
                $perService[] = ['service_id' => $row->service_id, 'name' => $service?->name, 'hostname' => $service?->hostname, 'amount' => Money::minor((int) $row->amount, $currency), 'events' => (int) $row->events, 'unpaid' => Money::minor((int) $row->unpaid, $currency), 'cap' => $service ? $rating->monthlyCap($service, $currency) : null, 'cap_hit' => isset($period->cap_applied[$row->service_id])];
            }
        }
        $metered = Service::query()->where('organization_id', $organization->id)->whereIn('family', ['cloud', 'data', 'game', 'apps'])->whereIn('state', ['ACTIVE', 'DEGRADED', 'RESIZING', 'SUSPENDED'])->get()->map(fn (Service $s) => ['service_id' => $s->id, 'name' => $s->name, 'unit_price' => $rating->unitPrice($s, $s->family === 'game' ? 'game_days' : 'vm_hours', $currency), 'unit' => $s->family === 'game' ? 'day' : 'hour', 'monthly_cap' => $rating->monthlyCap($s, $currency)])->all();

        return response()->json(['data' => ['period' => $period ? ['start' => $period->period_start->toDateString(), 'end' => $period->period_end->toDateString(), 'total' => $period->total(), 'state' => $period->state] : null, 'services' => $perService, 'price_cards' => $metered, 'currency' => $currency]]);
    }

    public function dunning(Request $request): JsonResponse
    {
        $organization = $this->api->organization($request);
        $this->api->authorize($request, 'billing.invoice.read', CommandScope::organization($organization->id));
        $cases = DunningCase::query()->where('organization_id', $organization->id)->whereNotIn('state', [DunningCase::RESOLVED, DunningCase::TERMINATED])->orderBy('due_at')->get()->map(fn (DunningCase $c) => self::case($c))->all();

        return response()->json(['data' => $cases, 'limited' => (bool) data_get($organization->settings, 'billing.limited', false)]);
    }

    public static function case(DunningCase $c): array
    {
        return ['id' => $c->id, 'state' => $c->state, 'ui' => DunningCase::machine()->toArray()[$c->state]['ui'] ?? null, 'invoice_id' => $c->invoice_id, 'service_id' => $c->service_id, 'due_at' => $c->due_at?->toIso8601String(), 'days_overdue' => $c->due_at ? max(0, (int) $c->due_at->diffInDays(now(), false)) : 0, 'notices_sent' => $c->notices_sent ?? [], 'suspended_at' => $c->suspended_at?->toIso8601String(), 'termination_at' => $c->termination_at?->toIso8601String(), 'resolved_at' => $c->resolved_at?->toIso8601String()];
    }

    private function subscription(Subscription $s): array
    {
        $service = $s->service_id ? Service::query()->withTrashed()->find($s->service_id) : null;

        return ['id' => $s->id, 'service_id' => $s->service_id, 'domain_id' => $s->domain_id, 'name' => $service?->name, 'hostname' => $service?->hostname, 'period' => $s->period, 'amount' => Money::minor((int) $s->amount_minor, $s->currency), 'state' => $s->state, 'auto_renew' => (bool) $s->auto_renew, 'cancel_at_period_end' => (bool) $s->cancel_at_period_end, 'renewal_priority' => $s->renewal_priority, 'current_period_start' => $s->current_period_start?->toIso8601String(), 'current_period_end' => $s->current_period_end?->toIso8601String(), 'next_renewal_at' => $s->next_renewal_at?->toIso8601String(), 'last_renewed_at' => $s->last_renewed_at?->toIso8601String(), 'renewal_failures' => $s->renewal_failures];
    }

    private function resolve(Request $request, string $id): Subscription
    {
        $subscription = Subscription::query()->find($id);
        if ($subscription === null) {
            throw DomainError::notFound('subscription');
        }
        $this->api->authorize($request, 'billing.wallet.topup', CommandScope::organization($subscription->organization_id));

        return $subscription;
    }
}
