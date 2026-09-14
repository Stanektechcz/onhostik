<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Onhost\Domain\Payments\Models\PaymentIntent;
use Onhost\Domain\Payments\PaymentService;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Commands\CommandScope;
use Onhost\Platform\Errors\DomainError;

/**
 * Gateway callbacks and return pages (blueprint §24). A callback never changes state on
 * its own word: the provider status is re-read, deduplicated by (provider, event id).
 */
final class PaymentController extends ApiController
{
    public function webhook(Request $request, PaymentService $payments, string $provider): JsonResponse
    {
        $result = $payments->handleWebhook($provider, $request);

        return response()->json(['result' => $result['result'], 'state' => $result['intent']?->state]);
    }

    /** The organization's payments, newest first: pending bank transfers carry their instructions until the money arrives. */
    public function index(Request $request): JsonResponse
    {
        $organization = $this->api->organization($request);
        $this->api->authorize($request, 'billing.wallet.read', CommandScope::organization($organization->id));
        $rows = PaymentIntent::query()->where('organization_id', $organization->id)->orderByDesc('created_at')->limit((int) min(200, max(1, (int) $request->query('limit', 50))))->get()
            ->map(fn (PaymentIntent $i) => $this->present($i) + ['instructions' => $i->provider === 'bank' ? ($i->raw['instructions'] ?? null) : null, 'created_at' => $i->created_at?->toIso8601String(), 'paid_at' => $i->paid_at?->toIso8601String()])->values()->all();

        return response()->json(['data' => $rows]);
    }

    public function show(Request $request, string $intent): JsonResponse
    {
        $model = $this->resolve($request, $intent);

        return response()->json(['data' => $this->present($model)]);
    }

    /** Return URL target: the browser comes back, the server asks the gateway for the truth. */
    public function sync(Request $request, PaymentService $payments, string $intent): JsonResponse
    {
        $model = $this->resolve($request, $intent);
        $state = $payments->syncFromProvider($model, CommandContext::system('payment return'));

        return response()->json(['data' => $this->present($model->refresh()) + ['result' => $state]]);
    }

    private function resolve(Request $request, string $id): PaymentIntent
    {
        $model = PaymentIntent::query()->find($id);
        if ($model === null) {
            throw DomainError::notFound('payment');
        }
        $this->api->authorize($request, 'billing.wallet.read', CommandScope::organization($model->organization_id));

        return $model;
    }

    private function present(PaymentIntent $intent): array
    {
        return ['id' => $intent->id, 'provider' => $intent->provider, 'state' => $intent->state, 'amount' => $intent->amount(), 'purpose' => $intent->purpose, 'reference' => [$intent->reference_type, $intent->reference_id], 'redirect_url' => $intent->redirect_url, 'created_at' => $intent->created_at?->toIso8601String(), 'paid_at' => $intent->paid_at?->toIso8601String(), 'failure_reason' => $intent->failure_reason];
    }
}
