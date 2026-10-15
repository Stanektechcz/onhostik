<?php

declare(strict_types=1);

/*
 * W4-4: every lang/sk file carries exactly the keys of its lang/cs twin, with the same :placeholders per key; access.php
 * and permissions.php stay on the sk -> cs fallback and must not exist in lang/sk.
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

it('keeps lang/sk key-for-key and placeholder-for-placeholder with lang/cs', function () {
    $files = glob(dirname(__DIR__, 2).'/lang/sk/*.php') ?: [];
    expect($files)->not->toBeEmpty();
    foreach ($files as $file) {
        $twin = dirname(__DIR__, 2).'/lang/cs/'.basename($file);
        expect(is_file($twin))->toBeTrue();
        $sk = langSkParityFlatten(require $file);
        $cs = langSkParityFlatten(require $twin);
        expect(array_keys($sk))->toBe(array_keys($cs));
        expect($sk)->toBe($cs);
    }
});

it('leaves access.php and permissions.php out of lang/sk', function () {
    $dir = dirname(__DIR__, 2).'/lang/sk';
    expect(is_file($dir.'/access.php'))->toBeFalse()->and(is_file($dir.'/permissions.php'))->toBeFalse();
});
