<?php

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('admin can view saved search filters', function (): void {
    $this->actingAs(adminUser())
        ->get(route('admin.saved-search-filters.index'))
        ->assertOk()
        ->assertViewHas('filters');
});

it('admin can create a saved search filter', function (): void {
    $this->actingAs(adminUser())
        ->post(route('admin.saved-search-filters.store'), [
            'name'        => 'Active Customers',
            'context'     => 'customers',
            'filters_raw' => '{"status":"active"}',
            'is_default'  => 0,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('saved_search_filters', ['name' => 'Active Customers']);
});

it('admin can delete a saved filter', function (): void {
    $admin  = adminUser();
    $filter = \App\Models\SavedSearchFilter::create([
        'user_id'    => $admin->id,
        'name'       => 'ToDelete',
        'context'    => 'customers',
        'filters'    => [],
        'is_default' => false,
    ]);

    $this->actingAs($admin)
        ->delete(route('admin.saved-search-filters.destroy', $filter))
        ->assertRedirect();

    $this->assertDatabaseMissing('saved_search_filters', ['id' => $filter->id]);
});

it('guest cannot access saved search filters', function (): void {
    $this->get(route('admin.saved-search-filters.index'))
        ->assertRedirect();
});

it('admin cannot delete another admin filter', function (): void {
    $adminA = adminUser();
    $adminB = adminUser();

    $filter = \App\Models\SavedSearchFilter::create([
        'user_id'    => $adminB->id,
        'name'       => 'BFilter',
        'context'    => 'customers',
        'filters'    => [],
        'is_default' => false,
    ]);

    $this->actingAs($adminA)
        ->delete(route('admin.saved-search-filters.destroy', $filter))
        ->assertForbidden();
});
