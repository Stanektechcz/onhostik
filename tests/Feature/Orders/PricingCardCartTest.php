<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;

/*
 * K7-1 (TASK-0162, found by the K7 browser check): the pricing page (`#/ceny`, the target of "Vybrat tarif" on `#/webhosting`)
 * draws its plan cards from the catalogue (`ONHOST_DATA.plans`, the web hosting plans), but its card button adds the line as
 * "VPS <plan>" (`pick('VPS ' + c[0], p.price)` in the byte-identical prototype). The cart resolves a line by its name, nothing knew
 * "VPS Standard", and the checkout refused: "Položky se nepodařilo spárovat s katalogem: VPS Standard." The main public path to order
 * web hosting never reached an order. The SKU index now knows the names the pricing cards use.
 */

/** @return array<string, mixed> the `cs`/`en` data of the generated `onhost-data.js` */
function pccData(object $test): array
{
    $js = (string) $test->get('/surfaces/onhost-data.js')->assertOk()->getContent();
    preg_match('/var D = (\{.*\});\n  function L/s', $js, $m);

    return (array) json_decode($m[1] ?? '{}', true);
}

it('resolves every pricing-page card, as the card names it, to the catalogue plan it shows', function () {
    $this->seed([CatalogSeeder::class]);
    $data = pccData($this);

    foreach (['cs', 'en'] as $locale) {
        $plans = $data[$locale]['plans'];
        expect($plans)->not->toBeEmpty();
        foreach ($plans as $card) {
            $name = mb_strtolower('VPS '.$card[$locale][0]); // what the card's button puts in the cart
            expect($data[$locale]['skus'])->toHaveKey($name.'|'.$card['price'])
                ->and($data[$locale]['skus'][$name.'|'.$card['price']])->toMatchArray(['product_key' => config('onhost.content.plans_product', 'web-hosting'), 'plan_key' => $card['key']])
                ->and($data[$locale]['skus'][$name])->toMatchArray(['plan_key' => $card['key']]);
        }
    }
});

it('quotes the line the pricing card put in the cart instead of refusing it', function () {
    $this->seed([CatalogSeeder::class, TaxRuleSeeder::class, LegalEntitySeeder::class]);
    $sku = pccData($this)['cs']['skus']['vps standard|189'];

    [$owner] = $this->customerWithOrganization();
    $this->actingAs($owner, 'sanctum');
    // the cart seam sends exactly this identity (onhost-cart.api.js: product_key + plan_key of the resolved line)
    $this->putJson('/v1/cart', ['items' => [['line_id' => 'l1', 'product_key' => $sku['product_key'], 'plan_key' => $sku['plan_key'], 'qty' => 1]]])->assertOk();
    $this->postJson('/v1/cart/quote')->assertOk()->assertJsonPath('data.lines.0.product_key', 'web-hosting')->assertJsonPath('data.lines.0.plan_key', 'standard');
});
