<?php

declare(strict_types=1);

use App\Domains\Customer\Models\Customer;
use App\Mail\DripStepMail;
use App\Models\EmailDripEnrollment;
use App\Models\EmailDripSequence;
use App\Models\EmailDripStep;
use App\Models\NewsletterCampaign;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class]);
});

// ── EmailDripSequence model ───────────────────────────────────────────────────

it('EmailDripSequence triggerLabel returns correct labels', function (): void {
    $seq = new EmailDripSequence(['trigger_event' => 'manual', 'is_active' => false]);
    expect($seq->triggerLabel())->toBe('Manuální');

    $seq->trigger_event = 'signup';
    expect($seq->triggerLabel())->toBe('Registrace zákazníka');

    $seq->trigger_event = 'service_created';
    expect($seq->triggerLabel())->toBe('Vytvoření služby');
});

it('EmailDripEnrollment isActive returns true when not completed or unsubscribed', function (): void {
    $enrollment = new EmailDripEnrollment([
        'email' => 'test@example.com',
        'completed_at' => null,
        'unsubscribed_at' => null,
    ]);
    expect($enrollment->isActive())->toBeTrue();
});

it('EmailDripEnrollment isActive returns false when completed', function (): void {
    $enrollment = new EmailDripEnrollment([
        'email' => 'test@example.com',
        'completed_at' => now(),
        'unsubscribed_at' => null,
    ]);
    expect($enrollment->isActive())->toBeFalse();
});

// ── Admin drip index page ─────────────────────────────────────────────────────

it('admin can view drip sequences index page', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
        ->get(route('admin.drip.index'))
        ->assertOk()
        ->assertViewIs('admin.drip.index')
        ->assertViewHas('sequences');
});

it('admin drip index page shows existing sequences', function (): void {
    $admin = adminUser();

    EmailDripSequence::create([
        'name'          => 'Onboarding sekvence',
        'trigger_event' => 'signup',
        'is_active'     => true,
        'created_by'    => $admin->id,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.drip.index'))
        ->assertOk()
        ->assertSee('Onboarding sekvence');
});

it('customer cannot access drip sequences index', function (): void {
    $user = customerUser();

    $this->actingAs($user)
        ->get(route('admin.drip.index'))
        ->assertForbidden();
});

// ── Admin drip create ─────────────────────────────────────────────────────────

it('admin can create a drip sequence', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
        ->post(route('admin.drip.store'), [
            'name'          => 'Welcome sekvence',
            'trigger_event' => 'signup',
            'description'   => 'Uvítací e-maily pro nové zákazníky.',
        ])
        ->assertRedirect();

    expect(EmailDripSequence::where('name', 'Welcome sekvence')->exists())->toBeTrue();
    expect(EmailDripSequence::first()->is_active)->toBeFalse();
});

it('drip sequence store validates required fields', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
        ->post(route('admin.drip.store'), [])
        ->assertSessionHasErrors(['name', 'trigger_event']);
});

it('drip sequence store rejects invalid trigger_event', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
        ->post(route('admin.drip.store'), [
            'name'          => 'Test',
            'trigger_event' => 'invalid_trigger',
        ])
        ->assertSessionHasErrors(['trigger_event']);
});

// ── Admin drip show ───────────────────────────────────────────────────────────

