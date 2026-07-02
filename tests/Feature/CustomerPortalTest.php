<?php

declare(strict_types=1);

use App\Domains\Ai\Models\AiRun;
use App\Domains\Ai\Models\AiUsageLog;
use App\Domains\Backups\Models\BackupJob;
use App\Domains\Billing\Actions\ProcessMockPaymentAction;
use App\Domains\Billing\Events\InvoicePaid;
use App\Domains\Billing\Listeners\HandleInvoicePaid;
use App\Domains\Billing\Models\CreditTransaction;
use App\Domains\Billing\Models\Invoice;
use App\Domains\Billing\Services\CreditLedger;
use App\Domains\Provisioning\Enums\ProvisioningDriver;
use App\Domains\Provisioning\Enums\ServiceStatus;
use App\Domains\Provisioning\Models\Service;
use App\Domains\Support\Models\SupportTicket;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;
use Illuminate\Support\Facades\Queue;
use Spatie\Activitylog\Models\Activity;

beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class]);
});

it('loads the customer dashboard with real widgets', function (): void {
    $user = customerUser();
    placeOrder($user);

    $this->actingAs($user)
        ->get(route('panel.dashboard'))
        ->assertOk()
        ->assertSee('Moje faktury')
        ->assertSee('Objednávky');
});

it('tops up credit via mock payment and never double-credits', function (): void {
    $user     = customerUser();
    $customer = $user->customer;
    $ledger   = app(CreditLedger::class);

    // 1. create the top-up invoice
    $this->actingAs($user)
        ->post(route('panel.billing.credits.topup'), ['amount' => 500])
        ->assertRedirect();

    $invoice = Invoice::query()->where('purpose', 'credit_topup')->firstOrFail();
    expect($invoice->total?->getMinorAmount()->toInt())->toBe(50_000);

    // 2. pay it with the mock gateway → wallet credited
    app(ProcessMockPaymentAction::class)->execute($invoice);

    expect($ledger->getBalance($customer->fresh())->getMinorAmount()->toInt())->toBe(50_000);

    // 3. replaying the listener never credits twice
    $payment = $invoice->payments()->firstOrFail();
    app(HandleInvoicePaid::class)->handle(new InvoicePaid($invoice->fresh(), $payment));

    expect($ledger->getBalance($customer->fresh())->getMinorAmount()->toInt())->toBe(50_000)
        ->and(CreditTransaction::query()->count())->toBe(1);
});

it('refuses paying a top-up invoice from credit', function (): void {
    $user = customerUser();

    $this->actingAs($user)->post(route('panel.billing.credits.topup'), ['amount' => 300]);
    $invoice = Invoice::query()->where('purpose', 'credit_topup')->firstOrFail();

    $this->actingAs($user)
        ->post(route('panel.billing.invoices.pay-credit', $invoice))
        ->assertSessionHasErrors('payment');
});

it('creates, shows and replies to a support ticket with events', function (): void {
    $user = customerUser();

    $this->actingAs($user)
        ->post(route('panel.support.store'), [
            'subject' => 'Web mi nejede',
            'message' => 'Dobrý den, od rána mi nejede web, můžete se podívat?',
        ])
        ->assertRedirect();

    $ticket = SupportTicket::query()->firstOrFail();

    $this->actingAs($user)->get(route('panel.support.show', $ticket))->assertOk()->assertSee('Web mi nejede');

    $this->actingAs($user)
        ->post(route('panel.support.reply', $ticket), ['message' => 'Doplňuji: jde o doménu example.cz'])
        ->assertRedirect();

    expect($ticket->fresh()->messages()->count())->toBe(2)
        ->and($ticket->events()->pluck('event')->all())->toContain('created', 'replied')
        ->and(Activity::query()->where('description', 'support.ticket_created')->exists())->toBeTrue();
});

it('blocks customers from another customer\'s ticket', function (): void {
    $owner    = customerUser();
    $intruder = customerUser();

    $this->actingAs($owner)->post(route('panel.support.store'), [
        'subject' => 'Privátní ticket',
        'message' => 'Toto je soukromá zpráva pro podporu.',
    ]);

    $ticket = SupportTicket::query()->firstOrFail();

    $this->actingAs($intruder)->get(route('panel.support.show', $ticket))->assertForbidden();
});

it('runs the mock AI assistant and records the trail', function (): void {
    $user = customerUser();

    $this->actingAs($user)
        ->post(route('panel.ai.run'), [
            'feature' => 'plan_recommendation',
            'text'    => 'Potřebuji hosting pro firemní web s e-shopem.',
        ])
        ->assertRedirect();

    $run = AiRun::query()->firstOrFail();

    expect($run->provider)->toBe('mock')
        ->and($run->messages()->count())->toBe(2)
        ->and(AiUsageLog::query()->count())->toBe(1)
        ->and(Activity::query()->where('description', 'ai.run_completed')->exists())->toBeTrue();
});

