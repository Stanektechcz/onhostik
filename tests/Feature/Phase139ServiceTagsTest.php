<?php

declare(strict_types=1);

use App\Domains\Provisioning\Models\Service;
use App\Models\ServiceTag;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;

beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class]);
});

it('admin can view service tags index', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
         ->get(route('admin.services.tags.index'))
         ->assertOk()
         ->assertSee('Štítky služeb');
});

it('admin can create a service tag', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
         ->post(route('admin.services.tags.store'), [
             'name'  => 'Priority',
             'color' => '#ff0000',
         ])
         ->assertRedirect();

    expect(ServiceTag::query()->where('name', 'Priority')->exists())->toBeTrue();
});

it('tag name must be unique', function (): void {
    $admin = adminUser();
    ServiceTag::create(['name' => 'Duplicate', 'color' => '#000000']);

    $this->actingAs($admin)
         ->post(route('admin.services.tags.store'), [
             'name'  => 'Duplicate',
             'color' => '#111111',
         ])
         ->assertSessionHasErrors(['name']);
});

it('admin can assign tag to a service', function (): void {
    $admin    = adminUser();
    $customer = customerUser();
    $service  = Service::factory()->create(['customer_id' => $customer->customer->id]);
    $tag      = ServiceTag::create(['name' => 'VIP', 'color' => '#ffd700']);

    $this->actingAs($admin)
         ->post(route('admin.services.tags.assign', $service), [
             'tag_id' => $tag->id,
         ])
         ->assertRedirect();

    expect($service->tags()->where('service_tag_id', $tag->id)->exists())->toBeTrue();
});

it('admin can detach tag from a service', function (): void {
    $admin    = adminUser();
    $customer = customerUser();
    $service  = Service::factory()->create(['customer_id' => $customer->customer->id]);
    $tag      = ServiceTag::create(['name' => 'RemovableTag', 'color' => '#aabbcc']);
    $service->tags()->attach($tag->id);

    $this->actingAs($admin)
         ->delete(route('admin.services.tags.detach', [$service, $tag]))
         ->assertRedirect();

    expect($service->fresh()->tags()->where('service_tag_id', $tag->id)->exists())->toBeFalse();
});

it('admin can delete a tag', function (): void {
    $admin = adminUser();
    $tag   = ServiceTag::create(['name' => 'Deletable', 'color' => '#cccccc']);

    $this->actingAs($admin)
         ->delete(route('admin.services.tags.destroy', $tag))
         ->assertRedirect();

    expect(ServiceTag::find($tag->id))->toBeNull();
});
