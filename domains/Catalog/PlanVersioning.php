<?php

declare(strict_types=1);

namespace Onhost\Domain\Catalog;

use Illuminate\Support\Facades\DB;
use Onhost\Domain\Billing\Models\Subscription;
use Onhost\Domain\Catalog\Models\Plan;
use Onhost\Domain\Catalog\Models\PlanVersion;
use Onhost\Domain\Catalog\Models\Price;
use Onhost\Domain\Catalog\Models\Product;
use Onhost\Domain\Services\Models\Service;
use Onhost\Platform\Audit\AuditRecorder;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Events\GenericEvent;
use Onhost\Platform\Money\Money;
use Onhost\Platform\Outbox\OutboxPublisher;

/**
 * Versions of a plan (Brain card H01). A plan version is a snapshot: quotes, orders, services and subscriptions point
 * at the version they were sold with, so a plan is never edited — a change of limits or prices is a NEW version that
 * new orders get, and everybody who already bought keeps theirs. Publishing copies the current version and applies the
 * change; what is not mentioned stays as it was, so a billing period can neither appear nor vanish by omission.
 * Rolling back is pointing the plan at an earlier version; customers of the version in between keep it.
 *
 * The prices of an old version stay active on purpose: renewals and hourly rating of existing services read them.
 */
final class PlanVersioning
{
    /** A price moving by more than this share has to be confirmed: a slipped decimal place is the usual way to a wrong price list. */
    public const LARGE_CHANGE_PCT = 50;

    public function __construct(private readonly AuditRecorder $audit, private readonly OutboxPublisher $outbox) {}

    /**
     * @param  array<string,mixed>  $in  entitlements?{key:value}, limits?{key:value}, features?{cs:[],en:[]},
     *                                   prices?[{currency, period, amount, renewal_amount?, setup?, monthly_cap?}], reason, confirm_large_change?,
     *                                   keep_promos? (a promo price of an unchanged price row stays — `CatalogRevisions` publishes with it)
     */
    public function publish(string $productKey, string $planKey, array $in, CommandContext $context): PlanVersion
    {
        $plan = $this->plan($productKey, $planKey);
        $reason = $this->reason($in);

        return DB::transaction(function () use ($plan, $productKey, $in, $reason, $context) {
            $plan = Plan::query()->lockForUpdate()->findOrFail($plan->id);
            self::assertBase($plan, $in);
            ['current' => $current, 'entitlements' => $entitlements, 'limits' => $limits, 'features' => $features, 'prices' => $prices, 'changed' => $changed] = $this->next($plan, $in);

            $number = (int) PlanVersion::query()->where('plan_id', $plan->id)->max('version') + 1; // never reuse a number, even after a rollback
            $version = PlanVersion::query()->create(['plan_id' => $plan->id, 'version' => $number, 'entitlements' => $entitlements, 'limits' => $limits, 'features' => $features, 'effective_from' => now(), 'created_by' => $context->actorId]);
            foreach ($prices as $p) {
                Price::query()->create(['plan_version_id' => $version->id, 'effective_from' => now(), 'state' => 'active'] + array_diff_key($p, ['changed' => 1]));
            }
            $current->forceFill(['effective_to' => now()])->save(); // informational: its prices stay active for those who bought it
            $plan->forceFill(['current_version' => $number])->save();

            $detail = ['product' => $productKey, 'plan' => $plan->key, 'version' => $number, 'previous_version' => $current->version, 'changed' => $changed, 'reason' => $reason];
            $this->audit->record($context, 'catalog.plan.publish', 'succeeded', $detail, 'plan', $plan->id);
            $this->outbox->publish(GenericEvent::of('catalog.plan.version_published', 'plan', $plan->id, $detail));

            return $version;
        }, 3);
    }

    /**
     * What `publish` would refuse, checked without writing anything: a broken change is refused before a second person is
     * asked to approve it (domains/Catalog/CatalogPreflight.php). The handler runs `publish`, which checks it all again.
     *
     * @param  array<string,mixed>  $in
     * @return array<string,mixed> what would change
     */
    public function check(string $productKey, string $planKey, array $in): array
    {
        $plan = $this->plan($productKey, $planKey);
        $this->reason($in);

        return $this->next($plan, $in)['changed'];
    }

