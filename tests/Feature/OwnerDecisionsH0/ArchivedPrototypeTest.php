<?php

declare(strict_types=1);
use App\Http\Support\SurfaceRenderer;

/*
 * H0, owner decision H-R6 (2026-10-06): the prototype `onhost-domains.js` was loaded by nothing — archived, not deleted. Its
 * bytes are what they were, it is not served any more, and nothing in the surfaces or the shell asks for it.
 */

it('keeps the archived prototype byte for byte, serves it nowhere and loads it from nowhere', function () {
    expect(is_file(base_path('apps/surfaces/onhost-domains.js')))->toBeFalse()
        ->and(hash_file('sha256', base_path('apps/surfaces/_archive/onhost-domains.js')))->toBe('cd38a8691554899adb5fc38e928ef9483f3402ff582f0a5792e26f829c901d6c');

    $this->get('/surfaces/_archive/onhost-domains.js')->assertNotFound();
    $this->get('/surfaces/onhost-domains.js')->assertNotFound();
    $this->get('/surfaces/api/onhost-domains.api.js')->assertOk(); // the API-backed variant the product uses

    foreach (glob(base_path('apps/surfaces/*.{html,js,jsx}'), GLOB_BRACE) ?: [] as $file) {
        expect((string) file_get_contents($file))->not->toContain('onhost-domains.js', basename($file).' loads the archived prototype');
    }
});

it('serves nothing under the archive whatever the spelling of its path (review L)', function () {
    $renderer = app(SurfaceRenderer::class);
    foreach (['_archive/onhost-domains.js', '_ARCHIVE/onhost-domains.js', '_Archive/onhost-domains.js', './_archive/onhost-domains.js', '_archive//onhost-domains.js', '_archive\\onhost-domains.js', '_archive/README.md'] as $path) {
        expect($renderer->assetPath($path))->toBeNull($path);
    }
    expect($renderer->assetPath('onhost-shell.js'))->not->toBeNull();
});
