<?php

declare(strict_types=1);

/*
 * W4-4: every lang/sk file carries exactly the keys of its lang/cs twin, with the same :placeholders per key; access.php
 * and permissions.php stay on the sk -> cs fallback and must not exist in lang/sk.
 * W5-5: lang/sk files without a lang/cs twin (the Czech text lives in the code branches) are listed here, carry the
 * owner-review header and use only placeholders the code passes.
 */

/** @return array<string, list<string>> */
function langSkParityFlatten(array $lines, string $prefix = ''): array
{
    $flat = [];
    foreach ($lines as $key => $value) {
        $name = $prefix === '' ? (string) $key : $prefix.'.'.$key;
        if (is_array($value)) {
            $flat += langSkParityFlatten($value, $name);

            continue;
        }
        preg_match_all('/:[a-z_]+/', (string) $value, $matches);
        $placeholders = $matches[0];
        sort($placeholders);
        $flat[$name] = $placeholders;
    }
    ksort($flat);

    return $flat;
}

/** @return array<string, array<string, list<string>>> W5-5 sk-only files: key => placeholders the code passes */
function langSkParitySkOnly(): array
{
    return ['surface.php' => [
        'metric.disk' => [], 'metric.inodes' => [], 'metric.mail' => [], 'metric.memory' => [], 'metric.traffic' => [], 'metric.transfer' => [], 'metric.web_disk_total' => [],
        'most_popular' => [], 'stock_lead' => [':days', ':days'], 'stock_on_order' => [], 'stock_unit' => [], 'stock_units' => [':count', ':count', ':count'],
    ]];
}

it('keeps lang/sk key-for-key and placeholder-for-placeholder with lang/cs', function () {
    $files = glob(dirname(__DIR__, 2).'/lang/sk/*.php') ?: [];
    expect($files)->not->toBeEmpty();
    foreach ($files as $file) {
        if (isset(langSkParitySkOnly()[basename($file)])) {
            continue;
        }
        $twin = dirname(__DIR__, 2).'/lang/cs/'.basename($file);
        expect(is_file($twin))->toBeTrue();
        $sk = langSkParityFlatten(require $file);
        $cs = langSkParityFlatten(require $twin);
        expect(array_keys($sk))->toBe(array_keys($cs));
        expect($sk)->toBe($cs);
    }
});

it('keeps the W5-5 sk-only files to the keys and placeholders the sk branches use', function () {
    foreach (langSkParitySkOnly() as $name => $expected) {
        $file = dirname(__DIR__, 2).'/lang/sk/'.$name;
        expect(is_file($file))->toBeTrue()
            ->and(is_file(dirname(__DIR__, 2).'/lang/cs/'.$name))->toBeFalse()
            ->and((string) file_get_contents($file))->toContain('schváleno vlastníkem 2026-10-16 (W4-K3), aktivní až s ONHOST_LOCALE_SK=true')
            ->and((string) file_get_contents($file))->not->toContain('čeká na jedno čtení');
        ksort($expected);
        expect(langSkParityFlatten(require $file))->toBe($expected);
    }
});

it('marks every lang/sk file as owner-approved but inactive until ONHOST_LOCALE_SK=true', function () {
    $root = dirname(__DIR__, 2);
    foreach (['surface.php', 'passwords.php', 'validation.php', 'auth.php'] as $name) {
        $header = (string) file_get_contents($root.'/lang/sk/'.$name);
        expect($header)->toContain('schváleno vlastníkem 2026-10-16 (W4-K3), aktivní až s ONHOST_LOCALE_SK=true')
            ->and($header)->not->toContain('čeká na jedno čtení');
    }
    expect(config('onhost.locale_sk'))->toBeFalse()
        ->and((string) file_get_contents($root.'/.env.example'))->toMatch('/^ONHOST_LOCALE_SK=false\b/m');
});

it('leaves access.php and permissions.php out of lang/sk', function () {
    $dir = dirname(__DIR__, 2).'/lang/sk';
    expect(is_file($dir.'/access.php'))->toBeFalse()->and(is_file($dir.'/permissions.php'))->toBeFalse();
});