    /** What `activate` would refuse, checked without writing anything. */
    public function checkActivate(string $productKey, string $planKey, int $number): PlanVersion
    {
        $plan = $this->plan($productKey, $planKey);
        $version = PlanVersion::query()->where('plan_id', $plan->id)->where('version', $number)->first() ?? throw DomainError::notFound("Version {$number} of {$productKey}/{$planKey}");
        if ((int) $plan->current_version === $number) {
            throw new DomainError('plan_version_unchanged', "Version {$number} is already the one on sale.", 422);
        }
        if (! $version->prices()->where('state', 'active')->exists()) {
            throw new DomainError('price_unavailable', "Version {$number} has no active price; it cannot go on sale.", 409);
        }

        return $version;
    }

    /** Point the plan at a version that already exists (a rollback, or forward again). Nobody's agreed version changes. */
    public function activate(string $productKey, string $planKey, int $number, array $in, CommandContext $context): Plan
    {
        $plan = $this->plan($productKey, $planKey);
        $reason = $this->reason($in);
        self::assertBase($plan, $in);
        $version = $this->checkActivate($productKey, $planKey, $number);
        $previous = (int) $plan->current_version;
        DB::transaction(function () use ($plan, $version, $number, $in): void {
            self::assertBase(Plan::query()->lockForUpdate()->findOrFail($plan->id), $in); // two approved rollbacks at once: the second finds the plan moved
            PlanVersion::query()->where('plan_id', $plan->id)->where('version', $plan->current_version)->update(['effective_to' => now()]);
            $version->forceFill(['effective_to' => null])->save();
            $plan->forceFill(['current_version' => $number])->save();
        }, 3);
        $detail = ['product' => $productKey, 'plan' => $plan->key, 'version' => $number, 'previous_version' => $previous, 'reason' => $reason];
        $this->audit->record($context, 'catalog.plan.activate_version', 'succeeded', $detail, 'plan', $plan->id);
        $this->outbox->publish(GenericEvent::of('catalog.plan.version_activated', 'plan', $plan->id, $detail));

        return $plan->refresh();
    }

    /** Every version of a plan, what it costs, and who is on it — the impact of a change before it is made. @return array<string,mixed> */
    public function history(string $productKey, string $planKey): array
    {
        $plan = $this->plan($productKey, $planKey);
        $versions = PlanVersion::query()->where('plan_id', $plan->id)->with('prices')->orderByDesc('version')->get();

        return [
            'product' => $productKey, 'plan' => $plan->key, 'name' => $plan->localizedName('cs'), 'current_version' => (int) $plan->current_version,
            'versions' => $versions->map(fn (PlanVersion $v) => [
                'version' => $v->version, 'on_sale' => $v->version === (int) $plan->current_version, 'entitlements' => $v->entitlements, 'limits' => $v->limits, 'features' => $v->features,
                'effective_from' => $v->effective_from?->toIso8601String(), 'effective_to' => $v->effective_to?->toIso8601String(), 'created_by' => $v->created_by,
                'prices' => $v->prices->sortBy(fn (Price $p) => $p->currency.$p->period)->map(fn (Price $p) => ['currency' => $p->currency, 'period' => $p->period, 'amount_minor' => $p->amount_minor, 'renewal_amount_minor' => $p->renewal_amount_minor, 'setup_minor' => $p->setup_minor, 'monthly_cap_minor' => $p->monthly_cap_minor, 'state' => $p->state, 'formatted' => Money::minor($p->amount_minor, $p->currency)->format()])->values()->all(),
                // who keeps this version whatever is published next
                'services' => Service::query()->where('plan_version_id', $v->id)->count(), 'subscriptions' => Subscription::query()->where('plan_version_id', $v->id)->whereIn('state', ['active', 'past_due'])->count(),
            ])->all(),
        ];
    }

    /**
     * The new version's content: the current one with the change applied, and what differs — or the refusal.
     *
     * @param  array<string,mixed>  $in
     * @return array{current: PlanVersion, entitlements: array<string,mixed>, limits: array<string,mixed>, features: mixed, prices: list<array<string,mixed>>, changed: array<string,mixed>}
     */
    private function next(Plan $plan, array $in): array
    {
        $current = $plan->currentVersion() ?? throw new DomainError('plan_version_missing', "Plan {$plan->key} has no current version.", 500);
        $entitlements = $this->merged((array) $current->entitlements, (array) ($in['entitlements'] ?? []), 'entitlements');
        $limits = $this->merged((array) ($current->limits ?? []), (array) ($in['limits'] ?? []), 'limits');
        $features = array_key_exists('features', $in) && $in['features'] !== null ? $this->features((array) $in['features']) : $current->features;
        $currentPrices = $current->prices()->where('state', 'active')->get();
        $prices = $this->prices($currentPrices->all(), (array) ($in['prices'] ?? []), (bool) ($in['confirm_large_change'] ?? false), (bool) ($in['keep_promos'] ?? false));

        $changed = [
            'entitlements' => self::diff((array) $current->entitlements, $entitlements),
            'limits' => self::diff((array) ($current->limits ?? []), $limits),
            'features' => $features !== $current->features,
            'prices' => array_values(array_map(fn (array $p) => $p['currency'].'/'.$p['period'], array_filter($prices, fn (array $p) => $p['changed']))),
        ];
        if ($changed['entitlements'] === [] && $changed['limits'] === [] && ! $changed['features'] && $changed['prices'] === []) {
            throw new DomainError('plan_version_unchanged', 'Nothing differs from the current version; a version without a change is not published.', 422);
        }

        return ['current' => $current, 'entitlements' => $entitlements, 'limits' => $limits, 'features' => $features, 'prices' => $prices, 'changed' => $changed];
    }

