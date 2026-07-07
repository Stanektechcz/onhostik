<?php

declare(strict_types=1);

use App\Domains\Customer\Models\Customer;
use App\Domains\Customer\Models\CustomerTag;
use Database\Seeders\IntegrationSeeder;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;

beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class, IntegrationSeeder::class]);
});

// ── CustomerTag model ─────────────────────────────────────────────────────────

it('CustomerTag colorBadgeStyle returns inline style', function (): void {
    $tag = new CustomerTag(['color' => '#dc3545']);
    expect($tag->colorBadgeStyle())->toContain('#dc3545');
});

it('CustomerTag colorBadgeStyle falls back to default color', function (): void {
    $tag = new CustomerTag(['color' => null]);
    expect($tag->colorBadgeStyle())->toContain('#6c757d');
});

// ── Customer tags relation ────────────────────────────────────────────────────

it('customer can have tags attached', function (): void {
    $customer = customerUser()->customer;
    $tag = CustomerTag::create([
        'name'  => 'VIP',
        'slug'  => 'vip',
        'color' => '#ffc107',
    ]);

    $customer->tags()->attach($tag->id);

    expect($customer->tags()->count())->toBe(1)
        ->and($customer->tags->first()->name)->toBe('VIP');
});

it('customer can have multiple tags', function (): void {
    $customer = customerUser()->customer;

    $vip = CustomerTag::create(['name' => 'VIP', 'slug' => 'vip', 'color' => '#ffc107']);
    $ent = CustomerTag::create(['name' => 'Enterprise', 'slug' => 'enterprise', 'color' => '#0d6efd']);

    $customer->tags()->attach([$vip->id, $ent->id]);

    expect($customer->tags()->count())->toBe(2);
});

it('tag has customers relation', function (): void {
    $customer = customerUser()->customer;
    $tag = CustomerTag::create(['name' => 'At-Risk', 'slug' => 'at-risk', 'color' => '#dc3545']);
    $customer->tags()->attach($tag->id);

    expect($tag->customers()->count())->toBe(1);
});

// ── Admin index ───────────────────────────────────────────────────────────────

it('admin can view customer tags page', function (): void {
    $admin = adminUser();

    CustomerTag::create(['name' => 'Premium', 'slug' => 'premium', 'color' => '#6f42c1']);

    $this->actingAs($admin)
        ->get(route('admin.customer-tags.index'))
        ->assertOk()
        ->assertSee('Premium');
});

it('admin can filter customers by tag', function (): void {
    $admin    = adminUser();
    $customer = customerUser()->customer;
    $tag      = CustomerTag::create(['name' => 'VIP', 'slug' => 'vip', 'color' => '#ffc107']);

    $customer->tags()->attach($tag->id, ['assigned_by' => $admin->id]);

    $this->actingAs($admin)
        ->get(route('admin.customer-tags.index', ['tag' => $tag->id]))
        ->assertOk()
        ->assertSee('VIP');
});

it('non-admin cannot access customer tags', function (): void {
    $user = customerUser();

    $this->actingAs($user)
        ->get(route('admin.customer-tags.index'))
        ->assertStatus(403);
});

// ── Admin CRUD ─────────────────────────────────────────────────────────────────

it('admin can create a tag', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
        ->post(route('admin.customer-tags.store'), [
            'name'        => 'Enterprise',
            'color'       => '#0d6efd',
            'description' => 'Large enterprise customers',
        ])
        ->assertRedirect(route('admin.customer-tags.index'));

    expect(CustomerTag::where('name', 'Enterprise')->exists())->toBeTrue();
});

it('slug is auto-generated on create', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
        ->post(route('admin.customer-tags.store'), [
            'name'  => 'At Risk',
            'color' => '#dc3545',
        ])
        ->assertRedirect();

    $tag = CustomerTag::where('name', 'At Risk')->first();
    expect($tag)->not->toBeNull()
        ->and($tag->slug)->toBe('at-risk');
});

it('tag name must be unique', function (): void {
    $admin = adminUser();
    CustomerTag::create(['name' => 'VIP', 'slug' => 'vip', 'color' => '#ffc107']);

    $this->actingAs($admin)
        ->post(route('admin.customer-tags.store'), [
            'name'  => 'VIP',
            'color' => '#ffc107',
        ])
        ->assertSessionHasErrors('name');
});

