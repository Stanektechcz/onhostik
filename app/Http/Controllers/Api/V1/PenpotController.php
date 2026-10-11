<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Onhost\Domain\Billing\Models\Subscription;
use Onhost\Domain\Catalog\CatalogService;
use Onhost\Domain\Catalog\Models\Plan;
use Onhost\Domain\Catalog\Models\PlanVersion;
use Onhost\Domain\Catalog\Models\Product;
use Onhost\Domain\Catalog\PenpotOffer;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Penpot\PenpotAccessCommand;
use Onhost\Domain\Services\Penpot\PenpotInstances;
use Onhost\Domain\Services\Penpot\PenpotParents;
use Onhost\Platform\Commands\CommandScope;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Money\Currency;
use Onhost\Platform\Money\Money;

/**
 * Penpot for web hosting (TASK-0123): the customer panel card and the owner's password. Suspend, resume, backup, cancel and
 * restore are the ordinary service actions (`POST /v1/services/{service}/actions`).
 */
final class PenpotController extends ApiController
{
    /** The panel card: address to open, login e-mail, limits, the last backups. Reading the service is enough. */
    public function show(Request $request, string $service): JsonResponse
    {
        return $this->ok(PenpotInstances::summary($this->resolve($request, $service, 'service.read')));
    }

    /** The owner sets the password of their Penpot account (HIGH, fresh step-up); the node gets it, nobody stores it. */
    public function ownerPassword(Request $request, string $service): JsonResponse
    {
        $model = $this->resolve($request, $service, 'service.read');
        $data = $request->validate(['password' => ['required', 'string', 'min:12', 'max:128']]);
        $context = $this->api->context($request, Organization::query()->find($model->organization_id));

        return $this->dispatch(new PenpotAccessCommand($model->organization_id, $this->idempotencyKey($request, 'penpot.owner:'.$model->id), [
            'service_id' => $model->id, 'project_id' => $model->project_id, 'op' => 'owner.password', 'password' => (string) $data['password'],
        ]), $context, 202);
    }

    /**
     * What a Penpot costs next to this service (owner decision H-R7) and whether it can be ordered now: included in a web hosting
     * tariff, the tariff's price, or the add-on price — or why not (another Penpot, an ended service, no node). The order itself
     * is an ordinary cart line `{product_key: penpot, plan_key: penpot-team, config: {parent_service_id}}`.
     */
    public function offer(Request $request, string $service): JsonResponse
    {
        $model = Service::query()->find($service);
        if ($model === null) {
            throw DomainError::notFound('service');
        }
        $this->api->authorizeOrNotFound($request, 'service.read', CommandScope::resource($model->id, $model->organization_id, $model->project_id), 'service');
        $subscription = Subscription::query()->where('service_id', $model->id)->where('state', Subscription::ACTIVE)->first(['currency', 'period']);
        $currency = Currency::fromString((string) ($subscription->currency ?? Organization::query()->whereKey($model->organization_id)->value('currency') ?? 'CZK'));
        $period = in_array($subscription?->period, ['month', 'year'], true) ? (string) $subscription->period : 'month';
        $out = ['service_id' => $model->id, 'product_key' => PenpotOffer::PRODUCT, 'plan_key' => PenpotOffer::PLAN, 'currency' => $currency->value, 'period' => $period, 'existing' => PenpotParents::of($model)->pluck('id')->values()->all()];
        try {
            PenpotParents::assertParent($model);
            if (PenpotParents::taken($model)) {
                throw new DomainError('penpot_exists', 'Tato služba už Penpot má (nebo je objednaný).', 409);
            }
            $resolved = app(CatalogService::class)->resolve(PenpotOffer::PRODUCT, PenpotOffer::PLAN, $currency, $period);
            $parent = Product::query()->where('key', (string) $model->product_key)->first() ?? throw DomainError::notFound('product');
            $price = app(PenpotOffer::class)->priceFor($parent, (string) Plan::query()->whereKey(PlanVersion::query()->whereKey((string) $model->plan_version_id)->value('plan_id'))->value('key'), $currency, $period, $resolved['price']);
            $deliverable = PenpotParents::deliverable($model->organization_id, (array) $resolved['version']->entitlements);
            $out += ['orderable' => $deliverable, 'reason' => $deliverable ? null : 'penpot_unavailable', 'included' => $price['included'], 'source' => $price['source'],
                'price' => Money::minor($price['first_minor'], $currency)->jsonSerialize(), 'renewal' => Money::minor($price['renewal_minor'], $currency)->jsonSerialize()];
        } catch (DomainError $e) {
            $out += ['orderable' => false, 'reason' => $e->error, 'message' => $e->getMessage()];
        }

        return $this->ok($out);
    }

    private function resolve(Request $request, string $id, string $permission): Service
    {
        $service = Service::query()->find($id);
        if ($service === null) {
            throw DomainError::notFound('service');
        }
        // a stranger gets 404 (TASK-0098); a project role covers the services of its project
        $this->api->authorizeOrNotFound($request, $permission, CommandScope::resource($service->id, $service->organization_id, $service->project_id), 'service');
        if (! PenpotInstances::isPenpot($service)) {
            throw new DomainError('feature_unavailable', 'This service is not a Penpot instance.', 404);
        }

        return $service;
    }
}
