<?php

use App\Models\ExportTemplate;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('admin can view export templates', function (): void {
    $this->actingAs(adminUser())
        ->get(route('admin.export-templates.index'))
        ->assertOk()
        ->assertViewHas('templates');
});

it('admin can create an export template', function (): void {
    $this->actingAs(adminUser())
        ->post(route('admin.export-templates.store'), [
            'name'        => 'Customer export',
            'entity_type' => 'customers',
            'columns_raw' => 'id,email,created_at',
            'format'      => 'csv',
            'is_shared'   => false,
        ])
        ->assertRedirect();

    expect(ExportTemplate::where('name', 'Customer export')->exists())->toBeTrue();
    $tpl = ExportTemplate::where('name', 'Customer export')->first();
    expect($tpl->columns)->toBe(['id', 'email', 'created_at']);
});

it('format must be one of csv xlsx json', function (): void {
    $this->actingAs(adminUser())
        ->post(route('admin.export-templates.store'), [
            'name'        => 'Bad',
            'entity_type' => 'customers',
            'columns_raw' => 'id',
            'format'      => 'xml',
        ])
        ->assertSessionHasErrors('format');
});

it('columns are required when creating a template', function (): void {
    $this->actingAs(adminUser())
        ->post(route('admin.export-templates.store'), [
            'name'        => 'Empty columns',
            'entity_type' => 'customers',
            'columns_raw' => '',
            'format'      => 'csv',
        ])
        ->assertSessionHasErrors('columns');
});

it('admin can delete an export template', function (): void {
    $tpl = ExportTemplate::create([
        'name'        => 'Delete me',
        'entity_type' => 'invoices',
        'columns'     => ['id', 'total'],
        'format'      => 'csv',
        'created_by'  => adminUser()->id,
        'is_shared'   => false,
    ]);

    $this->actingAs(adminUser())
        ->delete(route('admin.export-templates.destroy', $tpl))
        ->assertRedirect();

    expect(ExportTemplate::find($tpl->id))->toBeNull();
});
