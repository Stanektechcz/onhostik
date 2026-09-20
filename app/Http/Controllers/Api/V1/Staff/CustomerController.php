<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Staff;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Presenters\Presenters;
use App\Http\StaffReadAudit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Onhost\Domain\Domains\Models\Domain;
use Onhost\Domain\Domains\Models\RegistrarConnection;
use Onhost\Domain\Invoicing\Models\Invoice;
use Onhost\Domain\Loyalty\Models\Referral;
use Onhost\Domain\Orders\Commands\ReviewOrderCommand;
use Onhost\Domain\Orders\Commands\StaffCancelOrderCommand;
use Onhost\Domain\Orders\Commands\StaffCustomerCommand;
use Onhost\Domain\Orders\Models\Order;
use Onhost\Domain\Orders\QuoteService;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Organizations\Models\OrganizationMembership;
use Onhost\Domain\Provisioning\Commands\ProvisioningCommand;
use Onhost\Domain\Risk\RiskWeights;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\WalletLedger\WalletService;
use Onhost\Platform\Commands\CommandScope;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Money\Money;
use Symfony\Component\HttpFoundation\Response;

/** Admin `#/zakaznici`, `#/objednavky`, `#/sluzby`: cross-organization read for staff with `staff.customer.read`. */
final class CustomerController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $this->api->authorize($request, 'staff.customer.read', CommandScope::global());
        $query = Organization::query();
        if ($request->filled('q')) {
            $q = '%'.strtolower((string) $request->query('q')).'%';
            $query->where(fn ($w) => $w->whereRaw('lower(name) like ?', [$q])->orWhereRaw('lower(billing_email) like ?', [$q])->orWhere('ico', 'like', $q));
        }

        return $this->api->paginate($request, $query, fn (Organization $o) => Presenters::organization($o) + ['services' => Service::query()->where('organization_id', $o->id)->count(), 'domains' => Domain::query()->where('organization_id', $o->id)->count()]);
    }

    public function show(Request $request, WalletService $wallets, string $organization): JsonResponse
    {
        $this->api->authorize($request, 'staff.customer.read', CommandScope::global());
        $org = Organization::query()->find($organization);
        if ($org === null) {
            throw DomainError::notFound('organization');
        }
        app(StaffReadAudit::class)->record($request, $this->api->context($request, $org), 'customer', $org->id, 'organization', $org->id); // a look at a customer's account is an event, and the customer sees it
        $members = OrganizationMembership::query()->with('user')->where('organization_id', $org->id)->get()->map(fn ($m) => ['user_id' => $m->user_id, 'email' => $m->user?->email, 'name' => $m->user?->name, 'role' => $m->role_key, 'state' => $m->state])->all();

        return response()->json(['data' => Presenters::organization($org) + [
            'members' => $members, 'wallet' => $wallets->balances($org, $org->currency),
            'registrar_connections' => RegistrarConnection::query()->where('organization_id', $org->id)->orderByDesc('created_at')->get()->map(fn (RegistrarConnection $c) => Presenters::registrarConnection($c))->all(),
            'services' => Service::query()->where('organization_id', $org->id)->orderByDesc('created_at')->limit(100)->get()->map(fn (Service $s) => Presenters::service($s))->all(),
            'domains' => Domain::query()->where('organization_id', $org->id)->orderBy('expires_at')->limit(100)->get()->map(fn (Domain $d) => Presenters::domain($d))->all(),
            'orders' => Order::query()->where('organization_id', $org->id)->orderByDesc('placed_at')->limit(50)->get()->map(fn (Order $o) => Presenters::order($o, false) + ['source' => $o->source, 'bank_instructions' => $o->meta['bank_instructions'] ?? null])->all(), // §5y: staff confirm a transfer by its variable symbol
            'spendable' => $wallets->spendable($org, $org->currency),
            'can' => ['place_order' => $this->api->can($request, 'staff.order.manage', CommandScope::global()), 'move_money' => $this->api->can($request, 'billing.credit.adjust', CommandScope::global())], // H348: servicing a customer and moving their money are two permissions
            'invoices' => Invoice::query()->where('organization_id', $org->id)->where('state', '!=', Invoice::DRAFT)->orderByDesc('issued_at')->limit(50)->get()->map(fn (Invoice $i) => Presenters::invoice($i))->all(),
        ]]);
    }

    public function orders(Request $request): JsonResponse
    {
        $this->api->authorize($request, 'staff.order.manage', CommandScope::global());
        $query = Order::query();
        if ($request->filled('state')) {
            $query->where('state', strtoupper((string) $request->query('state')));
        }
        if ($request->filled('review')) { // orders held by the intake pre-check (audit §5f-8): ?review=pending
            $query->where('meta->review->state', (string) $request->query('review'));
        }

        return $this->api->paginate($request, $query, fn (Order $o) => Presenters::order($o, false) + ['organization_id' => $o->organization_id, 'organization' => Organization::query()->whereKey($o->organization_id)->value('name')], 'placed_at');
    }

    /** Release or reject an order the intake pre-check held (audit §5f-8). */
    /**
     * The risk model review (audit §5i-4): every held order with its signals next to the outcome, and per signal how
     * often a hold on it was released or rejected — so staff see which signals earn their weight. `format=csv` exports.
     */
    /**
     * A cell that starts with = + - @ (or a tab / carriage return before one) is a FORMULA to a spreadsheet, and the
     * organization name in this export is whatever a customer typed at registration: `=cmd|'/C …'!A1` ran on the analyst's
     * machine when the file was opened. Such a cell is prefixed with an apostrophe, which a spreadsheet shows as text.
     */
    private static function csvCell(mixed $value): string
    {
        $text = (string) $value;
        if ($text !== '' && preg_match('/^[\s]*[=+\-@]/', $text) === 1) {
            $text = "'".$text;
        }

        return '"'.str_replace('"', '""', $text).'"';
    }

    public function riskReview(Request $request): JsonResponse|Response
    {
        $this->api->authorize($request, 'staff.order.manage', CommandScope::global());
        $days = max(1, min(365, (int) $request->query('days', 90)));
        $orders = Order::query()->whereNotNull('meta->review->state')->where('created_at', '>=', now()->subDays($days))->orderByDesc('created_at')->limit(500)->get();
        $organizations = Organization::query()->whereIn('id', $orders->pluck('organization_id'))->get()->keyBy('id');
        $signals = [];
        $rows = $orders->map(function (Order $o) use ($organizations, &$signals) {
            $review = (array) data_get($o->meta, 'review', []);
            $reasons = array_values(array_map('strval', (array) data_get($o->meta, 'risk.reasons', [])));
            $outcome = (string) ($review['state'] ?? 'pending');
            foreach ($reasons as $reason) {
                $signals[$reason] = ($signals[$reason] ?? ['held' => 0, 'released' => 0, 'rejected' => 0, 'pending' => 0]);
                $signals[$reason]['held']++;
                $signals[$reason][in_array($outcome, ['released', 'rejected'], true) ? $outcome : 'pending']++;
            }

            return ['order' => $o->number, 'organization' => $organizations->get($o->organization_id)?->name, 'score' => (int) ($review['score'] ?? data_get($o->meta, 'risk.score', 0)), 'reasons' => $reasons, 'outcome' => $outcome, 'decided_at' => $review['decided_at'] ?? null, 'decision_reason' => $review['reason'] ?? null, 'total' => Money::minor((int) $o->total_minor, (string) $o->currency), 'placed_at' => $o->created_at?->toIso8601String()];
        })->all();
        // §5o-4: the referral loop's decisions next to the order loop's, in the same precision table (one weight table behind both)
        $referrals = Referral::query()->whereIn('state', [Referral::HELD, Referral::REFUSED, Referral::REWARDED, Referral::CLAWBACK])->where('score', '>', 0)->where('created_at', '>=', now()->subDays($days))->orderByDesc('created_at')->limit(500)->get();
        $referralOrgs = Organization::query()->whereIn('id', $referrals->pluck('referred_organization_id')->merge($referrals->pluck('referrer_organization_id')))->get()->keyBy('id');
        $referralRows = $referrals->filter(fn (Referral $r) => $r->state !== Referral::REWARDED || $r->decided_at !== null)->map(function (Referral $r) use ($referralOrgs, &$signals) {
            $outcome = match ($r->state) {
                Referral::HELD => 'pending', Referral::REWARDED => 'released', default => 'rejected'
            };
            $reasons = array_values(array_map('strval', (array) ($r->signals ?? [])));
            foreach ($reasons as $reason) {
                $signals[$reason] = ($signals[$reason] ?? ['held' => 0, 'released' => 0, 'rejected' => 0, 'pending' => 0]);
                $signals[$reason]['held']++;
                $signals[$reason][$outcome]++;
            }

            return ['loop' => 'referral', 'referral' => $r->id, 'code' => $r->code, 'organization' => $referralOrgs->get($r->referred_organization_id)?->name, 'referrer' => $referralOrgs->get($r->referrer_organization_id)?->name, 'score' => (int) $r->score, 'reasons' => $reasons, 'outcome' => $outcome, 'state' => $r->state, 'decided_at' => $r->decided_at?->toIso8601String(), 'decision_reason' => $r->reason];
        })->values()->all();
        foreach ($signals as $reason => &$s) {
            $decided = $s['released'] + $s['rejected'];
            $s['precision'] = $decided > 0 ? round($s['rejected'] / $decided, 2) : null; // the share of decided holds that were right
        }
        unset($s);
        if ((string) $request->query('format', '') === 'csv') {
            $lines = ['loop;order;organization;score;reasons;outcome;decided_at;decision_reason;total'];
            foreach ($rows as $r) {
                $lines[] = implode(';', array_map(fn ($v) => self::csvCell($v), ['order', $r['order'], $r['organization'], $r['score'], implode('|', $r['reasons']), $r['outcome'], $r['decided_at'], $r['decision_reason'], $r['total']->format()]));
            }
            foreach ($referralRows as $r) { // §5o-4
                $lines[] = implode(';', array_map(fn ($v) => self::csvCell($v), ['referral', $r['code'], $r['organization'], $r['score'], implode('|', $r['reasons']), $r['outcome'], $r['decided_at'], $r['decision_reason'], '']));
            }

            return response(implode("\n", $lines), 200, ['Content-Type' => 'text/csv; charset=utf-8', 'Content-Disposition' => 'attachment; filename="risk-review.csv"']);
        }

        return response()->json(['data' => ['days' => $days, 'held' => count($rows) + count($referralRows), 'signals' => $signals, 'orders' => $rows, 'referrals' => $referralRows, 'weights' => app(RiskWeights::class)->weights()]]); // §5o-4: both loops, one table
    }

    /** A service for a customer without an order (audit §5o): the console's "Založit herní server" and `onhost:game:create`. */
    public function createService(Request $request, string $organization): JsonResponse
    {
        $data = $request->validate(['product_key' => ['required', 'string', 'max:40'], 'plan_key' => ['nullable', 'string', 'max:40'], 'config' => ['nullable', 'array'], 'config.egg' => ['nullable', 'string', 'max:40'], 'config.version' => ['nullable', 'string', 'max:20'], 'config.label' => ['nullable', 'string', 'max:80'], 'config.region' => ['nullable', 'string', 'max:16'], 'config.environment' => ['nullable', 'array', 'max:30'], 'reason' => ['nullable', 'string', 'max:250']]);

        return $this->dispatch(new ProvisioningCommand($this->idempotencyKey($request, "service.create:{$organization}:".now()->format('YmdHis')), ['op' => 'service.create', 'organization_id' => $organization, 'product_key' => $data['product_key'], 'plan_key' => $data['plan_key'] ?? null, 'config' => (array) ($data['config'] ?? [])]), $this->api->context($request, null, $data['reason'] ?? null), 201);
    }

    /** Sandbox tenant (audit §5j-9): provisioning to lab instances, promo credit to test with, no loyalty or commissions. */
    public function sandbox(Request $request, string $organization): JsonResponse
    {
        $data = $request->validate(['enabled' => ['required', 'boolean'], 'reason' => ['nullable', 'string', 'max:250']]);

        return $this->dispatch(new ProvisioningCommand($this->idempotencyKey($request, 'tenant.sandbox:'.$organization.':'.now()->format('YmdHi')), ['op' => 'tenant.sandbox', 'organization_id' => $organization, 'enabled' => (bool) $data['enabled']]), $this->api->context($request, null, $data['reason'] ?? null));
    }

    /** Manual wallet credit (audit §5y): amount, kind manual|promo, the reason — HIGH, needs a fresh step-up. */
    public function creditWallet(Request $request, string $organization): JsonResponse
    {
        $data = $request->validate(['amount' => ['required', 'numeric', 'min:0.01', 'max:10000000'], 'currency' => ['nullable', 'in:CZK,EUR'], 'kind' => ['nullable', 'in:manual,promo'], 'note' => ['required', 'string', 'min:3', 'max:250']]);

        return $this->dispatch(new StaffCustomerCommand($this->onceKey($request, "wallet.credit:{$organization}"), ['op' => 'wallet.credit', 'organization_id' => $organization] + $data), $this->api->context($request, null, $data['note']), 201);
    }

    /** Price preview of an assisted order (audit §5y): the quote the order would use, nothing is placed. */
    public function quoteOrder(Request $request, QuoteService $quotes, string $organization): JsonResponse
    {
        $this->api->authorize($request, 'staff.order.manage', CommandScope::global());
        $org = Organization::query()->find($organization);
        if ($org === null) {
            throw DomainError::notFound('organization');
        }
        $data = $request->validate(['items' => ['required', 'array', 'min:1', 'max:20'], 'items.*.product_key' => ['required', 'string', 'max:40'], 'items.*.plan_key' => ['nullable', 'string', 'max:40'], 'items.*.config' => ['nullable', 'array'], 'items.*.period' => ['nullable', 'in:month,year'], 'commit_months' => ['nullable', 'integer', 'in:1,12,24']]);
        $quote = $quotes->quote($data['items'], $org->currency ?? 'CZK', ['country' => $org->country ?? 'CZ', 'customer_class' => $org->customer_class ?? 'b2c', 'vat_status' => $org->vat_status ?? 'unknown', 'ip_country' => null], (int) ($data['commit_months'] ?? 1), null, $org);

        return response()->json(['data' => ['quote_id' => $quote->id, 'lines' => $quote->lines, 'subtotal' => $quote->subtotal_minor, 'discount' => $quote->discount_minor, 'tax' => $quote->tax_minor, 'total' => $quote->total_minor, 'currency' => $quote->currency]]);
    }

    /** An order placed on the customer's behalf (audit §5y): the panel's items, quote and checkout; payment from credit, by proforma or postpaid. */
    public function placeOrder(Request $request, string $organization): JsonResponse
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1', 'max:20'], 'items.*.product_key' => ['required', 'string', 'max:40'], 'items.*.plan_key' => ['nullable', 'string', 'max:40'], 'items.*.qty' => ['nullable', 'integer', 'min:1', 'max:50'],
            'items.*.period' => ['nullable', 'in:month,year'], 'items.*.config' => ['nullable', 'array'], 'items.*.line_id' => ['nullable', 'string', 'max:20'],
            'payment' => ['required', 'in:wallet,bank,postpaid'], 'commit_months' => ['nullable', 'integer', 'in:1,12,24'], 'note' => ['required', 'string', 'min:3', 'max:250'],
        ]);

        return $this->dispatch(new StaffCustomerCommand($this->idempotencyKey($request, "order.assisted:{$organization}:".now()->format('YmdHis')), ['op' => 'order.assisted', 'organization_id' => $organization] + $data), $this->api->context($request, null, $data['note']), 201);
    }

    /**
     * Staff cancel an order: an unpaid one, or a paid one nothing of which runs (held by the review, failed). The console's
     * order buttons post here (`onhost-store.api.js`); the route did not exist, so every state button of the console failed.
     */
    public function cancelOrder(Request $request, string $order): JsonResponse
    {
        $data = OrderController::cancellation($request);
        $command = new StaffCancelOrderCommand($this->idempotencyKey($request, "order.cancel.staff:{$order}"), ['order_id' => $order, 'reason' => $data['reason'] ?? null]);
        $this->api->assertTokenScope($request, $command->permission());
        $result = (array) $this->bus->dispatch($command, $this->api->context($request, null, $data['reason'] ?? null));
        $model = Order::query()->findOrFail((string) $result['order_id']);

        return response()->json(['data' => Presenters::order($model) + ['organization_id' => $model->organization_id]]);
    }

    public function reviewOrder(Request $request, string $order): JsonResponse
    {
        $data = $request->validate(['decision' => ['required', 'in:release,reject'], 'reason' => ['nullable', 'string', 'max:250']]);

        return $this->dispatch(new ReviewOrderCommand($this->idempotencyKey($request, "order.review:{$order}"), ['order_id' => $order] + $data), $this->api->context($request, null, $data['reason'] ?? null));
    }

    public function services(Request $request): JsonResponse
    {
        $this->api->authorize($request, 'staff.service.manage', CommandScope::global());
        $query = Service::query();
        foreach (['state', 'family', 'product_key', 'provider_instance_id', 'node_id', 'organization_id'] as $filter) {
            if ($request->filled($filter)) {
                $query->where($filter, (string) $request->query($filter));
            }
        }

        return $this->api->paginate($request, $query, fn (Service $s) => Presenters::service($s) + ['organization_id' => $s->organization_id, 'provider_instance_id' => $s->provider_instance_id, 'node_id' => $s->node_id]);
    }

    /** All customer domains for the admin domains/DNS view (`OnhostDomains` API module in staff mode). */
    public function domains(Request $request): JsonResponse
    {
        $this->api->authorize($request, 'staff.service.manage', CommandScope::global());
        $query = Domain::query();
        foreach (['state', 'tld', 'organization_id', 'registrar_provider', 'dns_provider'] as $filter) {
            if ($request->filled($filter)) {
                $query->where($filter, (string) $request->query($filter));
            }
        }
        if ($request->filled('q')) {
            $query->where('fqdn_ascii', 'like', '%'.strtolower((string) $request->query('q')).'%');
        }
        $names = Organization::query()->whereIn('id', (clone $query)->limit(500)->pluck('organization_id'))->pluck('name', 'id');

        return $this->api->paginate($request, $query, fn (Domain $d) => Presenters::domain($d) + ['organization_id' => $d->organization_id, 'organization_name' => $names[$d->organization_id] ?? null], 'fqdn_ascii');
    }
}
