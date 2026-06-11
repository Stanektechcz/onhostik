<?php

declare(strict_types=1);

use Database\Seeders\ProductCatalogSeeder;

it('shows the five seeded webhosting plans on the public pricing page', function (): void {
    $this->seed(ProductCatalogSeeder::class);

    $this->get('/webhosting')
        ->assertOk()
        ->assertSee('Start')
        ->assertSee('Business')
        ->assertSee('Pro')
        ->assertSee('AI Hosting')
        ->assertSee('Managed WordPress');
});

it('shows the plan selection in the panel checkout', function (): void {
    $this->seed(ProductCatalogSeeder::class);

    $this->actingAs(customerUser())
        ->get('/panel/objednavky/nova')
        ->assertOk()
        ->assertSee('Business')
        ->assertSee('Managed WordPress');
});

it('checks domain availability via the mock WEDOS registrar', function (): void {
    $this->post('/domeny/overit', ['domain' => 'volna-domena.cz'])
        ->assertRedirect()
        ->assertSessionHas('domain_check_result', fn (array $result): bool => $result['available'] === true);

    $this->post('/domeny/overit', ['domain' => 'taken-domena.cz'])
        ->assertRedirect()
        ->assertSessionHas('domain_check_result', fn (array $result): bool => $result['available'] === false);
});
