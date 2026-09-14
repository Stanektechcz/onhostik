<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Onhost\Domain\Catalog\CatalogService;
use Onhost\Domain\Catalog\Models\Product;
use Onhost\Domain\Catalog\Models\PromoCode;
use Onhost\Domain\Catalog\PricingRules;
use Onhost\Domain\Domains\DomainService;
use Onhost\Platform\Errors\DomainError;

/** Public catalogue, plan compare, TLD policies and domain availability (rate limited, cached briefly). */
final class CatalogController extends ApiController
{
    public function index(Request $request, CatalogService $catalog): JsonResponse
    {
        [$locale, $currency] = $this->localeCurrency($request);

        return response()->json(['data' => $catalog->publicCatalog($locale, $currency), 'locale' => $locale, 'currency' => $currency]);
    }

    public function show(Request $request, CatalogService $catalog, string $product): JsonResponse
    {
        [$locale, $currency] = $this->localeCurrency($request);
        $item = collect($catalog->publicCatalog($locale, $currency)['products'] ?? $catalog->publicCatalog($locale, $currency))->first(fn ($p) => ($p['key'] ?? null) === $product);
        if ($item === null) {
            $model = Product::query()->where('key', $product)->first();
            if ($model === null) {
                throw DomainError::notFound('product');
            }
            $item = ['key' => $model->key, 'family' => $model->family, 'name' => $model->localizedName($locale), 'description' => $model->description[$locale] ?? null, 'meta' => $model->meta, 'state' => $model->state];
        }

        return response()->json(['data' => $item]);
    }

    public function tlds(CatalogService $catalog, Request $request): JsonResponse
    {
        [, $currency] = $this->localeCurrency($request);
        $out = [];
        foreach ($catalog->tlds() as $policy) {
            try {
                $price = $catalog->domainPrice($policy->tld, $currency);
            } catch (DomainError) {
                continue;
            }
            $out[] = ['tld' => $policy->tld, 'registrable' => (bool) $policy->registrable, 'periods' => $policy->periods, 'default_period' => $policy->default_period, 'transfer_mode' => $policy->transfer_mode, 'nsset_required' => (bool) $policy->nsset_required, 'dnssec' => (bool) $policy->dnssec_supported, 'idn' => (bool) $policy->idn, 'register' => $price->register(), 'renew' => $price->renew(), 'transfer' => $price->transfer(), 'registry_terms_url' => $policy->registry_terms_url, 'registrar_terms_url' => $policy->registrar_terms_url];
        }

        return response()->json(['data' => $out, 'currency' => $currency]);
    }

    /** Promo code lookup for the cart (kind, value, families it applies to); usage is counted when an order is placed. */
    /** The price region of a country (audit §5j-8): the suggested currency and the percentage the cart applies; `?country=` defaults to CZ. */
    public function regions(Request $request, PricingRules $rules): JsonResponse
    {
        $country = strtoupper((string) $request->query('country', 'CZ'));
        if (! preg_match('/^[A-Z]{2}$/', $country)) {
            throw new DomainError('country_invalid', 'country is an ISO 3166-1 alpha-2 code.', 422, ['field' => 'country']);
        }

        return response()->json(['data' => ['country' => $country, 'region' => $rules->regionFor($country), 'regions' => array_values($rules->regions()), 'currencies' => ['CZK', 'EUR']]]);
    }

    public function promo(Request $request): JsonResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:40']]);
        $promo = PromoCode::query()->find(strtoupper(trim($data['code'])));
        if ($promo === null || ! $promo->isUsable()) {
            throw new DomainError('promo_invalid', 'Slevový kód neplatí.', 422, ['field' => 'promo']);
        }

        return response()->json(['data' => ['code' => $promo->code, 'kind' => $promo->kind, 'value' => (float) $promo->value, 'currency' => $promo->currency, 'applies_to' => $promo->applies_to ?? [], 'first_period_only' => (bool) $promo->first_period_only, 'valid_to' => $promo->valid_to?->toIso8601String()]]);
    }

    public function checkDomains(Request $request, DomainService $domains): JsonResponse
    {
        $data = $request->validate(['names' => ['required', 'array', 'min:1', 'max:20'], 'names.*' => ['string', 'max:253'], 'currency' => ['nullable', 'in:CZK,EUR']]);
        $organization = $request->user() ? $this->api->organization($request, false) : null;

        return response()->json(['data' => $domains->search($data['names'], $data['currency'] ?? ($organization?->currency ?? 'CZK'), $organization)]);
    }

    /** @return array{0:string,1:string} */
    private function localeCurrency(Request $request): array
    {
        $locale = (string) $request->query('locale', $request->getPreferredLanguage(config('onhost.locales', ['cs', 'en'])) ?: config('onhost.default_locale', 'cs'));
        $currency = strtoupper((string) $request->query('currency', config('onhost.billing.default_currency', 'CZK')));

        return [in_array($locale, config('onhost.locales', ['cs']), true) ? $locale : 'cs', in_array($currency, config('onhost.billing.currencies', ['CZK']), true) ? $currency : 'CZK'];
    }
}
