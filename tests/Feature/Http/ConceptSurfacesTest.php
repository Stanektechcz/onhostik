<?php

declare(strict_types=1);

/*
 * Owner decision R11 (audit A3): the mobile shell (/m, Onhost-mobil.dc.html) and the component gallery (/widgets,
 * Onhost-widgets.dc.html) are design concepts with narrated data. Outside demo mode only staff open them; every serving is
 * kept out of search engines (X-Robots-Tag and a robots meta) and carries a "koncept" banner. The prototype files stay
 * byte-identical — the renderer adds the head lines.
 */

function conceptSurfaceAssertMarked(string $html, array $headers): void
{
    expect($headers['x-robots-tag'][0] ?? null)->toBe('noindex, nofollow');
    expect($html)->toContain("<head>\n<meta name=\"robots\" content=\"noindex, nofollow\">")->toContain("b.id = 'onhost-concept-banner';")
        ->toContain('KONCEPT · ukázka návrhu, ne funkční část ONhostu — data jsou smyšlená');
}

it('asks a guest to sign in and sends a customer to the panel', function () {
    foreach (['/m', '/m/sluzby', '/widgets'] as $path) {
        $this->get($path)->assertRedirect('/prihlaseni?next='.urlencode($path));
    }
    [$customer] = $this->customerWithOrganization();
    $this->actingAs($customer, 'sanctum');
    foreach (['/m', '/m/sluzby', '/widgets'] as $path) {
        $this->get($path)->assertRedirect('/panel');
    }
});

it('serves the concepts to staff, never indexed and marked as a concept', function () {
    $this->actingAs($this->staff('support_manager'), 'sanctum');
    foreach (['/m' => 'Onhost-mobil.dc.html', '/widgets' => 'Onhost-widgets.dc.html'] as $path => $file) {
        $response = $this->get($path)->assertOk();
        conceptSurfaceAssertMarked($response->getContent(), $response->headers->all());
        expect(substr_count($response->getContent(), 'name="robots"'))->toBe(1);
        expect((string) file_get_contents(base_path('apps/surfaces/'.$file)))->not->toContain('onhost-concept-banner')->not->toContain('name="robots"'); // the prototype is untouched
    }
});

it('keeps the concepts open in demo mode, still unindexed and marked', function () {
    config(['onhost.ui.demo' => true]);
    foreach (['/m', '/widgets'] as $path) {
        $response = $this->get($path)->assertOk();
        conceptSurfaceAssertMarked($response->getContent(), $response->headers->all());
    }
});

it('leaves the product surfaces indexable and without the concept banner', function () {
    $response = $this->get('/')->assertOk();
    expect($response->headers->has('X-Robots-Tag'))->toBeFalse()->and($response->getContent())->not->toContain('onhost-concept-banner');
});