    /**
     * A change approved against one version is not applied to another (owner decision 13): the staff console binds the request
     * to the version on sale when it was asked (`base_version`, part of the approved payload), and somebody else's version
     * published in between turns the approved change into a conflict instead of a silent overwrite.
     *
     * @param  array<string,mixed>  $in
     */
    private static function assertBase(Plan $plan, array $in): void
    {
        if (! array_key_exists('base_version', $in) || $in['base_version'] === null) {
            return;
        }
        if ((int) $in['base_version'] !== (int) $plan->current_version) {
            throw new DomainError('catalog_changed_since_request', "The plan changed since the request was made (version {$in['base_version']} then, {$plan->current_version} now); ask again against the current version.", 409, ['base_version' => (int) $in['base_version'], 'current_version' => (int) $plan->current_version]);
        }
    }

    private function plan(string $productKey, string $planKey): Plan
    {
        $product = Product::query()->where('key', $productKey)->first() ?? throw DomainError::notFound("Product {$productKey}");

        return Plan::query()->where('product_id', $product->id)->where('key', $planKey)->first() ?? throw DomainError::notFound("Plan {$productKey}/{$planKey}");
    }

    /**
     * Keys whose value changed, plus the ones the new version no longer carries — a removal is a change too.
     *
     * @param  array<string,mixed>  $was
     * @param  array<string,mixed>  $now
     * @return list<string>
     */
    private static function diff(array $was, array $now): array
    {
        $changed = array_keys(array_filter($now, fn ($v, $k) => ($was[$k] ?? null) !== $v, ARRAY_FILTER_USE_BOTH));

        return array_values(array_unique(array_merge($changed, array_keys(array_diff_key($was, $now)))));
    }

    /** @param array<string,mixed> $in */
    private function reason(array $in): string
    {
        $reason = trim((string) ($in['reason'] ?? ''));
        if (mb_strlen($reason) < 5) {
            throw new DomainError('reason_required', 'Say why the plan changes: the reason is kept with the version.', 422, ['field' => 'reason']);
        }

        return mb_substr($reason, 0, 250);
    }

    /**
     * The schema of a plan belongs to the code that provisions it: a version may change values, not invent or retype keys.
     *
     * @param  array<string,mixed>  $current
     * @param  array<string,mixed>  $changes
     * @return array<string,mixed>
     */
    private function merged(array $current, array $changes, string $field): array
    {
        foreach ($changes as $key => $value) {
            if (! array_key_exists($key, $current)) {
                throw new DomainError('plan_key_unknown', "{$field}.{$key} is not part of this plan; new keys come with the code that reads them.", 422, ['field' => "{$field}.{$key}"]);
            }
            if ($value === null) { // the schema could only grow: a number nothing applies any more had no way off the plan (audit §5ad)
                unset($current[$key]);

                continue;
            }
            $was = $current[$key];
            $sameKind = match (true) {
                is_bool($was) => is_bool($value),
                is_int($was) => (is_int($value) || (is_float($value) && floor($value) === $value)) && $value >= 0, // a whole number stays whole: adapters cast it
                is_float($was) => (is_int($value) || is_float($value)) && $value >= 0,
                is_string($was) => is_string($value) && $value !== '' && mb_strlen($value) <= 120, default => gettype($was) === gettype($value),
            };
            if (! $sameKind) {
                throw new DomainError('plan_value_invalid', "{$field}.{$key} must stay ".(is_bool($was) ? 'a boolean' : (is_string($was) ? 'a short text' : 'a non-negative number')).'.', 422, ['field' => "{$field}.{$key}"]);
            }
            $current[$key] = is_int($was) && is_float($value) && floor($value) === $value ? (int) $value : $value;
        }

        return $current;
    }