it('admin can view drip sequence show page', function (): void {
    $admin = adminUser();
    $drip  = EmailDripSequence::create([
        'name'          => 'Test drip',
        'trigger_event' => 'manual',
        'is_active'     => false,
        'created_by'    => $admin->id,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.drip.show', $drip))
        ->assertOk()
        ->assertViewIs('admin.drip.show')
        ->assertViewHas('drip')
        ->assertViewHas('activeEnrollments')
        ->assertViewHas('completedEnrollments');
});

// ── Admin drip toggle active ──────────────────────────────────────────────────

it('admin can toggle drip sequence active status', function (): void {
    $admin = adminUser();
    $drip  = EmailDripSequence::create([
        'name'          => 'Toggle test',
        'trigger_event' => 'manual',
        'is_active'     => false,
        'created_by'    => $admin->id,
    ]);

    $this->actingAs($admin)
        ->post(route('admin.drip.toggle', $drip))
        ->assertRedirect();

    expect($drip->fresh()->is_active)->toBeTrue();

    $this->actingAs($admin)
        ->post(route('admin.drip.toggle', $drip))
        ->assertRedirect();

    expect($drip->fresh()->is_active)->toBeFalse();
});

// ── Admin drip step CRUD ──────────────────────────────────────────────────────

it('admin can add a step to a drip sequence', function (): void {
    $admin = adminUser();
    $drip  = EmailDripSequence::create([
        'name'          => 'Step test',
        'trigger_event' => 'manual',
        'is_active'     => true,
        'created_by'    => $admin->id,
    ]);

    $this->actingAs($admin)
        ->post(route('admin.drip.step.store', $drip), [
            'subject'    => 'Vítejte!',
            'body_html'  => '<p>Vítáme vás!</p>',
            'delay_days' => 0,
        ])
        ->assertRedirect();

    expect($drip->steps()->count())->toBe(1);
    expect($drip->steps()->first()->subject)->toBe('Vítejte!');
});

it('drip step sort_order increments correctly', function (): void {
    $admin = adminUser();
    $drip  = EmailDripSequence::create([
        'name'          => 'Sort test',
        'trigger_event' => 'manual',
        'is_active'     => true,
        'created_by'    => $admin->id,
    ]);

    $this->actingAs($admin)->post(route('admin.drip.step.store', $drip), [
        'subject' => 'Krok 1', 'body_html' => '<p>1</p>', 'delay_days' => 0,
    ]);
    $this->actingAs($admin)->post(route('admin.drip.step.store', $drip), [
        'subject' => 'Krok 2', 'body_html' => '<p>2</p>', 'delay_days' => 3,
    ]);

    $steps = $drip->steps()->orderBy('sort_order')->get();
    expect($steps[0]->subject)->toBe('Krok 1');
    expect($steps[1]->subject)->toBe('Krok 2');
    expect($steps[1]->sort_order)->toBe(2);
});

it('admin can delete a drip step', function (): void {
    $admin = adminUser();
    $drip  = EmailDripSequence::create([
        'name'          => 'Delete step test',
        'trigger_event' => 'manual',
        'is_active'     => true,
        'created_by'    => $admin->id,
    ]);

    $step = EmailDripStep::create([
        'drip_sequence_id' => $drip->id,
        'sort_order'       => 1,
        'subject'          => 'Smazat mě',
        'body_html'        => '<p>bye</p>',
        'delay_days'       => 0,
    ]);

    $this->actingAs($admin)
        ->delete(route('admin.drip.step.destroy', [$drip, $step]))
        ->assertRedirect();

    expect(EmailDripStep::find($step->id))->toBeNull();
});

it('cannot delete step from wrong sequence', function (): void {
    $admin  = adminUser();
    $drip1  = EmailDripSequence::create([
        'name' => 'Drip 1', 'trigger_event' => 'manual', 'is_active' => true, 'created_by' => $admin->id,
    ]);
    $drip2  = EmailDripSequence::create([
        'name' => 'Drip 2', 'trigger_event' => 'manual', 'is_active' => true, 'created_by' => $admin->id,
    ]);
    $step = EmailDripStep::create([
        'drip_sequence_id' => $drip2->id,
        'sort_order'       => 1,
        'subject'          => 'Step in drip2',
        'body_html'        => '<p>x</p>',
        'delay_days'       => 0,
    ]);

    $this->actingAs($admin)
        ->delete(route('admin.drip.step.destroy', [$drip1, $step]))
        ->assertNotFound();
});

// ── Enrollment ────────────────────────────────────────────────────────────────

it('admin can enroll a contact into a drip sequence', function (): void {
    $admin = adminUser();
    $drip  = EmailDripSequence::create([
        'name'          => 'Enroll test',
        'trigger_event' => 'manual',
        'is_active'     => true,
        'created_by'    => $admin->id,
    ]);

    EmailDripStep::create([
        'drip_sequence_id' => $drip->id,
        'sort_order'       => 1,
        'subject'          => 'Krok 1',
        'body_html'        => '<p>Hi</p>',
        'delay_days'       => 2,
    ]);

    $this->actingAs($admin)
        ->post(route('admin.drip.enroll', $drip), [
            'email' => 'jan@example.com',
            'name'  => 'Jan Novak',
        ])
        ->assertRedirect();

    $enrollment = EmailDripEnrollment::where('email', 'jan@example.com')->first();
    expect($enrollment)->not()->toBeNull();
    expect($enrollment->next_step_index)->toBe(0);
    expect($enrollment->next_send_at)->not()->toBeNull();
});

it('re-enrolling a contact resets completed_at', function (): void {
    $admin = adminUser();
    $drip  = EmailDripSequence::create([
        'name'          => 'Re-enroll test',
        'trigger_event' => 'manual',
        'is_active'     => true,
        'created_by'    => $admin->id,
    ]);

    EmailDripEnrollment::create([
        'drip_sequence_id' => $drip->id,
        'email'            => 'jan@example.com',
        'next_step_index'  => 3,
        'completed_at'     => now(),
    ]);

    $this->actingAs($admin)
        ->post(route('admin.drip.enroll', $drip), [
            'email' => 'jan@example.com',
        ])
        ->assertRedirect();

    $enrollment = EmailDripEnrollment::where('email', 'jan@example.com')->first();
    expect($enrollment->next_step_index)->toBe(0);
    expect($enrollment->completed_at)->toBeNull();
});

// ── Admin drip destroy ────────────────────────────────────────────────────────

it('admin can delete a drip sequence', function (): void {
    $admin = adminUser();
    $drip  = EmailDripSequence::create([
        'name'          => 'Delete me',
        'trigger_event' => 'manual',
        'is_active'     => false,
        'created_by'    => $admin->id,
    ]);

    $this->actingAs($admin)
        ->delete(route('admin.drip.destroy', $drip))
        ->assertRedirect(route('admin.drip.index'));

    expect(EmailDripSequence::find($drip->id))->toBeNull();
});

// ── ProcessDripSequencesCommand ───────────────────────────────────────────────

it('drip:process sends due email and advances step index', function (): void {
    Mail::fake();

    $admin = adminUser();
    $drip  = EmailDripSequence::create([
        'name'          => 'Process test',
        'trigger_event' => 'manual',
        'is_active'     => true,
        'created_by'    => $admin->id,
    ]);

    EmailDripStep::create([
        'drip_sequence_id' => $drip->id,
        'sort_order'       => 1,
        'subject'          => 'Krok 1',
        'body_html'        => '<p>Step 1</p>',
        'delay_days'       => 0,
    ]);
    EmailDripStep::create([
        'drip_sequence_id' => $drip->id,
        'sort_order'       => 2,
        'subject'          => 'Krok 2',
        'body_html'        => '<p>Step 2</p>',
        'delay_days'       => 3,
    ]);

    $enrollment = EmailDripEnrollment::create([
        'drip_sequence_id' => $drip->id,
        'email'            => 'test@example.com',
        'next_step_index'  => 0,
        'next_send_at'     => now()->subMinute(),
    ]);

    $this->artisan('drip:process')->assertExitCode(0);

    Mail::assertSent(DripStepMail::class, fn ($mail) => $mail->hasTo('test@example.com'));

    $enrollment->refresh();
    expect($enrollment->next_step_index)->toBe(1);
    expect($enrollment->completed_at)->toBeNull();
});

it('drip:process marks enrollment completed after last step', function (): void {
    Mail::fake();

    $admin = adminUser();
    $drip  = EmailDripSequence::create([
        'name'          => 'Complete test',
        'trigger_event' => 'manual',
        'is_active'     => true,
        'created_by'    => $admin->id,
    ]);

    EmailDripStep::create([
        'drip_sequence_id' => $drip->id,
        'sort_order'       => 1,
        'subject'          => 'Only step',
        'body_html'        => '<p>Last</p>',
        'delay_days'       => 0,
    ]);

    $enrollment = EmailDripEnrollment::create([
        'drip_sequence_id' => $drip->id,
        'email'            => 'finish@example.com',
        'next_step_index'  => 0,
        'next_send_at'     => now()->subMinute(),
    ]);

    $this->artisan('drip:process')->assertExitCode(0);

    $enrollment->refresh();
    expect($enrollment->completed_at)->not()->toBeNull();
    expect($enrollment->next_send_at)->toBeNull();
});

it('drip:process skips inactive sequences', function (): void {
    Mail::fake();

    $admin = adminUser();
    $drip  = EmailDripSequence::create([
        'name'          => 'Inactive drip',
        'trigger_event' => 'manual',
        'is_active'     => false,
        'created_by'    => $admin->id,
    ]);

    EmailDripStep::create([
        'drip_sequence_id' => $drip->id,
        'sort_order'       => 1,
        'subject'          => 'Neodeslaný',
        'body_html'        => '<p>Skip</p>',
        'delay_days'       => 0,
    ]);

    EmailDripEnrollment::create([
        'drip_sequence_id' => $drip->id,
        'email'            => 'skip@example.com',
        'next_step_index'  => 0,
        'next_send_at'     => now()->subMinute(),
    ]);

    $this->artisan('drip:process')->assertExitCode(0);

    Mail::assertNothingSent();
});

it('drip:process does not process future enrollments', function (): void {
    Mail::fake();

    $admin = adminUser();
    $drip  = EmailDripSequence::create([
        'name'          => 'Future drip',
        'trigger_event' => 'manual',
        'is_active'     => true,
        'created_by'    => $admin->id,
    ]);

    EmailDripStep::create([
        'drip_sequence_id' => $drip->id,
        'sort_order'       => 1,
        'subject'          => 'Future',
        'body_html'        => '<p>Not yet</p>',
        'delay_days'       => 1,
    ]);

    EmailDripEnrollment::create([
        'drip_sequence_id' => $drip->id,
        'email'            => 'future@example.com',
        'next_step_index'  => 0,
        'next_send_at'     => now()->addHours(2),
    ]);

    $this->artisan('drip:process')->assertExitCode(0);

    Mail::assertNothingSent();
});

// ── Newsletter campaign customer segment targeting ────────────────────────────

it('newsletter campaign can be created with customer audience', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
        ->post(route('admin.newsletter.store'), [
            'subject'         => 'VIP kampaň',
            'body_html'       => '<p>Exkluzivní nabídka</p>',
            'target_audience' => 'customers_vip',
        ])
        ->assertRedirect();

    $campaign = NewsletterCampaign::where('subject', 'VIP kampaň')->first();
    expect($campaign)->not()->toBeNull();
    expect($campaign->target_audience)->toBe('customers_vip');
});

it('newsletter campaign store rejects invalid target_audience', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
        ->post(route('admin.newsletter.store'), [
            'subject'         => 'Test',
            'body_html'       => '<p>Test</p>',
            'target_audience' => 'invalid_audience',
        ])
        ->assertSessionHasErrors(['target_audience']);
});

