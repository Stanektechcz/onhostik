<?php

declare(strict_types=1);

use App\Domains\Billing\Enums\InvoiceStatus;
use App\Domains\Billing\Models\FinancialExportJob;
use App\Domains\Billing\Services\FinancialExportService;
use Database\Seeders\IntegrationSeeder;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class, IntegrationSeeder::class]);
});

// ── Model helpers ─────────────────────────────────────────────────────────────

it('FinancialExportJob statusLabel returns Czech strings', function (): void {
    expect((new FinancialExportJob(['status' => 'pending']))->statusLabel())->toBe('Ve frontě')
        ->and((new FinancialExportJob(['status' => 'processing']))->statusLabel())->toBe('Zpracovává se')
        ->and((new FinancialExportJob(['status' => 'done']))->statusLabel())->toBe('Dokončeno')
        ->and((new FinancialExportJob(['status' => 'failed']))->statusLabel())->toBe('Chyba');
});

it('FinancialExportJob formatLabel returns readable labels', function (): void {
    expect((new FinancialExportJob(['format' => 'pohoda_xml']))->formatLabel())->toBe('POHODA XML')
        ->and((new FinancialExportJob(['format' => 'csv_invoices']))->formatLabel())->toBe('CSV – Faktury')
        ->and((new FinancialExportJob(['format' => 'csv_payments']))->formatLabel())->toBe('CSV – Platby')
        ->and((new FinancialExportJob(['format' => 'pdf_summary']))->formatLabel())->toBe('PDF – Přehled');
});

it('isDownloadable returns true only when done and file_path set', function (): void {
    expect((new FinancialExportJob(['status' => 'done', 'file_path' => 'some/path.xml']))->isDownloadable())->toBeTrue()
        ->and((new FinancialExportJob(['status' => 'done', 'file_path' => null]))->isDownloadable())->toBeFalse()
        ->and((new FinancialExportJob(['status' => 'failed', 'file_path' => 'x.csv']))->isDownloadable())->toBeFalse();
});

// ── Service: CSV invoices ─────────────────────────────────────────────────────

it('FinancialExportService generates CSV invoices export', function (): void {
    $user = customerUser();
    $result = placeOrder($user);
    $invoice = $result['invoice'];

    $invoice->update(['status' => InvoiceStatus::Paid]);

    $job = FinancialExportJob::create([
        'uuid'   => Str::uuid()->toString(),
        'format' => 'csv_invoices',
        'status' => 'pending',
    ]);

    app(FinancialExportService::class)->process($job);

    $job->refresh();
    expect($job->status)->toBe('done')
        ->and($job->file_path)->toEndWith('.csv')
        ->and($job->row_count)->toBeGreaterThanOrEqual(1);
});

it('CSV invoices export contains invoice number', function (): void {
    $user = customerUser();
    $result = placeOrder($user);
    $invoice = $result['invoice'];

    $job = FinancialExportJob::create([
        'uuid'   => Str::uuid()->toString(),
        'format' => 'csv_invoices',
        'status' => 'pending',
    ]);

    app(FinancialExportService::class)->process($job);

    $job->refresh();
    $content = \Illuminate\Support\Facades\Storage::disk('local')->get($job->file_path);
    expect($content)->toContain($invoice->number);
});

// ── Service: CSV payments ─────────────────────────────────────────────────────

it('FinancialExportService generates CSV payments export', function (): void {
    $job = FinancialExportJob::create([
        'uuid'   => Str::uuid()->toString(),
        'format' => 'csv_payments',
        'status' => 'pending',
    ]);

    app(FinancialExportService::class)->process($job);

    $job->refresh();
    expect($job->status)->toBe('done')
        ->and($job->file_path)->toEndWith('.csv');
});

// ── Service: POHODA XML ───────────────────────────────────────────────────────

