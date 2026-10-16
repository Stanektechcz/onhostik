<?php

declare(strict_types=1);

use App\Http\Support\PublicSeo;
use App\Http\Support\SurfaceRenderer;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\TaxRuleSeeder;
use Onhost\Domain\Notifications\Lexicon;
use Onhost\Domain\Services\Metering\WebDiskTotal;
use Onhost\Domain\Services\UsageWatch;

/*
 * W5-5 (SK phase 2 behind config('onhost.locale_sk')): off, every public page, the catalogue and the cart quote answer
 * byte for byte as on 8817e21, also for ?locale=sk; on, the inventory branches (docs/development/sk-locale-inventory.md)
 * give Slovak its own format and texts and never English. The locale never changes currency, price, VAT or the invoice,
 * PublicSeo stays Czech only and apps/surfaces stays byte-identical.
 *
 * Reference snapshots live in tests/fixtures/sk-phase2/. They must come from the unchanged code (8817e21): record them
 * there with SK2_RECORD=1 (a missing snapshot skips its comparison, it is never written outside record mode).
 */

function sk2FixtureDir(): string
{
    return dirname(__DIR__).'/fixtures/sk-phase2';
}

/** CSRF tokens, CSP nonces, generated ids and timestamps vary per request; everything else must not. */
function sk2Normalize(string $body): string
{
    $body = (string) preg_replace('/(name="csrf-token" content=")[^"]*"/', '$1CSRF"', $body);
    $body = (string) preg_replace('/(csrf["\']?\s*:\s*["\'])[^"\']*/', '$1CSRF', $body);
    $body = (string) preg_replace('/(name="_token" value=")[^"]*"/', '$1CSRF"', $body);
    $body = (string) preg_replace('/nonce="[^"]*"/', 'nonce="NONCE"', $body);
    $body = (string) preg_replace('/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/i', 'UUID', $body);
    $body = (string) preg_replace('/[0-9A-HJKMNP-TV-Z]{26}/', 'ULID', $body);

    return (string) preg_replace('/\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(\.\d+)?([+-]\d{2}:\d{2}|Z)?/', 'TIMESTAMP', $body);
}

/** @return array<string, string> fixture name => normalized body, for ?locale=sk and ?locale=cs */
function sk2Capture($test): array
{
    $out = [];
    foreach (['sk', 'cs'] as $locale) {
        foreach (PublicSeo::paths() as $path) {
            $slug = $path === '/' ? 'root' : trim(str_replace('/', '_', $path), '_');
            $out["page-{$slug}-{$locale}.html"] = sk2Normalize((string) $test->get($path.'?locale='.$locale)->getContent());
        }
        $out["catalog-{$locale}.json"] = sk2Normalize((string) $test->getJson('/v1/catalog?locale='.$locale)->getContent());
        $token = (string) $test->putJson('/v1/cart', ['items' => [['line_id' => 'l1', 'product_key' => 'web-hosting', 'plan_key' => 'standard', 'qty' => 1, 'config' => []]], 'commit_months' => 12, 'currency' => 'CZK'])->json('data.token');
        $out["quote-{$locale}.json"] = sk2Normalize((string) $test->withHeader('X-Cart-Token', $token)->postJson('/v1/cart/quote?locale='.$locale)->getContent());
    }

    return $out;
}

/** @return array<string, mixed> */
function sk2Quote($test, string $locale): array
{
    $token = (string) $test->putJson('/v1/cart', ['items' => [['line_id' => 'l1', 'product_key' => 'web-hosting', 'plan_key' => 'standard', 'qty' => 1, 'config' => []]], 'commit_months' => 12, 'currency' => 'CZK'])->assertOk()->json('data.token');

    return (array) $test->withHeader('X-Cart-Token', $token)->postJson('/v1/cart/quote?locale='.$locale)->assertOk()->json('data');
}

/** Every amount, rate, currency and VAT field of the quote lines, at any depth; wording is left out. @return array<string, mixed> */
function sk2Amounts(array $lines, string $prefix = ''): array
{
    $out = [];
    foreach ($lines as $key => $value) {
        $name = $prefix.'/'.$key;
        if (is_array($value)) {
            $out += sk2Amounts($value, $name);
        } elseif (preg_match('/tax|vat|total|amount|minor|price|currency|rate|qty/i', (string) $key) === 1) {
            $out[$name] = $value;
        }
    }

    return $out;
}

