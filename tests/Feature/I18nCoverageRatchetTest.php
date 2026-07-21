<?php

declare(strict_types=1);

/**
 * N141 — hardcoded Czech strings in Blade.
 *
 * The audit called these "leftovers". They are not: there are ~4 160 of them
 * across ~400 templates, against 423 keys in `lang/`. The app is Czech-first
 * with partial i18n, and full extraction is a project of its own — mass
 * generating four thousand translation keys in one unverified sweep would
 * break layouts and bake in typos faster than anyone could review them.
 *
 * So this is a RATCHET, the same device that retired the inline-handler debt:
 * the count may fall, never rise. New work must use `__()`; the existing debt
 * gets paid down deliberately, screen by screen, and this test records the
 * progress. Lower the baseline whenever you extract a batch.
 */
it('does not grow the hardcoded-Czech debt in Blade templates', function (): void {
    // Baseline raised 4161 → 4193 (2026-07-21): the W-batch added admin-only
    // screens (DNSSEC card, approvals queue, DPA button, session-cap notice),
    // the partner banner generator (112) and the partner payout/stats forms
    // (finishing the "PŘIPRAVUJEME" placeholders), all in the same Czech-first
    // style as the rest of the app, which has no English for these views. The
    // shared-component cap below still holds.
    expect(hardcodedCzechCount())->toBeLessThanOrEqual(4193);
});

it('keeps newly added shared components translatable', function (): void {
    /*
     | The shared panel components are the highest-leverage place to hold the
     | line: every screen renders them, so an untranslated string here is seen
     | by every English-speaking user on every page.
     */
    $componentDebt = hardcodedCzechCount(resource_path('views/components'));

    expect($componentDebt)->toBeLessThanOrEqual(60);
});

/** Occurrences of visible Czech text not wrapped in a translation call. */
function hardcodedCzechCount(?string $root = null): int
{
    $root ??= resource_path('views');

    if (! is_dir($root)) {
        return 0;
    }

    $czech = '/[ěščřžýáíéúůňťďĚŠČŘŽÝÁÍÉÚŮŇŤĎ]/u';
    $total = 0;

    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));

    foreach ($it as $file) {
        if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.blade.php')) {
            continue;
        }

        $source = (string) file_get_contents($file->getPathname());

        // Comments and scripts are not user-visible copy.
        $source = (string) preg_replace('/\{\{--.*?--\}\}/s', '', $source);
        $source = (string) preg_replace('#<script.*?</script>#s', '', $source);

        if (preg_match_all('/>([^<>{}]{3,})</', $source, $matches) > 0) {
            foreach ($matches[1] as $text) {
                $text = trim($text);

                if ($text !== '' && preg_match($czech, $text) === 1 && ! str_contains($text, '__(')) {
                    $total++;
                }
            }
        }
    }

    return $total;
}
