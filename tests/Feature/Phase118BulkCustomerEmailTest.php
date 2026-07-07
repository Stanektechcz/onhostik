<?php

declare(strict_types=1);

use App\Domains\Customer\Models\Customer;
use App\Domains\Customer\Models\CustomerTag;
use App\Jobs\SendBulkCustomerEmailJob;
use App\Models\BulkCustomerEmail;
use Database\Factories\InvoiceFactory;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class]);
});

// ── Index ─────────────────────────────────────────────────────────────────────

it('admin can view bulk email index', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
         ->get(route('admin.bulk-email.index'))
         ->assertOk()
         ->assertSee('Hromadný e-mail');
});

it('customer cannot access bulk email index', function (): void {
    $user = customerUser();

    $this->actingAs($user)
         ->get(route('admin.bulk-email.index'))
         ->assertForbidden();
});

// ── Store (draft) ─────────────────────────────────────────────────────────────

it('admin can create a bulk email campaign as draft', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
         ->post(route('admin.bulk-email.store'), [
             'subject'   => 'Testovací hromadný e-mail',
             'body_html' => '<p>Ahoj zákazníku!</p>',
         ])
         ->assertRedirect()
         ->assertSessionHas('status');

    $campaign = BulkCustomerEmail::query()->where('subject', 'Testovací hromadný e-mail')->first();
    expect($campaign)->not->toBeNull()
        ->and($campaign->status)->toBe('draft')
        ->and($campaign->created_by)->toBe($admin->id);
});

it('bulk email store validates required fields', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
         ->post(route('admin.bulk-email.store'), [])
         ->assertSessionHasErrors(['subject', 'body_html']);
});

it('bulk email stores filters correctly', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
         ->post(route('admin.bulk-email.store'), [
             'subject'             => 'VIP email',
             'body_html'           => '<p>Ahoj!</p>',
             'filter_segment'      => 'vip',
             'filter_country_code' => 'CZ',
         ])
         ->assertRedirect();

    $campaign = BulkCustomerEmail::query()->where('subject', 'VIP email')->first();
    expect($campaign->filters)->toBe(['segment' => 'vip', 'country_code' => 'CZ']);
});

// ── Preview count ─────────────────────────────────────────────────────────────

it('preview count returns json with recipient count', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
         ->getJson(route('admin.bulk-email.preview-count'))
         ->assertOk()
         ->assertJsonStructure(['count']);
});

it('preview count filters by segment', function (): void {
    $admin = adminUser();
    $user  = customerUser();

    // Update customer to VIP
    $user->customer->update(['segment' => 'vip', 'email' => 'vip@test.com']);

    $responseAll = $this->actingAs($admin)
         ->getJson(route('admin.bulk-email.preview-count'))
         ->assertOk()
         ->json('count');

    $responseVip = $this->actingAs($admin)
         ->getJson(route('admin.bulk-email.preview-count', ['filter_segment' => 'vip']))
         ->assertOk()
         ->json('count');

    expect($responseVip)->toBeLessThanOrEqual($responseAll)
        ->and($responseVip)->toBeGreaterThanOrEqual(1);
});

// ── Send ──────────────────────────────────────────────────────────────────────

it('admin can send a draft bulk email campaign', function (): void {
    Queue::fake();

    $admin = adminUser();
    $user  = customerUser();
    $user->customer->update(['email' => 'customer@example.com']);

    $campaign = BulkCustomerEmail::create([
        'created_by'  => $admin->id,
        'subject'     => 'Test send',
        'body_html'   => '<p>Obsah</p>',
        'filters'     => [],
        'status'      => 'draft',
    ]);

    $this->actingAs($admin)
         ->post(route('admin.bulk-email.send', $campaign))
         ->assertRedirect()
         ->assertSessionHas('status');

    $campaign->refresh();
    expect($campaign->status)->toBe('sending')
        ->and($campaign->recipients_count)->toBeGreaterThan(0);

    Queue::assertPushed(SendBulkCustomerEmailJob::class);
});

it('cannot send an already-sent campaign', function (): void {
    $admin = adminUser();

    $campaign = BulkCustomerEmail::create([
        'created_by' => $admin->id,
        'subject'    => 'Already sent',
        'body_html'  => '<p>X</p>',
        'filters'    => [],
        'status'     => 'sent',
    ]);

    $this->actingAs($admin)
         ->post(route('admin.bulk-email.send', $campaign))
         ->assertForbidden();
});

// ── Tag filter ────────────────────────────────────────────────────────────────

it('filter query respects tag filter', function (): void {
    $admin = adminUser();
    $user  = customerUser();

    $tag = CustomerTag::create(['name' => 'TestTag', 'slug' => 'testtag', 'color' => '#ff0000']);
    $user->customer->tags()->attach($tag->id, ['assigned_by' => $admin->id]);

    $countWithTag = BulkCustomerEmail::buildFilterQuery(['tag_id' => $tag->id])->count();
    $countAll     = BulkCustomerEmail::buildFilterQuery([])->count();

    expect($countWithTag)->toBeGreaterThanOrEqual(1)
        ->and($countWithTag)->toBeLessThanOrEqual($countAll);
});

// ── Destroy ───────────────────────────────────────────────────────────────────

it('admin can delete a draft campaign', function (): void {
    $admin = adminUser();

    $campaign = BulkCustomerEmail::create([
        'created_by' => $admin->id,
        'subject'    => 'Draft to delete',
        'body_html'  => '<p>X</p>',
        'filters'    => [],
        'status'     => 'draft',
    ]);

    $this->actingAs($admin)
         ->delete(route('admin.bulk-email.destroy', $campaign))
         ->assertRedirect()
         ->assertSessionHas('status');

    expect(BulkCustomerEmail::find($campaign->id))->toBeNull();
});

it('cannot delete a sent campaign', function (): void {
    $admin = adminUser();

    $campaign = BulkCustomerEmail::create([
        'created_by' => $admin->id,
        'subject'    => 'Sent - no delete',
        'body_html'  => '<p>X</p>',
        'filters'    => [],
        'status'     => 'sent',
    ]);

    $this->actingAs($admin)
         ->delete(route('admin.bulk-email.destroy', $campaign))
         ->assertForbidden();
});