it('(a) answers byte for byte as before W5-5 while locale_sk is off, also for ?locale=sk', function () {
    config()->set('onhost.locale_sk', false);
    $this->seed([CatalogSeeder::class, TaxRuleSeeder::class]);
    $record = (string) getenv('SK2_RECORD') === '1';
    $compared = 0;
    foreach (sk2Capture($this) as $name => $body) {
        $file = sk2FixtureDir().'/'.$name;
        if ($record) {
            file_put_contents($file, $body);

            continue;
        }
        if (! is_file($file)) {
            continue;
        }
        expect($body)->toBe((string) file_get_contents($file), $name);
        $compared++;
    }
    if (! $record && $compared === 0) {
        $this->markTestSkipped('No sk-phase2 snapshots recorded yet: run SK2_RECORD=1 on 8817e21 (W5-5 commit 1).');
    }
});

it('(b) gives sk the Slovak format and texts, never an English string of the inventory rows', function () {
    config()->set('onhost.locale_sk', true);
    expect(Lexicon::slovak('sk'))->toBeTrue();
    foreach (['disk' => 'priestor', 'traffic' => 'prenos dát', 'memory' => 'pamäť', 'mail' => 'poštové schránky', 'inodes' => 'počet súborov', 'transfer' => 'mesačný prenos dát'] as $key => $label) {
        expect(UsageWatch::metricLabel($key, 'sk'))->toBe($label);
    }
    expect(UsageWatch::metricLabel(WebDiskTotal::METRIC, 'sk'))->toBe('priestor tarifu celkom (súbory, databázy, pošta)');
    expect(trans_choice('surface.stock_units', 1, ['count' => 1], 'sk'))->toBe('1 kus na sklade')
        ->and(trans_choice('surface.stock_units', 3, ['count' => 3], 'sk'))->toBe('3 kusy na sklade')
        ->and(trans_choice('surface.stock_units', 7, ['count' => 7], 'sk'))->toBe('7 kusov na sklade');

    $panel = SurfaceRenderer::panelSeams((string) file_get_contents(base_path('apps/surfaces/Onhost-app.dc.html')));
    expect($panel)->toContain("'sk-SK'");

    $english = ['Most popular', 'rental / month', 'units in stock', 'made to order', 'delivery in ', 'disk space', 'file count', 'monthly transfer', 'plan storage'];
    $texts = array_merge(array_map(fn (string $k) => UsageWatch::metricLabel($k, 'sk'), ['disk', 'traffic', 'memory', 'mail', 'inodes', 'transfer', WebDiskTotal::METRIC]),
        [__('surface.most_popular', [], 'sk'), __('surface.stock_unit', [], 'sk'), __('surface.stock_lead', ['days' => 5], 'sk'), __('surface.stock_on_order', [], 'sk')]);
    foreach ($texts as $text) {
        foreach ($english as $phrase) {
            expect(str_contains($text, $phrase))->toBeFalse("{$text}: {$phrase}");
        }
    }

    config()->set('onhost.locale_sk', false);
    expect(Lexicon::slovak('sk'))->toBeFalse()
        ->and(UsageWatch::metricLabel('disk', 'sk'))->toBe('disk space') // the pre-W5-5 output, unchanged while off
        ->and(SurfaceRenderer::panelSeams((string) file_get_contents(base_path('apps/surfaces/Onhost-app.dc.html'))))->not->toContain("'sk-SK'");
});

it('(c) quotes the same cart identically for cs, sk and en', function () {
    config()->set('onhost.locale_sk', true);
    $this->seed([CatalogSeeder::class, TaxRuleSeeder::class]);
    $cs = sk2Quote($this, 'cs');
    foreach (['sk', 'en'] as $locale) {
        $other = sk2Quote($this, $locale);
        foreach (['subtotal', 'tax', 'total', 'currency'] as $field) {
            expect($other[$field])->toBe($cs[$field]);
        }
        expect(sk2Amounts((array) $other['lines']))->toBe(sk2Amounts((array) $cs['lines']));
    }
});

it('(d) keeps the Czech pages Czech: the sk hreflang points to /sk/, never to a Czech URL', function () {
    config()->set('onhost.locale_sk', true);
    expect(PublicSeo::languages())->toBe(['cs', 'sk']); // W6-6: the SK texts are approved; Slovak lives under /sk/, never on the Czech URL
    expect((string) $this->get('/')->getContent())->not->toContain('hreflang="sk" href="'.PublicSeo::base().'/"')->toContain('hreflang="sk" href="'.PublicSeo::base().'/sk/"');
    config()->set('onhost.locale_sk', false);
    expect(PublicSeo::languages())->toBe(['cs']);
    expect((string) $this->get('/')->getContent())->not->toContain('hreflang="sk"');
});

it('(e) leaves apps/surfaces/*.dc.html byte-identical', function () {
    $expected = json_decode((string) file_get_contents(sk2FixtureDir().'/surfaces.sha1.json'), true);
    $actual = [];
    foreach (glob(base_path('apps/surfaces/*.dc.html')) ?: [] as $file) {
        $actual[basename($file)] = sha1_file($file);
    }
    ksort($actual);
    ksort($expected);
    expect($actual)->toBe($expected);
});
