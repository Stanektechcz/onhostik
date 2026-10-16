<?php

declare(strict_types=1);

use App\Http\Support\PublicSeo;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\TaxRuleSeeder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
 * W4-4: Slovak is a request locale only behind config('onhost.locale_sk') (ONHOST_LOCALE_SK, default off). Off, an `sk`
 * visitor reads Czech and `?locale=sk` is ignored; on, lang/sk answers validation and anything lang/sk lacks falls back
 * to Czech. The locale never changes a price, and hreflang stays Czech only.
 */

function slskProbeRoute(): void
{
    Route::post('/slsk-probe', fn (Request $request) => $request->validate(['email' => ['required', 'string']]));
}

function slskRequiredMessage(string $locale): string
{
    return trans('validation.custom.email.required', [], $locale);
}

it('keeps Slovak visitors on Czech while the switch is off, also through ?locale=sk', function () {
    config()->set('onhost.locale_sk', false);
    slskProbeRoute();

    $this->withHeader('Accept-Language', 'sk')->postJson('/slsk-probe', [])
        ->assertStatus(422)->assertJsonPath('errors.email.0', slskRequiredMessage('cs'));
    $this->withHeader('Accept-Language', 'en')->postJson('/slsk-probe?locale=sk', [])
        ->assertStatus(422)->assertJsonPath('errors.email.0', trans('validation.required', ['attribute' => 'email'], 'en'));
});

it('answers Slovak from lang/sk when the switch is on and falls back to Czech for access.php', function () {
    config()->set('onhost.locale_sk', true);
    slskProbeRoute();

    expect(slskRequiredMessage('sk'))->not->toBe(slskRequiredMessage('cs'));
    $this->withHeader('Accept-Language', 'sk')->postJson('/slsk-probe', [])
        ->assertStatus(422)->assertJsonPath('errors.email.0', slskRequiredMessage('sk'));
    $this->withHeader('Accept-Language', 'en')->postJson('/slsk-probe?locale=sk', [])
        ->assertStatus(422)->assertJsonPath('errors.email.0', slskRequiredMessage('sk'));

    app()->setLocale('sk'); // the same path SetLocale takes: LocaleUpdated sets the cs fallback
    expect(__('access.families.web'))->toBe(trans('access.families.web', [], 'cs'));
});

it('quotes the same cart identically for ?locale=cs and ?locale=sk', function () {
    config()->set('onhost.locale_sk', true);
    $this->seed([CatalogSeeder::class, TaxRuleSeeder::class]);
    $token = (string) $this->putJson('/v1/cart', ['items' => [['line_id' => 'l1', 'product_key' => 'web-hosting', 'plan_key' => 'standard', 'qty' => 1, 'config' => []]], 'commit_months' => 12, 'currency' => 'CZK'])->assertOk()->json('data.token');

    $cs = $this->withHeader('X-Cart-Token', $token)->postJson('/v1/cart/quote?locale=cs')->assertOk()->json('data');
    $sk = $this->withHeader('X-Cart-Token', $token)->postJson('/v1/cart/quote?locale=sk')->assertOk()->json('data');
    foreach (['subtotal', 'tax', 'total', 'currency'] as $field) {
        expect($sk[$field])->toBe($cs[$field]);
    }
});

it('publishes hreflang for Czech only while the switch is off', function () {
    config()->set('onhost.locale_sk', false);
    expect(PublicSeo::languages())->toBe(['cs']);
});
