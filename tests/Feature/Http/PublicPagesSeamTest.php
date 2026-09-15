<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;

/** Public product pages and the web hosting landing sell the catalogue (seam #23): plans, specs, comparison and SKUs come from the data layer. */
it('generates catalogue-driven plans, comparison tables and SKUs for the public product pages', function () {
    $this->seed([CatalogSeeder::class]);
    $js = $this->get('/surfaces/onhost-data.js')->assertOk()->getContent();
    preg_match('/var D = (\{.*\});\n  function L/s', $js, $m);
    $data = json_decode($m[1] ?? '{}', true);
    expect($data)->toHaveKeys(['cs', 'en']);
    $pages = $data['cs']['pages'];
    expect(array_keys($pages))->toContain('web-hosting', 'wordpress', 'eshop', 'mail', 'database', 'backup', 'vps', 'dedicated', 'ssl', 'cdn', 'devhosting', 'domains');

    $web = $pages['web-hosting'];
    expect(array_column($web['plans'], 'name'))->toBe(['Start', 'Standard', 'Profi'])
        ->and($web['plans'][0])->toMatchArray(['price' => 89, 'sku' => ['product_key' => 'web-hosting', 'plan_key' => 'start', 'price_year' => 890.0]])->and($web['plans'][0]['specs'])->toBe(['1 web', '10 GB NVMe', '5 schránek', '1 databáze', 'Zálohy 7 dní', 'Certifikát zdarma'])
        ->and($web['plans'][1]['tag'])->toBe('Nejoblíbenější')->and($web['cmp']['cols'])->toBe(['Start', 'Standard', 'Profi']);
    $rows = collect($web['cmp']['rows'])->keyBy(0);
    expect($rows->get('Weby'))->toBe(['Weby', '1', '10', '50'])->and($rows->get('NVMe prostor'))->toBe(['NVMe prostor', '10 GB', '50 GB', '200 GB'])->and($rows->get('Zálohy'))->toBe(['Zálohy', '7 dní', '30 dní', '90 dní'])->and($rows->get('Staging prostředí'))->toBe(['Staging prostředí', '—', '✓', '✓']);

    $eshop = $pages['eshop'];
    expect(array_column($eshop['plans'], 'name'))->toBe(['Shop Start', 'Shop Growth', 'Shop Peak'])->and($eshop['plans'][1]['price'])->toBe(990)->and($eshop['plans'][2]['specs'][0])->toBe('Bez limitu produktů');
    expect($pages['ssl']['keep_first'])->toBe(1)->and($pages['ssl']['plans'][0])->toMatchArray(['name' => 'OV Business', 'price' => 1490, 'unitYear' => true]);
    expect($pages['cdn']['plans'][1])->toMatchArray(['name' => 'Shield', 'price' => 890, 'tag' => 'Nejoblíbenější']);
    expect($pages['backup']['plans'])->toHaveCount(3)->and($pages['vps']['plans'][0]['specs'])->toContain('2 vCPU', '4 GB RAM', '80 GB NVMe');
    expect($pages['domains'])->toMatchArray(['keep_last' => 1])->and($pages['domains']['plans'][0])->toMatchArray(['name' => '.cz', 'price' => 0, 'priceLabel' => '179 Kč', 'goto' => 'home', 'unitYear' => true])->and($pages['domains']['plans'][0]['specs'])->toContain('DNSSEC')->and($pages['domains']['plans'][0]['priceNote'])->toContain('obnova 179 Kč / rok');
    expect(json_encode($pages))->not->toMatch('/wedos|subreg|aapanel|ispconfig/i');

    // landing cards and the pricing page use the same plans, with taglines and bullets
    expect($data['cs']['webPlans'][1])->toBe(['Standard', 189, 'Pro weby, které mají návštěvnost.', ['10 webů', '50 GB NVMe', '50 schránek', '20 databází', 'Zálohy 30 dní'], 'Nejčastější volba']);
    expect($data['cs']['plans'][0]['cs'])->toBe(['Start', '', 'Pro první web nebo portfolio.', ['1 web', '10 GB NVMe', '5 schránek', '1 databáze', 'Zálohy 7 dní', 'Certifikát zdarma']]);
    expect($data['en']['pages']['web-hosting']['plans'][0]['specs'][0])->toBe('1 site')->and($data['en']['webPlans'][0][2])->toBe('For a first site or a portfolio.');
    // the cart resolves `crumb · plan` names as well as plain plan names
    expect($data['cs']['skus'])->toHaveKey('webhosting · standard|189')->and($data['cs']['skus']['webhosting · standard'])->toMatchArray(['product_key' => 'web-hosting', 'plan_key' => 'standard']);

    $html = $this->get('/')->assertOk()->getContent();
    expect($html)->toContain('/surfaces/api/onhost-svc-pages.api.js')->toContain('.map(fn => (window.OnhostSvcPages ? window.OnhostSvcPages.wrap(fn) : fn))')
        ->toContain('window.ONHOST_DATA.webPlans(cs)')->toContain("this.addToCart(name, price || 0, (window.OnhostSvcPages && window.OnhostSvcPages.sku(name, price)) || '');");
    $module = (string) file_get_contents((string) $this->get('/surfaces/api/onhost-svc-pages.api.js')->assertOk()->baseResponse->getFile());
    expect($module)->toContain('keep_first')->toContain('window.OnhostSvcPages = {');
    // cart rules, per-line add-ons, confirmation with payment instructions, billing address (seam #24)
    expect($html)->toContain('get COMMITS() { return window.OnhostCart')->toContain('coItemAddons: window.OnhostCart')->toContain('window.OnhostCart.doneCopy(window.__onhostOrder, cs).title')->toContain('{{ co.doneRows }}')->toContain('{{ co.lblAddress }}')->toContain('Alespoň 12 znaků, písmena i číslice.')->toContain('/surfaces/api/onhost-cart.api.js');
    // payment methods the platform really takes, an honest ETA per method, and the signed-in customer's identity pre-filled
    expect($html)->toContain("['card', 'Karta', 'Visa, Mastercard · platební brána'], ['bank', 'Bankovní převod', 'QR platba, zálohová faktura'],")->not->toContain("['paypal', 'PayPal'")->not->toContain("['sepa', 'SEPA inkaso'")
        ->toContain('window.OnhostCart.eta(this.state, true)')->toContain('window.OnhostCart.prefill() : {})');
    $cart = (string) file_get_contents((string) $this->get('/surfaces/api/onhost-cart.api.js')->assertOk()->baseResponse->getFile());
    expect($cart)->toContain('window.OnhostCart = {')->toContain('registrace na ')->toContain('obnova ')->toContain('prefill: function ()')->toContain('po připsání platby');

    // the game hosting landing (audit §5g-1): the game product's plans as the prototype's `gameSlots` cards, SKU-resolvable the way the cards add to the cart
    $slots = $data['cs']['gameSlots'];
    expect(array_column(array_column($slots, 'cs'), 0))->toContain('Minecraft · Paper')->toContain('Counter-Strike 2')->not->toContain('Game 4 GB')->and($slots[0]['egg'])->toBe('minecraft-paper')->and($slots[0]['p'])->toBe(84) // §5v: one card per game, "from" = base 49 + 1 GB above the base for Paper's 2 GB floor
        ->and($slots[0]['cs'][3])->toHaveCount(4)->and($slots[0]['cs'][3][0])->toBe('od 2 GB RAM')->and($slots[0]['en'][3][0])->toBe('from 2 GB RAM');
    expect($data['cs']['skus']['gamehosting game 8 gb|349'])->toMatchArray(['product_key' => 'game', 'plan_key' => 'game-8'])->and($data['en']['skus'])->toHaveKey('game hosting game 16 gb');
    expect($html)->toContain('window.ONHOST_DATA.gameSlots(cs)')->toContain("'Support within 10 min']] }\n    ]);");
    expect($html)->toContain('/surfaces/api/onhost-game-config.api.js')->toContain('id="game-config"')->toContain('window.OnhostGameConfig.pickOn(this, p.egg)')->toContain("'Již od '");
    // one term per order, monthly unless a year is picked, and the summary shows the server's quote (yearly list prices for 12/24 months)
    expect((float) $data['cs']['skus']['webhosting · standard']['price_year'])->toBe(1890.0)->and($pages['web-hosting']['plans'][1]['sku'])->toMatchArray(['plan_key' => 'standard', 'price_year' => 1890.0]);
    expect($html)->toContain('step: 1, commit: 1, co: {')->not->toContain('commit: st.commit || 12')->toContain("st.period === 'year' ? 12 : (st.commit || 1)")
        ->toContain('window.OnhostCart.totals(this)')->toContain('window.OnhostCart.termLabel(it, d, _)')->toContain('on: () => this.setState(st => ({ commit: c[0], cartItems: st.cartItems.map(x => (window.OnhostCart && window.OnhostCart.isDomain(x)) ? x : Object.assign({}, x, { commit: c[0] })) }))');
    expect($html)->toContain('totalLabel: (window.OnhostCart && window.OnhostCart.totalLabel) ? window.OnhostCart.totalLabel(s, cs) : ')->not->toContain("totalLabel: _('Celkem měsíčně', 'Monthly total'),");
    expect($cart)->toContain('totals: totals')->toContain("A().post('/cart/quote', {}")->toContain('X-Cart-Token')->toContain('platba na rok předem')->toContain('Celkem za rok');
    // amounts keep their haléře when they have them (2 286,90 Kč), whole crowns stay whole — in mny() and czk()
    expect($html)->toContain('const __x = Math.round(n * c.rate * 100) / 100, __d = Number.isInteger(__x) ? c.dec : Math.max(c.dec, 2);')
        ->toContain('const __x = Math.round(n * 1.21 * c.rate * 100) / 100, __d = Number.isInteger(__x) ? c.dec : Math.max(c.dec, 2);')
        ->not->toContain('{ minimumFractionDigits: c.dec, maximumFractionDigits: c.dec }).format(n * c.rate);');
});
