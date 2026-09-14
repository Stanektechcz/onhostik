<?php

declare(strict_types=1);

it('serves the provider onboarding page to staff only and links it from the console user menu', function () {
    $this->get('/sprava/nastaveni/integrace')->assertRedirect('/prihlaseni?next=%2Fsprava%2Fnastaveni%2Fintegrace');

    [$customer] = $this->customerWithOrganization();
    $this->actingAs($customer);
    $this->get('/sprava/nastaveni/integrace')->assertRedirect('/panel');

    $staff = $this->staff('sre');
    $this->actingAs($staff);
    $page = $this->get('/sprava/nastaveni/integrace')->assertOk()->getContent();
    expect($page)->toContain('Integrace providerů')->toContain('/v1')->toContain("'/staff/integrations'")->toContain('/staff/integrations/schema')
        ->toContain('step_up_required')->toContain('tls_ca')->toContain('<meta name="csrf-token"')->toContain('href="/surfaces/_ds/')->toContain($staff->email)
        ->toContain('Umístění tarifů')->toContain("'/staff/placements'")->toContain('Registrátoři domén')->toContain("'/staff/registrars'");
    $this->get('/sprava/nastaveni')->assertRedirect('/sprava/nastaveni/integrace');

    // the console's user menu (a prototype literal) gets the settings entry outside demo mode
    expect($this->get('/sprava')->assertOk()->getContent())->toContain("location.href = '/sprava/nastaveni/integrace';");
});
