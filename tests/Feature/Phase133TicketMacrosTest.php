<?php

declare(strict_types=1);

use App\Models\TicketMacro;

it('admin can view ticket macros index', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
         ->get(route('admin.support.macros.index'))
         ->assertOk()
         ->assertSee('Makra');
});

it('admin can create a new macro', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
         ->post(route('admin.support.macros.store'), [
             'title' => 'Vítejte',
             'body'  => 'Dobrý den, jak vám mohu pomoci?',
         ])
         ->assertRedirect();

    expect(TicketMacro::query()->where('title', 'Vítejte')->exists())->toBeTrue();
});

it('macro creation validates min body length', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
         ->post(route('admin.support.macros.store'), [
             'title' => 'Test',
             'body'  => 'Krátk',
         ])
         ->assertSessionHasNoErrors();

    // body min is 5, "Krátk" is 5 chars — should pass; test with 4 chars:
    $this->actingAs($admin)
         ->post(route('admin.support.macros.store'), [
             'title' => 'Test',
             'body'  => 'Krt',
         ])
         ->assertSessionHasErrors(['body']);
});

it('admin can update a macro', function (): void {
    $admin = adminUser();
    $macro = TicketMacro::create([
        'title'      => 'Původní název',
        'body'       => 'Původní text makra pro testování.',
        'created_by' => $admin->id,
    ]);

    $this->actingAs($admin)
         ->put(route('admin.support.macros.update', $macro), [
             'title' => 'Nový název',
             'body'  => 'Aktualizovaný text makra pro uložení.',
         ])
         ->assertRedirect();

    expect($macro->fresh()->title)->toBe('Nový název');
});

it('admin can delete a macro', function (): void {
    $admin = adminUser();
    $macro = TicketMacro::create([
        'title'      => 'Smazatelné makro',
        'body'       => 'Text makra pro smazání v testu.',
        'created_by' => $admin->id,
    ]);

    $this->actingAs($admin)
         ->delete(route('admin.support.macros.destroy', $macro))
         ->assertRedirect();

    expect(TicketMacro::find($macro->id))->toBeNull();
});

it('customer cannot access ticket macros', function (): void {
    adminUser();
    $user = customerUser();

    $this->actingAs($user)
         ->get(route('admin.support.macros.index'))
         ->assertForbidden();
});