it('sending newsletter to customer segment dispatches jobs for matching customers', function (): void {
    Queue::fake();

    $admin = adminUser();

    $u1 = \App\Models\User::factory()->create();
    $u2 = \App\Models\User::factory()->create();

    Customer::create([
        'user_id'      => $u1->id,
        'company_name' => 'VIP Firma',
        'email'        => 'vip@example.com',
        'segment'      => 'vip',
    ]);
    Customer::create([
        'user_id'      => $u2->id,
        'company_name' => 'Normalni Firma',
        'email'        => 'normal@example.com',
        'segment'      => 'healthy',
    ]);

    $campaign = NewsletterCampaign::create([
        'subject'         => 'VIP Only',
        'body_html'       => '<p>Pro VIP</p>',
        'status'          => 'draft',
        'target_audience' => 'customers_vip',
        'created_by'      => $admin->id,
    ]);

    $this->actingAs($admin)
        ->post(route('admin.newsletter.send', $campaign))
        ->assertRedirect();

    Queue::assertPushed(\App\Jobs\SendCampaignToCustomerJob::class, 1);
});

it('sending newsletter to all customers dispatches jobs for all customers with email', function (): void {
    Queue::fake();

    $admin = adminUser();

    $uA = \App\Models\User::factory()->create();
    $uB = \App\Models\User::factory()->create();

    Customer::create([
        'user_id' => $uA->id, 'company_name' => 'A', 'email' => 'a@ex.com', 'segment' => 'vip',
    ]);
    Customer::create([
        'user_id' => $uB->id, 'company_name' => 'B', 'email' => 'b@ex.com', 'segment' => 'healthy',
    ]);

    $campaign = NewsletterCampaign::create([
        'subject'         => 'Vseobecna',
        'body_html'       => '<p>Vse</p>',
        'status'          => 'draft',
        'target_audience' => 'customers_all',
        'created_by'      => $admin->id,
    ]);

    $this->actingAs($admin)
        ->post(route('admin.newsletter.send', $campaign))
        ->assertRedirect();

    Queue::assertPushed(\App\Jobs\SendCampaignToCustomerJob::class, 2);
});
