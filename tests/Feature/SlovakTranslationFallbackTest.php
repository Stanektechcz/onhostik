<?php

declare(strict_types=1);

use Illuminate\Support\Facades\App;

// C2 (panel 2026-10-15): 'sk' falls back to Czech, the global fallback stays as configured.

function c2SkFallbackRegisterLines(): void
{
    app('translator')->addLines(['c2test.greeting' => 'Ahoj (cs)'], 'cs');
    app('translator')->addLines(['c2test.greeting' => 'Hello (en)'], 'en');
}

it('serves Czech strings to the sk locale', function () {
    c2SkFallbackRegisterLines();
    App::setLocale('sk');

    expect(__('c2test.greeting'))->toBe('Ahoj (cs)')
        ->and(app('translator')->getFallback())->toBe('cs');
});

it('restores the global fallback for other locales', function () {
    c2SkFallbackRegisterLines();
    App::setLocale('sk');
    App::setLocale('de');

    expect(config('app.fallback_locale'))->toBe('en')
        ->and(app('translator')->getFallback())->toBe('en')
        ->and(__('c2test.greeting'))->toBe('Hello (en)');
});
