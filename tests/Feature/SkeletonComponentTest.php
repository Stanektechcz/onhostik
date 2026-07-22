<?php

declare(strict_types=1);

/**
 * Reusable skeleton loading-placeholder component.
 */

it('renders the requested number of skeleton lines', function (): void {
    $html = \Illuminate\Support\Facades\Blade::render(
        '<x-panel.skeleton type="text" :rows="4" />'
    );

    // 4 line placeholders, all carrying the shared .skeleton class.
    expect(substr_count($html, 'skeleton-line'))->toBe(4)
        ->and($html)->toContain('skeleton')
        // Decorative — hidden from assistive tech.
        ->and($html)->toContain('aria-hidden');
});

it('renders a card skeleton with a title and block', function (): void {
    $html = \Illuminate\Support\Facades\Blade::render('<x-panel.skeleton type="card" :rows="2" />');

    expect($html)->toContain('skeleton-title')
        ->and($html)->toContain('skeleton-block');
});

it('renders a list skeleton with avatars', function (): void {
    $html = \Illuminate\Support\Facades\Blade::render('<x-panel.skeleton type="list" :rows="3" />');

    expect(substr_count($html, 'skeleton-avatar'))->toBe(3);
});

it('ships the skeleton styles in the project CSS layer', function (): void {
    $css = (string) file_get_contents(public_path('panel/css/onhost.css'));

    expect($css)->toContain('.skeleton')
        ->toContain('onhost-skeleton-shimmer')
        // Accessibility: no shimmer for reduced-motion users.
        ->toContain('prefers-reduced-motion');
});