    /** @param array<string,mixed> $in @return array{cs:list<string>, en:list<string>} */
    private function features(array $in): array
    {
        $out = ['cs' => [], 'en' => []];
        foreach (['cs', 'en'] as $locale) {
            foreach ((array) ($in[$locale] ?? []) as $line) {
                $line = trim((string) $line);
                if ($line !== '') {
                    $out[$locale][] = mb_substr($line, 0, 160);
                }
            }
        }
        if ($out['cs'] === []) {
            throw new DomainError('plan_features_invalid', 'Features need at least the Czech lines.', 422, ['field' => 'features.cs']);
        }
        $out['en'] = $out['en'] === [] ? $out['cs'] : $out['en'];

        return $out;
    }

    /**
     * The new version's price rows: every (currency, period) the current version sells, with the given ones replaced.
     *
     * @param  list<Price>  $current
     * @param  array<int, array<string,mixed>>  $changes
     * @return list<array<string,mixed>>
     */
    private function prices(array $current, array $changes, bool $confirmedLarge, bool $keepPromos = false): array
    {
        $rows = [];
        foreach ($current as $price) {
            // a promo price is a campaign of its version and is not carried over — except by a revision that changes no price
            // (`keep_promos`): taking it off there would raise the price for new orders without anybody deciding it
            $rows[$price->currency.'/'.$price->period] = [
                'currency' => $price->currency, 'period' => $price->period, 'amount_minor' => $price->amount_minor, 'renewal_amount_minor' => $price->renewal_amount_minor ?? $price->amount_minor, 'setup_minor' => $price->setup_minor,
                'promo_amount_minor' => $keepPromos ? $price->promo_amount_minor : null, 'promo_periods' => $keepPromos ? $price->promo_periods : null, 'monthly_cap_minor' => $price->monthly_cap_minor, 'included' => $price->included, 'changed' => false,
            ];
        }
        foreach ($changes as $i => $change) {
            $key = strtoupper((string) ($change['currency'] ?? '')).'/'.strtolower((string) ($change['period'] ?? ''));
            if (! isset($rows[$key])) {
                throw new DomainError('price_period_unknown', "The plan is not sold as {$key}; a version changes amounts, not the currencies and periods on offer.", 422, ['field' => "prices.{$i}"]);
            }
            $currency = $rows[$key]['currency'];
            $minor = function (string $field) use ($change, $currency, $i): ?int {
                if (! array_key_exists($field, $change) || $change[$field] === null || $change[$field] === '') {
                    return null;
                }
                if (! is_numeric($change[$field]) || (float) $change[$field] < 0) {
                    throw new DomainError('price_invalid', "{$field} must be a non-negative amount.", 422, ['field' => "prices.{$i}.{$field}"]);
                }

                return Money::decimal((string) $change[$field], $currency)->minor;
            };
            $amount = $minor('amount') ?? throw new DomainError('price_invalid', 'amount is required for a changed price.', 422, ['field' => "prices.{$i}.amount"]);
            $was = (int) $rows[$key]['amount_minor'];
            if ($was > 0 && $amount === 0) {
                throw new DomainError('price_invalid', 'A paid plan does not become free by a new version; take it off sale or use a promo code.', 422, ['field' => "prices.{$i}.amount"]);
            }
            if (! $confirmedLarge && $was > 0 && abs($amount - $was) * 100 > self::LARGE_CHANGE_PCT * $was) {
                throw new DomainError('price_change_large', "{$key} would move from ".Money::minor($was, $currency)->format().' to '.Money::minor($amount, $currency)->format().'; confirm a change of more than '.self::LARGE_CHANGE_PCT.' %.', 422, ['field' => "prices.{$i}.amount", 'confirm' => 'confirm_large_change']);
            }
            $renewal = $minor('renewal_amount') ?? $amount; // unless said otherwise the renewal follows the new amount
            $patch = ['amount_minor' => $amount, 'renewal_amount_minor' => $renewal, 'setup_minor' => $minor('setup') ?? $rows[$key]['setup_minor'], 'monthly_cap_minor' => $minor('monthly_cap') ?? $rows[$key]['monthly_cap_minor']];
            $changed = array_intersect_key($rows[$key], $patch) != $patch;
            $rows[$key] = array_replace($rows[$key], $patch, ['changed' => $changed], $changed ? ['promo_amount_minor' => null, 'promo_periods' => null] : []); // a promo of an old amount is not a promo of the new one
        }

        return array_values($rows);
    }
}
