<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Database\Seeders\TaxRuleSeeder;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Exceptions;
use Onhost\Platform\Events\GenericEvent;
use Onhost\Platform\Outbox\OutboxMessage;
use Onhost\Platform\Outbox\OutboxPublisher;

/*
 * W4-6 (docs/compliance/dpia-funnel-telemetry.md): a successful cart quote publishes an anonymous `cart.quote.requested`
 * (plan keys, period, locale — nothing else) while ONHOST_FUNNEL_METRICS is on; the relay counts it into funnel_daily,
 * which has no identifier and no route.
 */

beforeEach(function () {
    $this->seed([CatalogSeeder::class, TaxRuleSeeder::class]);
    config(['onhost.funnel_metrics' => true]);
});

/** A guest cart with the given plans of web hosting, quoted once; returns the cart token. */
function cqmQuote($test, array $plans, int $commitMonths = 1, ?string $token = null): string
{
    $lines = array_map(fn (string $plan) => ['product_key' => 'web-hosting', 'plan_key' => $plan, 'qty' => 1, 'config' => []], $plans);
    $headers = $token !== null ? ['X-Cart-Token' => $token] : [];
    $token = (string) $test->withHeaders($headers)->putJson('/v1/cart', ['items' => $lines, 'commit_months' => $commitMonths, 'currency' => 'CZK'])->assertOk()->json('data.token');
    $test->withHeader('X-Cart-Token', $token)->postJson('/v1/cart/quote?locale=en')->assertOk();

    return $token;
}

function cqmMessages()
{
    return OutboxMessage::query()->where('name', 'cart.quote.requested')->get();
}

function cqmCount(string $plan, string $period = 'month', string $locale = 'en'): int
{
    return (int) DB::table('funnel_daily')->where(['plan_key' => $plan, 'period' => $period, 'locale' => $locale])->sum('count');
}

it('publishes exactly plan_keys, period and locale with no organization', function () {
    cqmQuote($this, ['start', 'standard', 'start'], 12);

    $messages = cqmMessages();
    expect($messages)->toHaveCount(1);
    $message = $messages->first();
    expect(array_keys((array) $message->payload))->toEqualCanonicalizing(['plan_keys', 'period', 'locale'])
        ->and($message->payload['plan_keys'])->toBe(['standard', 'start'])
        ->and($message->payload['period'])->toBe('year')
        ->and($message->payload['locale'])->toBe('en')
        ->and($message->organization_id)->toBeNull()
        ->and($message->aggregate_type)->toBe('metric')
        ->and($message->aggregate_id)->toBe(now()->toDateString());
});

it('counts each quote into the daily aggregate at relay', function () {
    $token = cqmQuote($this, ['start']);
    app(OutboxPublisher::class)->relayPending();
    expect(cqmCount('start'))->toBe(1);

    cqmQuote($this, ['start'], 1, $token);
    app(OutboxPublisher::class)->relayPending();
    expect(cqmCount('start'))->toBe(2)->and(DB::table('funnel_daily')->count())->toBe(1);
});

it('keeps an unknown plan key or locale out of the aggregate', function () {
    $outbox = app(OutboxPublisher::class);
    $outbox->publish(GenericEvent::of('cart.quote.requested', 'metric', now()->toDateString(), ['plan_keys' => ['no-such-plan', 'start'], 'period' => 'month', 'locale' => 'en']));
    $outbox->publish(GenericEvent::of('cart.quote.requested', 'metric', now()->toDateString(), ['plan_keys' => ['start'], 'period' => 'month', 'locale' => 'de']));
    $outbox->publish(GenericEvent::of('cart.quote.requested', 'metric', now()->toDateString(), ['plan_keys' => ['start'], 'period' => 'week', 'locale' => 'en']));
    $outbox->relayPending();

    expect(DB::table('funnel_daily')->pluck('plan_key')->all())->toBe(['start'])
        ->and(DB::table('funnel_daily')->sum('count'))->toEqual(1)
        ->and(DB::table('funnel_daily')->where('locale', 'de')->exists())->toBeFalse();
});

it('publishes nothing while the switch is off', function () {
    config(['onhost.funnel_metrics' => false]);
    cqmQuote($this, ['start']);
    app(OutboxPublisher::class)->relayPending();

    expect(cqmMessages())->toHaveCount(0)->and(DB::table('funnel_daily')->count())->toBe(0);
});

it('has no route that reads the aggregate', function () {
    foreach (glob(base_path('routes/*.php')) ?: [] as $file) {
        $source = (string) file_get_contents($file);
        expect(str_contains($source, 'funnel_daily'))->toBeFalse("{$file} names funnel_daily")
            ->and(str_contains($source, 'CountQuoteRequest'))->toBeFalse("{$file} names CountQuoteRequest")
            ->and(stripos($source, 'funnel'))->toBeFalse("{$file} names the funnel aggregate");
    }
});

/**
 * W5-1: the amounts of a guest quote of the given plans, with the metric switch as given.
 *
 * @param  list<string>  $plans
 * @return array{subtotal: mixed, tax: mixed, total: mixed, currency: mixed}
 */
function cqmW51Amounts($test, array $plans, bool $metrics): array
{
    config(['onhost.funnel_metrics' => $metrics]);
    $lines = array_map(fn (string $plan) => ['product_key' => 'web-hosting', 'plan_key' => $plan, 'qty' => 1, 'config' => []], $plans);
    $token = (string) $test->putJson('/v1/cart', ['items' => $lines, 'commit_months' => 1, 'currency' => 'CZK'])->assertOk()->json('data.token');
    $data = $test->withHeader('X-Cart-Token', $token)->postJson('/v1/cart/quote?locale=en')->assertOk()->json('data');

    return ['subtotal' => $data['subtotal'], 'tax' => $data['tax'], 'total' => $data['total'], 'currency' => $data['currency']];
}

it('answers the quote with the same amounts when publishing the metric fails (W5-1)', function () {
    $expected = cqmW51Amounts($this, ['standard'], false);

    // OutboxPublisher is final: a real instance is bound and its publish throws for the metric event only
    app()->instance(OutboxPublisher::class, app()->make(OutboxPublisher::class));
    OutboxMessage::creating(function (OutboxMessage $message) {
        if ($message->name === 'cart.quote.requested') {
            throw new RuntimeException('outbox unavailable');
        }
    });
    Exceptions::fake();
    $logged = [];
    Event::listen(MessageLogged::class, function (MessageLogged $event) use (&$logged) {
        $logged[] = $event->message.' '.json_encode($event->context);
    });

    expect(cqmW51Amounts($this, ['standard'], true))->toBe($expected);

    Exceptions::assertReported(RuntimeException::class);
    expect(cqmMessages())->toHaveCount(0);
    foreach ($logged as $line) {
        expect(str_contains($line, 'standard'))->toBeFalse('the log names the plan key')
            ->and(str_contains($line, 'plan_keys'))->toBeFalse('the log names plan_keys');
    }
});