it('FinancialExportService generates POHODA XML export', function (): void {
    $user = customerUser();
    placeOrder($user);

    $job = FinancialExportJob::create([
        'uuid'   => Str::uuid()->toString(),
        'format' => 'pohoda_xml',
        'status' => 'pending',
    ]);

    app(FinancialExportService::class)->process($job);

    $job->refresh();
    expect($job->status)->toBe('done')
        ->and($job->file_path)->toEndWith('.xml')
        ->and($job->row_count)->toBeGreaterThanOrEqual(1);

    $content = \Illuminate\Support\Facades\Storage::disk('local')->get($job->file_path);
    expect($content)->toContain('<inv:invoice');
});

// ── Service: PDF summary ──────────────────────────────────────────────────────

it('FinancialExportService generates PDF summary export', function (): void {
    $user = customerUser();
    placeOrder($user);

    $job = FinancialExportJob::create([
        'uuid'   => Str::uuid()->toString(),
        'format' => 'pdf_summary',
        'status' => 'pending',
    ]);

    app(FinancialExportService::class)->process($job);

    $job->refresh();
    expect($job->status)->toBe('done')
        ->and($job->file_path)->toEndWith('.pdf');
});

// ── Service: date range filter ────────────────────────────────────────────────

it('export respects date_from and date_to filters', function (): void {
    $user = customerUser();
    placeOrder($user);

    $job = FinancialExportJob::create([
        'uuid'      => Str::uuid()->toString(),
        'format'    => 'csv_invoices',
        'status'    => 'pending',
        'date_from' => now()->addYear()->toDateString(),
        'date_to'   => now()->addYears(2)->toDateString(),
    ]);

    app(FinancialExportService::class)->process($job);

    $job->refresh();
    expect($job->status)->toBe('done')
        ->and($job->row_count)->toBe(0); // no invoices in future range
});

// ── Admin routes ──────────────────────────────────────────────────────────────

it('admin can list exports', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
        ->get(route('admin.exports.index'))
        ->assertOk()
        ->assertSee('Finanční exporty');
});

it('non-admin cannot access exports', function (): void {
    $user = customerUser();

    $this->actingAs($user)
        ->get(route('admin.exports.index'))
        ->assertStatus(403);
});

it('admin can view export create form', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
        ->get(route('admin.exports.create'))
        ->assertOk()
        ->assertSee('Formát');
});

it('admin can create and generate CSV invoices export', function (): void {
    $admin = adminUser();
    $user  = customerUser();
    placeOrder($user);

    $this->actingAs($admin)
        ->post(route('admin.exports.store'), [
            'format'    => 'csv_invoices',
            'date_from' => now()->subMonth()->toDateString(),
            'date_to'   => now()->toDateString(),
        ])
        ->assertRedirect(route('admin.exports.index'));

    expect(FinancialExportJob::where('format', 'csv_invoices')->where('status', 'done')->exists())->toBeTrue();
});

it('admin export store validates format', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
        ->post(route('admin.exports.store'), [
            'format' => 'invalid_format',
        ])
        ->assertSessionHasErrors('format');
});

it('admin export store validates date_to after date_from', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
        ->post(route('admin.exports.store'), [
            'format'    => 'csv_invoices',
            'date_from' => now()->toDateString(),
            'date_to'   => now()->subDay()->toDateString(),
        ])
        ->assertSessionHasErrors('date_to');
});

it('admin can download completed export', function (): void {
    $admin = adminUser();
    $user  = customerUser();
    placeOrder($user);

    $this->actingAs($admin)->post(route('admin.exports.store'), [
        'format' => 'csv_invoices',
    ]);

    $job = FinancialExportJob::where('format', 'csv_invoices')->first();

    expect($job->status)->toBe('done');

    $response = $this->actingAs($admin)
        ->get(route('admin.exports.download', $job));

    $response->assertOk()
        ->assertHeader('Content-Disposition');
});

it('admin can delete an export', function (): void {
    $admin = adminUser();

    $job = FinancialExportJob::create([
        'uuid'   => Str::uuid()->toString(),
        'format' => 'csv_invoices',
        'status' => 'done',
    ]);

    $this->actingAs($admin)
        ->delete(route('admin.exports.destroy', $job))
        ->assertRedirect(route('admin.exports.index'));

    expect(FinancialExportJob::find($job->id))->toBeNull();
});