// ── Service actions (backup, cancellation, plan change) ───────────────────────

it('customer can request a mock backup', function (): void {
    Queue::fake();

    $user      = customerUser();
    $productId = \App\Domains\Products\Models\Product::value('id');

    $service = Service::create([
        'customer_id'         => $user->customer->id,
        'product_id'          => $productId,
        'status'              => ServiceStatus::Active,
        'label'               => 'backup-test-service',
        'provisioning_driver' => ProvisioningDriver::AAPanel,
    ]);

    $this->actingAs($user)
        ->post(route('panel.services.backup', $service))
        ->assertRedirect();

    expect(BackupJob::where('service_id', $service->id)->exists())->toBeTrue();
});

it('customer cannot request backup for inactive service', function (): void {
    $user      = customerUser();
    $productId = \App\Domains\Products\Models\Product::value('id');

    $service = Service::create([
        'customer_id'         => $user->customer->id,
        'product_id'          => $productId,
        'status'              => ServiceStatus::Suspended,
        'label'               => 'suspended-service',
        'provisioning_driver' => ProvisioningDriver::AAPanel,
    ]);

    $this->actingAs($user)
        ->post(route('panel.services.backup', $service))
        ->assertSessionHasErrors('backup');
});

it('customer can request service cancellation via support ticket', function (): void {
    $user      = customerUser();
    $productId = \App\Domains\Products\Models\Product::value('id');

    $service = Service::create([
        'customer_id'         => $user->customer->id,
        'product_id'          => $productId,
        'status'              => ServiceStatus::Active,
        'label'               => 'cancel-me-service',
        'provisioning_driver' => ProvisioningDriver::AAPanel,
    ]);

    $this->actingAs($user)
        ->post(route('panel.services.request-cancel', $service))
        ->assertRedirect();

    // A cancellation support ticket should be created
    expect(SupportTicket::where('customer_id', $user->customer->id)
        ->where('department', 'billing')
        ->exists())->toBeTrue();
});

it('customer cannot cancel a terminated service', function (): void {
    $user      = customerUser();
    $productId = \App\Domains\Products\Models\Product::value('id');

    $service = Service::create([
        'customer_id'         => $user->customer->id,
        'product_id'          => $productId,
        'status'              => ServiceStatus::Terminated,
        'label'               => 'terminated-service',
        'provisioning_driver' => ProvisioningDriver::AAPanel,
    ]);

    $this->actingAs($user)
        ->post(route('panel.services.request-cancel', $service))
        ->assertSessionHasErrors('cancel');
});

it('customer cannot perform service actions on another customer\'s service', function (): void {
    $owner    = customerUser();
    $intruder = customerUser();
    $productId = \App\Domains\Products\Models\Product::value('id');

    $service = Service::create([
        'customer_id'         => $owner->customer->id,
        'product_id'          => $productId,
        'status'              => ServiceStatus::Active,
        'label'               => 'private-service',
        'provisioning_driver' => ProvisioningDriver::AAPanel,
    ]);

    $this->actingAs($intruder)
        ->post(route('panel.services.backup', $service))
        ->assertForbidden();

    $this->actingAs($intruder)
        ->post(route('panel.services.request-cancel', $service))
        ->assertForbidden();
});

it('customer can download GDPR data export as ZIP', function (): void {
    $user = customerUser();

    $response = $this->actingAs($user)
        ->get(route('panel.account.data-export'))
        ->assertOk();

    expect($response->headers->get('Content-Type'))->toContain('application/zip');
    expect($response->headers->get('Content-Disposition'))->toContain('onhost_data_export_');

    // ZIP should contain profile.json with user email
    $zip = new ZipArchive();
    $tmpFile = tempnam(sys_get_temp_dir(), 'test_export') . '.zip';
    file_put_contents($tmpFile, $response->getContent());
    $zip->open($tmpFile);
    $profileJson = $zip->getFromName('profile.json');
    $zip->close();
    @unlink($tmpFile);

    expect($profileJson)->not->toBeFalse();
    $profile = json_decode($profileJson, true);
    expect($profile['email'])->toBe($user->email);
});

it('saves billing details for the tax document flow', function (): void {
    $user = customerUser();

    $this->actingAs($user)
        ->put(route('panel.account.billing.update'), [
            'type'         => 'company',
            'company_name' => 'Test s.r.o.',
            'vat_number'   => 'CZ12345678',
            'country_code' => 'cz',
            'street'       => 'Dlouhá 1',
            'city'         => 'Praha',
            'zip'          => '11000',
        ])
        ->assertRedirect();

    $customer = $user->customer->fresh();

    expect($customer->company_name)->toBe('Test s.r.o.')
        ->and($customer->country_code)->toBe('CZ')
        ->and($customer->billingAddress()?->street)->toBe('Dlouhá 1');
});
