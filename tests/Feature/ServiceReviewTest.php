<?php

declare(strict_types=1);

use App\Domains\Provisioning\Models\Service;
use App\Models\ServiceReview;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;

/**
 * Service reviews module — finishes the declared "Recenze" admin placeholder.
 * A customer reviews a service they own; nothing is visible until an admin
 * approves it.
 */
beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class]);
});

function reviewableService(?\App\Models\User $user = null): Service
{
    $user ??= customerUser();

    return Service::factory()->create(['customer_id' => $user->customer->id]);
}

// ── customer submits ─────────────────────────────────────────────────────────────

it('lets a customer review a service they own — starting pending', function (): void {
    $user    = customerUser();
    $service = reviewableService($user);

    $this->actingAs($user)
        ->post(route('panel.services.review', $service), [
            'rating' => 5, 'title' => 'Skvělé', 'body' => 'Rychlé a bez výpadků.',
        ])
        ->assertRedirect();

    $review = ServiceReview::firstOrFail();
    expect($review->rating)->toBe(5)
        ->and($review->customer_id)->toBe($user->customer->id)
        ->and($review->product_id)->toBe($service->product_id)
        // Never visible until moderated.
        ->and($review->status)->toBe(ServiceReview::STATUS_PENDING);
});

it('resubmitting edits the existing review and returns it to pending', function (): void {
    $user    = customerUser();
    $service = reviewableService($user);

    $this->actingAs($user)->post(route('panel.services.review', $service), ['rating' => 4, 'body' => 'Dobré'])->assertRedirect();
    // Approve it, then the customer edits — it must drop back to pending.
    $review = ServiceReview::firstOrFail();
    $review->update(['status' => ServiceReview::STATUS_APPROVED, 'moderated_by' => adminUser()->id]);

    $this->actingAs($user)->post(route('panel.services.review', $service), ['rating' => 2, 'body' => 'Zhoršilo se to'])->assertRedirect();

    // Still one review (edited, not duplicated), back in the queue.
    expect(ServiceReview::count())->toBe(1);
    expect($review->fresh())->rating->toBe(2)->status->toBe(ServiceReview::STATUS_PENDING)
        ->and($review->fresh()->moderated_by)->toBeNull();
});

it('validates the rating range and requires a body', function (): void {
    $user    = customerUser();
    $service = reviewableService($user);

    $this->actingAs($user)
        ->from(route('panel.services.show', $service))
        ->post(route('panel.services.review', $service), ['rating' => 9, 'body' => ''])
        ->assertSessionHasErrors(['rating', 'body']);
});

it('forbids reviewing someone else’s service', function (): void {
    $mine   = reviewableService(customerUser());

    $this->actingAs(customerUser())
        ->post(route('panel.services.review', $mine), ['rating' => 1, 'body' => 'Sabotáž'])
        ->assertForbidden();

    expect(ServiceReview::count())->toBe(0);
});

it('shows the review form on the service detail', function (): void {
    $user    = customerUser();
    $service = reviewableService($user);

    $this->actingAs($user)
        ->get(route('panel.services.show', $service))
        ->assertOk()
        ->assertSee('Ohodnotit službu');
});

// ── admin moderation ─────────────────────────────────────────────────────────────

it('shows the moderation queue instead of the old placeholder', function (): void {
    $service = reviewableService();
    ServiceReview::create([
        'customer_id' => $service->customer_id, 'service_id' => $service->id,
        'product_id' => $service->product_id, 'rating' => 5, 'body' => 'Paráda',
    ]);

    $this->actingAs(adminUser())
        ->get(route('admin.reviews'))
        ->assertOk()
        ->assertViewIs('admin.reviews')
        ->assertSee('Paráda')
        ->assertDontSee('bude implementován v dalším vydání');
});

it('lets an admin approve a review', function (): void {
    $service = reviewableService();
    $review  = ServiceReview::create([
        'customer_id' => $service->customer_id, 'service_id' => $service->id,
        'product_id' => $service->product_id, 'rating' => 5, 'body' => 'x',
    ]);
    $admin = adminUser();

    $this->actingAs($admin)->post(route('admin.reviews.approve', $review))->assertRedirect();

    expect($review->fresh())->status->toBe(ServiceReview::STATUS_APPROVED)
        ->and($review->fresh()->moderated_by)->toBe($admin->id);
    $this->assertDatabaseHas('activity_log', ['description' => 'review.moderated']);
});

it('lets an admin reject a review', function (): void {
    $service = reviewableService();
    $review  = ServiceReview::create([
        'customer_id' => $service->customer_id, 'service_id' => $service->id,
        'product_id' => $service->product_id, 'rating' => 1, 'body' => 'spam spam',
    ]);

    $this->actingAs(adminUser())->post(route('admin.reviews.reject', $review))->assertRedirect();

    expect($review->fresh()->status)->toBe(ServiceReview::STATUS_REJECTED);
});

it('forbids a customer from reaching the moderation queue', function (): void {
    $this->actingAs(customerUser())
        ->get(route('admin.reviews'))
        ->assertForbidden();
});

it('the approved scope returns only approved reviews', function (): void {
    $service = reviewableService();
    ServiceReview::create(['customer_id' => $service->customer_id, 'service_id' => $service->id, 'rating' => 5, 'body' => 'a', 'status' => 'approved']);
    ServiceReview::create(['customer_id' => $service->customer_id, 'service_id' => null, 'rating' => 1, 'body' => 'b', 'status' => 'pending']);

    expect(ServiceReview::approved()->count())->toBe(1);
});
