<?php

declare(strict_types=1);

// N21: X-Request-Id on every response; a client value is accepted only as a valid UUID.

it('answers every request with a generated X-Request-Id', function () {
    $id = $this->get('/up')->headers->get('X-Request-Id');
    expect($id)->toBeString()->and(strlen((string) $id))->toBe(26);
});

it('keeps a client X-Request-Id that is a valid UUID', function () {
    $id = '3f2b8c1e-9a4d-4e6f-8b7a-1c2d3e4f5a6b';
    $this->withHeaders(['X-Request-Id' => strtoupper($id)])->get('/up')->assertHeader('X-Request-Id', $id);
});

it('replaces a client X-Request-Id that is not a UUID', function (string $bad) {
    $sent = $this->withHeaders(['X-Request-Id' => $bad])->get('/up')->headers->get('X-Request-Id');
    expect($sent)->not->toBe($bad)->and(strlen((string) $sent))->toBe(26);
})->with(['not-a-uuid', '3f2b8c1e-9a4d-4e6f-8b7a-1c2d3e4f5a6b; drop', str_repeat('a', 200)]);
