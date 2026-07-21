<?php

declare(strict_types=1);

use Database\Seeders\IntegrationSeeder;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;

/**
 * N137 focus-trap + N138 keyboard shortcuts.
 *
 * These are browser behaviours, so the tests assert the CONTRACT the script
 * depends on is rendered: the shortcut dialog exists with the right roles, the
 * handlers are inside a nonced block (CSP enforce would silently drop them
 * otherwise — see H119), and no inline handler was reintroduced.
 */
beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class, IntegrationSeeder::class]);
});

it('renders the shortcut help dialog for a signed-in user', function (): void {
    $html = $this->actingAs(customerUser())->get(route('panel.dashboard'))->assertOk()->content();

    expect($html)->toContain('id="kbd-help"')
        ->and($html)->toContain('aria-labelledby="kbd-help-title"')
        // It must use the same .modal contract the trap observes.
        ->and($html)->toContain('class="modal" id="kbd-help"');
});

it('documents each shortcut it actually binds', function (): void {
    $html = $this->actingAs(customerUser())->get(route('panel.dashboard'))->assertOk()->content();

    // A help panel that lists a key the code does not handle is worse than none.
    foreach (['<kbd>/</kbd>', '<kbd>?</kbd>', '<kbd>Esc</kbd>', '<kbd>Tab</kbd>'] as $key) {
        expect($html)->toContain($key);
    }
});

it('does not show the shortcut dialog to a guest', function (): void {
    // Guests have no search or panel to navigate — the overlay would be noise.
    $html = $this->get('/')->assertOk()->content();

    expect($html)->not->toContain('id="kbd-help"');
});

it('keeps the focus-trap and shortcut handlers inside a nonced script', function (): void {
    $html = $this->actingAs(customerUser())->get(route('panel.dashboard'))->assertOk()->content();

    // Under CSP enforce an un-nonced block never runs — the trap would be
    // silently absent, which is exactly the H119 failure mode.
    expect($html)->toContain('MutationObserver')
        ->and($html)->toContain('aria-modal');

    $pos = strpos($html, 'MutationObserver');
    $before = substr($html, 0, (int) $pos);
    $lastScript = strrpos($before, '<script');

    expect(substr($before, (int) $lastScript, 200))->toContain('nonce=');
});

it('starts the shortcut dialog hidden', function (): void {
    $html = $this->actingAs(customerUser())->get(route('panel.dashboard'))->assertOk()->content();

    // Rendered but display:none — the script only toggles it.
    expect($html)->toContain('id="kbd-help"')
        ->and($html)->toMatch('/id="kbd-help".*?style="display:none;"/s');
});

it('has no inline handler on the dialog controls', function (): void {
    $html = $this->actingAs(customerUser())->get(route('panel.dashboard'))->assertOk()->content();

    $start = strpos($html, 'id="kbd-help"');
    $chunk = substr($html, (int) $start, 1400);

    // Close buttons use data-kbd-help-close, bound by the delegated listener.
    expect($chunk)->toContain('data-kbd-help-close')
        ->and($chunk)->not->toContain('onclick=');
});
