<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use Illuminate\Testing\TestResponse;

// W7-2b (MERENI P4 upravené): scripts transformed in PHP and onhost-data.js answer If-None-Match with 304; Cache-Control unchanged.

function setEtagFetch(string $uri, ?string $etag = null): TestResponse
{
    return test()->get($uri, $etag === null ? [] : ['If-None-Match' => $etag]);
}

it('serves a transformed script with an ETag and answers a matching If-None-Match with 304', function () {
    config(['onhost.ui.demo' => false]);
    $first = setEtagFetch('/surfaces/onhost-shell.js');
    $first->assertOk();
    $etag = (string) $first->headers->get('ETag');
    expect($etag)->toBe('"'.sha1((string) $first->getContent()).'"')
        ->and((string) $first->headers->get('Cache-Control'))->toContain('max-age=300');

    $second = setEtagFetch('/surfaces/onhost-shell.js', $etag);
    $second->assertStatus(304);
    expect((string) $second->getContent())->toBe('');
});

it('answers 200 with a different ETag once the served script content changes', function () {
    config(['onhost.ui.demo' => false]);
    $etag = (string) setEtagFetch('/surfaces/onhost-shell.js')->headers->get('ETag');

    config(['onhost.ui.demo' => true]); // demo serves the pristine prototype file, not the PHP-transformed one
    $changed = setEtagFetch('/surfaces/onhost-shell.js', $etag);
    $changed->assertOk();
    expect((string) $changed->headers->get('ETag'))->not->toBe($etag);
});

it('serves onhost-data.js with an ETag, 304 on a match and a new ETag after the content changes', function () {
    Cache::forget('surfaces:onhost-data.js');
    $first = setEtagFetch('/surfaces/onhost-data.js');
    $first->assertOk();
    $etag = (string) $first->headers->get('ETag');
    expect($etag)->toBe('"'.sha1((string) $first->getContent()).'"')
        ->and((string) $first->headers->get('Cache-Control'))->toContain('max-age=60');

    $second = setEtagFetch('/surfaces/onhost-data.js', $etag);
    $second->assertStatus(304);
    expect((string) $second->getContent())->toBe('');

    config(['onhost.legal_entity.email' => 'etag-change@example.test']);
    Cache::forget('surfaces:onhost-data.js');
    $third = setEtagFetch('/surfaces/onhost-data.js', $etag);
    $third->assertOk();
    expect((string) $third->headers->get('ETag'))->not->toBe($etag);
});
