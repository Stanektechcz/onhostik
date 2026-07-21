<?php

declare(strict_types=1);

/**
 * Phase N (N172/N173): the localisation files stay in sync.
 *
 * The failure this guards is quiet: a string added to `cs` but not `en` shows
 * an English-language user the raw key ("services.pay_renewal") instead of a
 * translation. Nothing errors — it just looks broken to exactly the audience
 * the English file exists for.
 */

/** @return array<string, string> dotted key => value */
function flattenLang(string $file): array
{
    /** @var array<string, mixed> $data */
    $data = require $file;

    $out = [];
    $walk = function (array $node, string $prefix) use (&$walk, &$out): void {
        foreach ($node as $key => $value) {
            $dotted = $prefix === '' ? (string) $key : $prefix . '.' . $key;
            if (is_array($value)) {
                $walk($value, $dotted);
            } else {
                $out[$dotted] = (string) $value;
            }
        }
    };
    $walk($data, '');

    return $out;
}

it('has an English translation for every Czech panel key', function (): void {
    $cs = flattenLang(lang_path('cs/panel.php'));
    $en = flattenLang(lang_path('en/panel.php'));

    $missing = array_keys(array_diff_key($cs, $en));

    expect($missing)->toBe([], "Chybí v en/panel.php:\n" . implode("\n", $missing));
});

it('has no stray English key absent from Czech', function (): void {
    // The reverse drift: an en key with no cs source means the default locale
    // would fall through to English.
    $cs = flattenLang(lang_path('cs/panel.php'));
    $en = flattenLang(lang_path('en/panel.php'));

    $extra = array_keys(array_diff_key($en, $cs));

    expect($extra)->toBe([], "Navíc v en/panel.php:\n" . implode("\n", $extra));
});

it('keeps placeholder tokens consistent between locales', function (): void {
    // A :placeholder present in cs but dropped in en would render a literal
    // ":count" to the user. Assert both sides carry the same tokens.
    $cs = flattenLang(lang_path('cs/panel.php'));
    $en = flattenLang(lang_path('en/panel.php'));

    $mismatched = [];

    foreach ($cs as $key => $csValue) {
        if (! isset($en[$key])) {
            continue;
        }

        preg_match_all('/:([a-z_]+)/', $csValue, $csTokens);
        preg_match_all('/:([a-z_]+)/', $en[$key], $enTokens);

        sort($csTokens[1]);
        sort($enTokens[1]);

        if ($csTokens[1] !== $enTokens[1]) {
            $mismatched[] = $key;
        }
    }

    expect($mismatched)->toBe([], "Nesouhlasí placeholdery:\n" . implode("\n", $mismatched));
});
