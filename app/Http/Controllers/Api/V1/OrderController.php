<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Presenters\Presenters;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Onhost\Domain\Orders\Commands\CancelOrderCommand;
use Onhost\Domain\Orders\Commands\DecideOrderApprovalCommand;
use Onhost\Domain\Orders\Commands\PlaceOrderCommand;
use Onhost\Domain\Orders\Models\Order;
use Onhost\Domain\Orders\OrderStateMachine;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Risk\Turnstile;
use Onhost\Platform\Commands\CommandScope;
use Onhost\Platform\Errors\DomainError;

final class OrderController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $organization = $this->api->organization($request);
        $this->api->authorize($request, 'organization.read', CommandScope::organization($organization->id));
        $query = Order::query()->where('organization_id', $organization->id);
        if ($request->filled('state')) {
            $query->where('state', strtoupper((string) $request->query('state')));
        }
        if ($request->query('approval') === 'pending') { // credit orders waiting for the owner or a billing admin (TASK-0021)
            $query->where('state', OrderStateMachine::NEW)->where('meta->approval->state', 'pending');
        }

        return $this->api->paginate($request, $query, fn (Order $o) => Presenters::order($o, false), 'placed_at');
    }

    public function show(Request $request, string $order): JsonResponse
    {
        return response()->json(['data' => Presenters::order($this->resolve($request, $order))]);
    }

    public function store(Request $request, Turnstile $turnstile): JsonResponse
    {
        $organization = $this->api->organization($request);
        $turnstile->check($request); // §5q-6
        $data = $request->validate([
            'quote_id' => ['required', 'string'], 'consents' => ['required', 'array'], 'payment' => ['required', 'array'], 'payment.mode' => ['required', 'in:wallet,gateway,bank,postpaid'],
            'payment.provider' => ['nullable', 'string', 'max:20'], 'payment.method' => ['nullable', 'string', 'max:40'], 'payment.return_urls' => ['nullable', 'array'], 'source' => ['nullable', 'in:web,panel,api,partner'],
        ]);

        return $this->dispatch(new PlaceOrderCommand($organization->id, $this->idempotencyKey($request, 'order.place'), $data), $this->api->context($request, $organization), 201);
    }

    /**
     * The customer cancels an order nobody paid yet — the only state a person sets on an order. `to` (or `state`, as the
     * panel store sends it) must be `cancelled`: "paid" comes from a payment and "active" from delivered lines, never from
     * a request. The route used to move an order to ANY state for whoever held `staff.order.manage`, outside the command bus.
     */
    public function transition(Request $request, string $order): JsonResponse
    {
        $model = $this->resolve($request, $order);
        $data = self::cancellation($request);
        $organization = Organization::query()->findOrFail($model->organization_id);
        $command = new CancelOrderCommand($organization->id, $this->idempotencyKey($request, "order.cancel:{$model->id}"), ['order_id' => $model->id, 'reason' => $data['reason'] ?? null]);
        $this->api->assertTokenScope($request, $command->permission()); // bearer tokens are limited to their documented scopes
        $this->bus->dispatch($command, $this->api->context($request, $organization, $data['reason'] ?? null));

        return response()->json(['data' => Presenters::order($model->refresh())]);
    }

    /**
     * The owner or a billing admin decides a credit order another member placed (owner decision 20, TASK-0021). The order is
     * found and the caller authorized for its organization before anything of the request is validated.
     */
    public function approval(Request $request, string $order): JsonResponse
    {
        $model = $this->resolve($request, $order);
        $data = $request->validate(['decision' => ['required', 'in:approve,reject'], 'reason' => ['nullable', 'string', 'max:250']]);
        $organization = Organization::query()->findOrFail($model->organization_id);

        return $this->dispatch(new DecideOrderApprovalCommand($organization->id, $this->idempotencyKey($request, "order.approval:{$model->id}"), ['order_id' => $model->id, 'decision' => $data['decision'], 'reason' => $data['reason'] ?? null]), $this->api->context($request, $organization, $data['reason'] ?? null));
    }

    /** @return array{to:string, reason?:?string} */
    public static function cancellation(Request $request): array
    {
        $data = $request->validate(['to' => ['required_without:state', 'nullable', 'string', 'max:20'], 'state' => ['required_without:to', 'nullable', 'string', 'max:20'], 'reason' => ['nullable', 'string', 'max:250']]);
        $to = strtoupper((string) ($data['to'] ?? $data['state']));
        if ($to !== OrderStateMachine::CANCELLED) {
            throw new DomainError('order_transition_not_offered', 'The state of an order follows its payment and its services; the only thing a person does to it is cancel it.', 422, ['field' => 'to', 'offered' => ['cancelled']]);
        }

        return ['to' => $to, 'reason' => $data['reason'] ?? null];
    }

    private function resolve(Request $request, string $id): Order
    {
        $order = Order::query()->find($id);
        if ($order === null) {
            throw DomainError::notFound('order');
        }
        $this->api->authorize($request, 'organization.read', CommandScope::organization($order->organization_id));

        return $order;
    }
}
