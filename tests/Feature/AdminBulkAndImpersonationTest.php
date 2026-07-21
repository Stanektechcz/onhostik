<?php

declare(strict_types=1);

use App\Domains\Customer\Models\Customer;
use App\Http\Controllers\Admin\ImpersonateController;
use App\Models\CustomerSegmentTag;
use Database\Seeders\IntegrationSeeder;
use Database\Seeders\ProductCatalogSeeder;

/**
 * Bulk customer actions (audit G99) and time-boxed impersonation (G98).
 */
beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, IntegrationSeeder::class]);
});

// ── G99: bulk actions ─────────────────────────────────────────────────────────

it('deactivates several customers at once', function (): void {
    $a = customerUser();
    $b = customerUser();

    $this->actingAs(adminUser())
        ->post(route('admin.customers.bulk-action'), [
            'action'       => 'deactivate',
            'customer_ids' => [$a->customer->id, $b->customer->id],
        ])
        ->assertRedirect()
        ->assertSessionHas('status');

    expect((bool) $a->fresh()->is_active)->toBeFalse()
        ->and((bool) $b->fresh()->is_active)->toBeFalse();
});

it('reactivates several customers at once', function (): void {
    $user = customerUser();
    $user->update(['is_active' => false]);

    $this->actingAs(adminUser())
        ->post(route('admin.customers.bulk-action'), [
            'action'       => 'activate',
            'customer_ids' => [$user->customer->id],
        ])
        ->assertRedirect();

    expect((bool) $user->fresh()->is_active)->toBeTrue();
});

it('never deactivates an administrator in a bulk sweep', function (): void {
    $admin = adminUser();
    Customer::factory()->for($admin)->create();

    $this->actingAs(adminUser())
        ->post(route('admin.customers.bulk-action'), [
            'action'       => 'deactivate',
            'customer_ids' => [$admin->customer->id],
        ])
        ->assertRedirect();

    // A sweep must never be able to lock the operators out.
    expect((bool) $admin->fresh()->is_active)->toBeTrue();
});

it('tags and untags customers in bulk', function (): void {
    $user = customerUser();
    $tag  = CustomerSegmentTag::create(['name' => 'VIP', 'color' => 'primary']);

    $this->actingAs(adminUser())
        ->post(route('admin.customers.bulk-action'), [
            'action' => 'tag', 'customer_ids' => [$user->customer->id], 'tag_id' => $tag->id,
        ])
        ->assertRedirect();
    expect($user->customer->fresh()->segmentTags)->toHaveCount(1);

    $this->actingAs(adminUser())
        ->post(route('admin.customers.bulk-action'), [
            'action' => 'untag', 'customer_ids' => [$user->customer->id], 'tag_id' => $tag->id,
        ])
        ->assertRedirect();
    expect($user->customer->fresh()->segmentTags)->toHaveCount(0);
});

it('requires a tag when tagging in bulk', function (): void {
    $user = customerUser();

    $this->actingAs(adminUser())
        ->from(route('admin.customers.index'))
        ->post(route('admin.customers.bulk-action'), [
            'action' => 'tag', 'customer_ids' => [$user->customer->id],
        ])
        ->assertSessionHasErrors('tag_id');
});

it('rejects an unknown bulk action', function (): void {
    $user = customerUser();

    $this->actingAs(adminUser())
        ->from(route('admin.customers.index'))
        ->post(route('admin.customers.bulk-action'), [
            'action' => 'delete_everything', 'customer_ids' => [$user->customer->id],
        ])
        ->assertSessionHasErrors('action');
});

it('records bulk actions in the audit log', function (): void {
    $user = customerUser();

    $this->actingAs(adminUser())
        ->post(route('admin.customers.bulk-action'), [
            'action' => 'deactivate', 'customer_ids' => [$user->customer->id],
        ]);

    expect(\Spatie\Activitylog\Models\Activity::where('description', 'customer.bulk_action')->exists())->toBeTrue();
});

it('forbids a customer from running bulk actions', function (): void {
    $victim = customerUser();

    $this->actingAs(customerUser())
        ->post(route('admin.customers.bulk-action'), [
            'action' => 'deactivate', 'customer_ids' => [$victim->customer->id],
        ])
        ->assertForbidden();

    expect((bool) $victim->fresh()->is_active)->toBeTrue();
});

// ── G98: impersonation is time-boxed ──────────────────────────────────────────

it('lets an admin impersonate a customer', function (): void {
    $target = customerUser();

    $this->actingAs(adminUser())
        ->get(route('admin.impersonate.start', $target))
        ->assertRedirect(route('panel.dashboard'));

    expect(session('_impersonated_by'))->not->toBeNull()
        ->and(session('_impersonation_expires_at'))->not->toBeNull();
});

it('ends the impersonation automatically once it expires', function (): void {
    $admin  = adminUser();
    $target = customerUser();

    $this->actingAs($admin)->get(route('admin.impersonate.start', $target));

    // Wind the clock past the time box.
    session(['_impersonation_expires_at' => now()->subMinute()->timestamp]);

    $this->get(route('panel.dashboard'))->assertRedirect(route('admin.customers.index'));

    expect(session('_impersonated_by'))->toBeNull()
        ->and(auth()->id())->toBe($admin->id); // handed back to the admin, not logged out
});

it('keeps an unexpired impersonation running', function (): void {
    $target = customerUser();

    $this->actingAs(adminUser())->get(route('admin.impersonate.start', $target));

    $this->get(route('panel.dashboard'))->assertOk();

    expect(session('_impersonated_by'))->not->toBeNull();
});

it('refuses to impersonate another admin', function (): void {
    $otherAdmin = adminUser();

    $this->actingAs(adminUser())
        ->from(route('admin.customers.index'))
        ->get(route('admin.impersonate.start', $otherAdmin))
        ->assertSessionHasErrors('impersonate');
});

it('has a sane impersonation time box', function (): void {
    expect(ImpersonateController::MAX_MINUTES)->toBeGreaterThan(0)->toBeLessThanOrEqual(120);
});
