<?php

declare(strict_types=1);

use App\Domains\Backups\Enums\BackupJobStatus;
use App\Domains\Backups\Models\BackupJob;
use App\Domains\Backups\Models\BackupPolicy;
use App\Domains\Billing\Actions\IssueTaxDocumentAction;
use App\Domains\Billing\Actions\ProcessMockPaymentAction;
use App\Domains\Billing\Enums\InvoiceType;
use App\Domains\Billing\Exceptions\IncompleteBillingDetailsException;
use App\Domains\Billing\Models\Invoice;
use App\Domains\Integrations\Clients\AapanelClient;
use App\Domains\Integrations\Clients\WedosWapiClient;
use App\Domains\Integrations\Models\IntegrationSetting;
use App\Domains\Monitoring\Models\Monitor;
use App\Domains\Provisioning\Exceptions\ProvisioningException;
use App\Domains\Provisioning\Models\Service;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;
use Spatie\Activitylog\Models\Activity;

beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class]);
});

function paidService(): Service
{
    $user = customerUser();
    ['invoice' => $invoice] = placeOrder($user);
    app(ProcessMockPaymentAction::class)->execute($invoice);

    return Service::query()->firstOrFail();
}

it('auto-creates a monitor and a backup policy after provisioning', function (): void {
    $service = paidService();

    $monitor = Monitor::query()->where('service_id', $service->id)->firstOrFail();
    $policy  = BackupPolicy::query()->where('service_id', $service->id)->firstOrFail();

    expect($monitor->status->value)->toBe('up')
        ->and($monitor->provider)->toBe('internal_mock')
        ->and($monitor->checks()->count())->toBeGreaterThan(0)
        ->and($policy->frequency)->toBe('daily')
        ->and(Activity::query()->where('description', 'monitoring.monitor_created')->exists())->toBeTrue()
        ->and(Activity::query()->where('description', 'backup.policy_created')->exists())->toBeTrue();
});

it('runs a manual mock backup end to end', function (): void {
    $service = paidService();
    $user    = $service->customer->user;

    $this->actingAs($user)
        ->post(route('panel.services.backup', $service))
        ->assertRedirect();

    $job = BackupJob::query()->where('service_id', $service->id)->firstOrFail();

    expect($job->status)->toBe(BackupJobStatus::Success)
        ->and($job->files()->count())->toBe(1)
        ->and($job->policy?->last_run_at)->not->toBeNull()
        ->and(Activity::query()->where('description', 'backup.completed')->exists())->toBeTrue();
});

it('installs WordPress in mock mode exactly once', function (): void {
    $service = paidService();
    $user    = $service->customer->user;

    $this->actingAs($user)->post(route('panel.services.wordpress', $service))->assertRedirect();
    $this->actingAs($user)->post(route('panel.services.wordpress', $service))->assertRedirect();

    expect($service->provisioningTasks()->where('operation', 'install_wordpress')->count())->toBe(1);
});

it('blocks the tax document without billing details and issues it after completion', function (): void {
    $user = customerUser();
    ['invoice' => $proforma] = placeOrder($user);

    app(ProcessMockPaymentAction::class)->execute($proforma);

    // blocked — customer has no billing address
    expect(Invoice::query()->where('type', InvoiceType::Invoice->value)->exists())->toBeFalse()
        ->and(Activity::query()->where('description', 'invoice.tax_document_blocked')->exists())->toBeTrue();

    expect(fn () => app(IssueTaxDocumentAction::class)->execute($proforma->fresh()))
        ->toThrow(IncompleteBillingDetailsException::class);

    // complete the details → manual issue succeeds
    $user->customer->addresses()->create([
        'type' => 'billing', 'street' => 'Krátká 2', 'city' => 'Brno', 'zip' => '60200',
        'country_code' => 'CZ', 'is_primary' => true,
    ]);

    $taxDocument = app(IssueTaxDocumentAction::class)->execute($proforma->fresh());

    expect($taxDocument->type)->toBe(InvoiceType::Invoice)
        ->and($taxDocument->parent_invoice_id)->toBe($proforma->id)
        ->and($taxDocument->status->value)->toBe('paid')
        ->and($taxDocument->total?->getMinorAmount()->toInt())->toBe($proforma->total?->getMinorAmount()->toInt());

    // idempotent — second call returns the same document
    expect(app(IssueTaxDocumentAction::class)->execute($proforma->fresh())->id)->toBe($taxDocument->id);
});

it('issues the tax document automatically when billing details exist', function (): void {
    $user = customerUser();
    $user->customer->addresses()->create([
        'type' => 'billing', 'street' => 'Hlavní 5', 'city' => 'Ostrava', 'zip' => '70200',
        'country_code' => 'CZ', 'is_primary' => true,
    ]);

    ['invoice' => $proforma] = placeOrder($user);
    app(ProcessMockPaymentAction::class)->execute($proforma);

    $taxDocument = Invoice::query()
        ->where('type', InvoiceType::Invoice->value)
        ->where('parent_invoice_id', $proforma->id)
        ->first();

    expect($taxDocument)->not->toBeNull()
        ->and(Activity::query()->where('description', 'invoice.tax_document_issued')->exists())->toBeTrue();
});

it('keeps real aaPanel calls behind every refusal gate', function (): void {
    $setting = IntegrationSetting::query()->create([
        'provider' => 'aapanel', 'label' => 'aaPanel', 'is_active' => true,
        'mock_mode' => true, 'dry_run' => true,
        'credentials' => ['base_url' => 'https://x', 'api_key' => 'k'],
    ]);

    $client = new AapanelClient($setting);

    // mock/dry-run → simulated results, no HTTP
    expect($client->connectionTest()['dry_run'])->toBeTrue()
        ->and($client->createSite('example.cz')['dry_run'])->toBeTrue()
        ->and($client->getServerHealth()['dry_run'])->toBeTrue();

    // flags open but env gate closed → hard refusal
    $setting->update(['mock_mode' => false, 'dry_run' => false]);
    config(['integrations.real_write_gates.aapanel' => false]);

    expect(fn () => (new AapanelClient($setting->fresh()))->createSite('example.cz'))
        ->toThrow(ProvisioningException::class, 'env approval gate is closed');
});

it('keeps the WEDOS WAPI client in dry-run', function (): void {
    $setting = IntegrationSetting::query()->create([
        'provider' => 'wedos', 'label' => 'WEDOS', 'is_active' => false,
        'mock_mode' => true, 'dry_run' => true, 'credentials' => [],
    ]);

    $client = new WedosWapiClient($setting);

    expect($client->connectionTest()['dry_run'])->toBeTrue()
        ->and($client->checkDomain('example.cz')['dry_run'])->toBeTrue()
        ->and($client->registerDomain('example.cz')['dry_run'])->toBeTrue();
});
