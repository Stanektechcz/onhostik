<?php

declare(strict_types=1);

// W7-2: guards for infra/aapanel/nginx-site.conf (compression, cache, HSTS stay where they belong).
function nscActiveLines(): array
{
    $text = (string) file_get_contents(dirname(__DIR__, 3).'/infra/aapanel/nginx-site.conf');
    $lines = [];
    foreach (preg_split('/\R/', $text) ?: [] as $line) {
        $code = trim((string) preg_replace('/#.*$/', '', $line));
        if ($code !== '') {
            $lines[] = $code;
        }
    }

    return $lines;
}

function nscLocationBody(string $opening): string
{
    $active = implode("\n", nscActiveLines());
    $start = strpos($active, $opening);
    if ($start === false) {
        return '';
    }
    $end = strpos($active, '}', $start);

    return $end === false ? substr($active, $start) : substr($active, $start, $end - $start);
}

it('sets no Strict-Transport-Security header in nginx', function () {
    expect(preg_grep('/add_header\s+Strict-Transport-Security/i', nscActiveLines()))->toBe([]);
});

it('keeps brotli commented out', function () {
    expect(preg_grep('/\bbrotli\s+on\b/', nscActiveLines()))->toBe([]);
});

it('does not duplicate gzip_types for json', function () {
    expect(preg_grep('/gzip_types[^;]*application\/json/', nscActiveLines()))->toBe([]);
});

it('sets no expires or cache-control on the front controller', function () {
    $body = nscLocationBody('location / {');
    expect($body)->not->toBe('')
        ->and($body)->not->toContain('expires')
        ->and(stripos($body, 'Cache-Control'))->toBeFalse();
});

it('caches only the favicon', function () {
    expect(nscLocationBody('location = /favicon.ico {'))->toContain('expires 30d');
});