it('admin can update a tag', function (): void {
    $admin = adminUser();
    $tag   = CustomerTag::create(['name' => 'Old Name', 'slug' => 'old-name', 'color' => '#6c757d']);

    $this->actingAs($admin)
        ->put(route('admin.customer-tags.update', $tag), [
            'name'  => 'New Name',
            'color' => '#198754',
        ])
        ->assertRedirect(route('admin.customer-tags.index'));

    expect($tag->fresh()->name)->toBe('New Name')
        ->and($tag->fresh()->slug)->toBe('new-name')
        ->and($tag->fresh()->color)->toBe('#198754');
});

it('admin can delete a tag', function (): void {
    $admin = adminUser();
    $tag   = CustomerTag::create(['name' => 'Temporary', 'slug' => 'temporary', 'color' => '#6c757d']);

    $this->actingAs($admin)
        ->delete(route('admin.customer-tags.destroy', $tag))
        ->assertRedirect(route('admin.customer-tags.index'));

    expect(CustomerTag::find($tag->id))->toBeNull();
});

it('deleting a tag removes pivot rows', function (): void {
    $admin    = adminUser();
    $customer = customerUser()->customer;
    $tag      = CustomerTag::create(['name' => 'To Delete', 'slug' => 'to-delete', 'color' => '#6c757d']);

    $customer->tags()->attach($tag->id);

    $this->actingAs($admin)
        ->delete(route('admin.customer-tags.destroy', $tag))
        ->assertRedirect();

    expect(CustomerTag::find($tag->id))->toBeNull();
    expect($customer->fresh()->tags()->count())->toBe(0);
});

// ── Assign / detach ────────────────────────────────────────────────────────────

it('admin can assign a tag to a customer', function (): void {
    $admin    = adminUser();
    $customer = customerUser()->customer;
    $tag      = CustomerTag::create(['name' => 'VIP', 'slug' => 'vip', 'color' => '#ffc107']);

    $this->actingAs($admin)
        ->post(route('admin.customers.tags.assign', $customer), ['tag_id' => $tag->id])
        ->assertRedirect();

    expect($customer->fresh()->tags()->where('customer_tags.id', $tag->id)->exists())->toBeTrue();
});

it('assigning same tag twice does not create duplicate', function (): void {
    $admin    = adminUser();
    $customer = customerUser()->customer;
    $tag      = CustomerTag::create(['name' => 'VIP', 'slug' => 'vip', 'color' => '#ffc107']);

    $this->actingAs($admin)
        ->post(route('admin.customers.tags.assign', $customer), ['tag_id' => $tag->id]);

    $this->actingAs($admin)
        ->post(route('admin.customers.tags.assign', $customer), ['tag_id' => $tag->id]);

    expect($customer->fresh()->tags()->count())->toBe(1);
});

it('admin can detach a tag from a customer', function (): void {
    $admin    = adminUser();
    $customer = customerUser()->customer;
    $tag      = CustomerTag::create(['name' => 'VIP', 'slug' => 'vip', 'color' => '#ffc107']);

    $customer->tags()->attach($tag->id);

    $this->actingAs($admin)
        ->delete(route('admin.customer-tags.detach', [$customer, $tag]))
        ->assertRedirect();

    expect($customer->fresh()->tags()->count())->toBe(0);
});

it('assign syncs customer_count on tag', function (): void {
    $admin    = adminUser();
    $customer = customerUser()->customer;
    $tag      = CustomerTag::create(['name' => 'VIP', 'slug' => 'vip', 'color' => '#ffc107', 'customer_count' => 0]);

    $this->actingAs($admin)
        ->post(route('admin.customers.tags.assign', $customer), ['tag_id' => $tag->id]);

    expect($tag->fresh()->customer_count)->toBe(1);
});

it('detach syncs customer_count on tag', function (): void {
    $admin    = adminUser();
    $customer = customerUser()->customer;
    $tag      = CustomerTag::create(['name' => 'VIP', 'slug' => 'vip', 'color' => '#ffc107', 'customer_count' => 0]);

    $customer->tags()->attach($tag->id);

    $this->actingAs($admin)
        ->delete(route('admin.customer-tags.detach', [$customer, $tag]))
        ->assertRedirect();

    expect($tag->fresh()->customer_count)->toBe(0);
});

it('assign requires valid tag_id', function (): void {
    $admin    = adminUser();
    $customer = customerUser()->customer;

    $this->actingAs($admin)
        ->post(route('admin.customers.tags.assign', $customer), ['tag_id' => 99999])
        ->assertSessionHasErrors('tag_id');
});
