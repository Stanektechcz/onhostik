<?php

declare(strict_types=1);

use Database\Seeders\IntegrationSeeder;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;

/**
 * Guard for audit H119.
 *
 * Content-Security-Policy sets script-src with a per-request nonce and
 * deliberately WITHOUT 'unsafe-inline'. A nonce authorises <script> blocks —
 * it does NOT authorise inline event ATTRIBUTES (that would need
 * 'unsafe-hashes'). So under SECURITY_CSP_ENFORCE=true an
 * onsubmit="return confirm(...)" never executes; and because it never
 * executes it never returns false either, so the destructive form submits
 * with no confirmation whatsoever. Silent, and worse than having no guard.
 *
 * Confirmations are therefore declared with data-confirm / data-prompt and
 * bound by a delegated listener in the layout. These tests fail if an inline
 * handler or an unnonced <script> creeps back in.
 */
beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class, IntegrationSeeder::class]);
});

/** @return list<string> */
function bladeFiles(): array
{
    $files = [];

    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(resource_path('views')));

    foreach ($iterator as $file) {
        if ($file->isFile() && str_ends_with($file->getFilename(), '.blade.php')) {
            $files[] = $file->getPathname();
        }
    }

    return $files;
}

/** Inline handler occurrences across all Blade files, ignoring comments. */
function inlineHandlerCount(): int
{
    $total = 0;

    foreach (bladeFiles() as $path) {
        $source = (string) file_get_contents($path);

        // Strip Blade comments — the explanatory note in the layout quotes the
        // old pattern deliberately and must not count as an offender.
        $source = (string) preg_replace('/\{\{--.*?--\}\}/s', '', $source);

        $total += preg_match_all('/\son(submit|click|change|input|load|error)\s*=\s*"/i', $source);
    }

    return $total;
}

it('has no inline handler on any form', function (): void {
    // Forms are the dangerous case: an onsubmit="return confirm(...)" that CSP
    // drops does not merely stop working — it stops CANCELLING, so the
    // destructive submit goes through unconfirmed. Those are all migrated to
    // data-confirm / data-prompt.
    $offenders = [];

    foreach (bladeFiles() as $path) {
        $source = (string) preg_replace('/\{\{--.*?--\}\}/s', '', (string) file_get_contents($path));

        if (preg_match('/\sonsubmit\s*=\s*"/i', $source)) {
            $offenders[] = str_replace(resource_path('views') . DIRECTORY_SEPARATOR, '', $path);
        }
    }

    expect($offenders)->toBe([], "Inline onsubmit (silently dropped by CSP enforce) in:\n" . implode("\n", $offenders));
});

it('has retired the remaining inline-handler debt', function (): void {
    /*
     | This started as a ratchet at 38 — onclick/onchange handlers calling
     | page-local functions, retired incrementally because they broke visibly
     | rather than silently like a skipped confirmation.
     |
     | The debt is now zero. Two of those 38 were worse than "visible": the
     | sidebar logout link and the one-time API-token copy button both did
     | nothing at all under enforce.
     |
     | See CspInlineHandlerTest for the full-coverage version of this check.
     */
    expect(inlineHandlerCount())->toBe(0);
});

it('nonces every inline script block', function (): void {
    $offenders = [];

    foreach (bladeFiles() as $path) {
        $source = (string) file_get_contents($path);

        // A bare <script> with no nonce and no src is blocked under enforce.
        if (preg_match('/<script(?![^>]*(nonce|src))[^>]*>/i', $source)) {
            $offenders[] = str_replace(resource_path('views') . DIRECTORY_SEPARATOR, '', $path);
        }
    }

    expect($offenders)->toBe([], "Un-nonced inline <script> in:\n" . implode("\n", $offenders));
});

it('keeps unsafe-inline out of the script-src directive', function (): void {
    $response = $this->actingAs(customerUser())->get(route('panel.dashboard'));

    $csp = $response->headers->get('Content-Security-Policy')
        ?? $response->headers->get('Content-Security-Policy-Report-Only')
        ?? '';

    expect($csp)->toContain('script-src')
        ->and($csp)->toContain('nonce-');

    preg_match('/script-src[^;]*/', $csp, $matches);

    // If this ever gains 'unsafe-inline' the nonce becomes decorative.
    expect($matches[0] ?? '')->not->toContain("'unsafe-inline'");
});

it('still renders destructive actions with a declared confirmation', function (): void {
    $service = \App\Domains\Provisioning\Models\Service::factory()->create([
        'provisioning_driver' => \App\Domains\Provisioning\Enums\ProvisioningDriver::AAPanel,
        'status'              => \App\Domains\Provisioning\Enums\ServiceStatus::Active,
        'label'               => 'potvrzeni.cz',
    ]);

    $this->actingAs(adminUser())
        ->get(route('admin.services.show', $service))
        ->assertOk()
        ->assertSee('data-confirm', false);
});

it('renders the cart with its confirmations intact', function (): void {
    $user = customerUser();
    $plan = \App\Domains\Products\Models\PricingPlan::where('is_active', true)->firstOrFail();

    $this->actingAs($user)->post(route('panel.cart.add', $plan->id));

    $this->actingAs($user)
        ->get(route('panel.cart.index'))
        ->assertOk()
        ->assertSee('Opravdu vyprázdnit celý košík?', false);
});
