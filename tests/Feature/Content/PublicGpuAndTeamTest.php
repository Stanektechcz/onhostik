<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Database\Seeders\ContentSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Illuminate\Support\Facades\Cache;
use Onhost\Domain\Catalog\Models\Product;

/*
 * I-R16 (owner, 2026-10-08): GPU H100 / L40S are not sold now, only planned — the public site says "připravujeme" wherever it
 * offered the cards: no price, no stock count, no delivery time, no order. I-R19: the team page names the real team (Filip, Roman,
 * Adrian, Markéta) with initials avatars and neutral roles; the invented people, tenures, bios, shift sizes and reply medians are gone.
 * The prototype files stay byte-identical; demo mode keeps the prototype's copy.
 */

beforeEach(function () {
    $this->seed([CatalogSeeder::class, TaxRuleSeeder::class, LegalEntitySeeder::class, ContentSeeder::class]);
    Cache::flush();
});

function publicGpuHome($test): string
{
    return $test->get('/')->assertOk()->getContent();
}

it('says "připravujeme" wherever the public site offered GPU cards, with no price, stock or delivery time', function () {
    $html = publicGpuHome($this);

    foreach ([
        'Zbývá 7 z 24 karet', '7 of 24 cards left', "['GPU H100 (hodina)', 39]", "['GPU L40S (hour)', 19]", "s: '39 Kč/h'", "s: '19 CZK/h'",
        'H100 80 GB a L40S, účtované po hodinách', 'H100 and L40S cards sit in Prague', 'Karty H100 a L40S stojí v Praze', 'Inference v Praze, účtování po hodinách',
        "'12 900 Kč', 'nájem / měsíc', '6 karet skladem'", "'38,900 CZK'", "'84 Kč/h'", 'GPU účtujeme po započaté minutě', "'v ceně platformy'",
        'Proto vám u nich říkáme pět dnů', "'H100 a L40S v Praze'", 'GPU pro AI — na jedné české platformě', 'Od statického webu po GPU klastr',
    ] as $claim) {
        expect($html)->not->toContain($claim);
    }
    expect($html)->toContain('GPU H100 a L40S připravujeme')->toContain("['GPU H100', 'připravujeme']")->toContain("['GPU L40S', 'coming soon']")
        ->toContain("typeof i[1] === 'string' ? i[1]");
});

it('sells no GPU product and serves no GPU stock row', function () {
    expect(Product::query()->where('slug', 'like', '%gpu%')->exists())->toBeFalse();

    $stock = collect($this->getJson('/v1/stock')->assertOk()->json('data'))->pluck(0)->all();
    expect($stock)->not->toContain('L40S')->not->toContain('H100');
});

it('shows the real team with initials and neutral roles and none of the invented people or figures', function () {
    $html = publicGpuHome($this);

    foreach (['Petr Doležal', 'Lenka Horáková', "v: '18 min'", 'Noční směna · 2 lidé', 'Lidí v podpoře', 'Nikdo neprodává', 'První noční nikdy sám'] as $invented) {
        expect($html)->not->toContain($invented);
    }

    $js = $this->get('/surfaces/onhost-data.js')->assertOk()->getContent();
    preg_match('/var D = (\{.*\});\n  function L/s', $js, $m);
    $team = json_decode($m[1], true)['cs']['team'];
    expect(array_column($team, 'name'))->toBe(['Filip', 'Roman', 'Adrian', 'Markéta'])
        ->and(array_column($team, 'ini'))->toBe(['F', 'R', 'A', 'M'])
        ->and(array_column($team, 'role'))->toBe(['Tým ONhost', 'Tým ONhost', 'Zakladatel', 'Tým ONhost'])
        ->and(json_decode($m[1], true)['en']['team'][2]['role'])->toBe('Founder');
    expect($html)->toContain('window.ONHOST_DATA.team');
});

it('keeps the prototype copy in demo mode', function () {
    config(['onhost.ui.demo' => true]);

    expect(publicGpuHome($this))->toContain('Petr Doležal')->toContain("['GPU H100 (hodina)', 39]");
});
