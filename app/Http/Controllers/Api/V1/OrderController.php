<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Presenters\Presenters;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Onhost\Domain\Orders\CheckoutService;
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

    /** Customer cancellation of an unpaid order; staff transitions are audited with a reason. */
    public function transition(Request $request, CheckoutService $checkout, string $order): JsonResponse
    {
        $model = $this->resolve($request, $order);
        $data = $request->validate(['to' => ['required', 'string'], 'reason' => ['nullable', 'string', 'max:250']]);
        $to = strtoupper($data['to']);
        $staff = $this->api->can($request, 'staff.order.manage', CommandScope::global());
        if (! $staff && ! ($to === OrderStateMachine::CANCELLED && in_array($model->state, [OrderStateMachine::NEW, OrderStateMachine::PENDING_PAYMENT], true))) {
            throw DomainError::forbidden('Customers can only cancel orders that are not paid yet.');
        }
        $context = $this->api->context($request, Organization::query()->find($model->organization_id), $data['reason'] ?? null);
        $updated = $checkout->transition($model, $to, $context, $data['reason'] ?? null);

        return response()->json(['data' => Presenters::order($updated)]);
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
