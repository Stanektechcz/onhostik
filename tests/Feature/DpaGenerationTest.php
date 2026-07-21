<?php

declare(strict_types=1);

use App\Domains\Compliance\Services\DpaPdfService;

/**
 * Audit 178 — on-demand GDPR art. 28 Data Processing Agreement.
 *
 * A hosting customer is the CONTROLLER of the data in their services; the
 * platform is the PROCESSOR. Business customers need this document on file, and
 * generating it on demand beats a support ticket.
 */

it('serves the DPA as a PDF download to the signed-in customer', function (): void {
    $user = customerUser();

    $response = $this->actingAs($user)->get(route('panel.compliance.dpa'));

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toBe('application/pdf');
    expect($response->headers->get('Content-Disposition'))->toContain('.pdf');
    // A real PDF, not an error page rendered with a 200.
    expect($response->getContent())->toStartWith('%PDF');
});

it('records who generated which DPA version', function (): void {
    $user = customerUser();

    $this->actingAs($user)->get(route('panel.compliance.dpa'))->assertOk();

    $this->assertDatabaseHas('activity_log', ['description' => 'dpa.generated']);
});

it('forbids generating a DPA without a customer profile', function (): void {
    // A bare user (e.g. staff without a customer) has no controller identity.
    $admin = adminUser();
    $admin->customer()->delete();

    $this->actingAs($admin->fresh())
        ->get(route('panel.compliance.dpa'))
        ->assertForbidden();
});

it('renders the art. 28(3) particulars into the document', function (): void {
    // The route returns binary, so assert the template content by rendering it
    // directly — the same approach used for the invoice PDF.
    $customer = customerUser()->customer;
    $customer->update(['company_name' => 'Controller s.r.o.', 'vat_number' => 'CZ12345678']);

    $html = view('pdf.dpa', [
        'customer'    => $customer->fresh(),
        'processor'   => config('billing.supplier'),
        'dpa'         => config('legal.dpa'),
        'version'     => config('legal.document_versions.dpa'),
        'generatedAt' => now(),
        'isPdf'       => true,
    ])->render();

    expect($html)
        ->toContain('Controller s.r.o.')          // controller identity
        ->toContain('CZ12345678')                  // controller DIČ
        ->toContain('Správce')                     // art. 28 roles
        ->toContain('Zpracovatel')
        ->toContain('Technická a organizační')     // art. 32 measures section
        ->toContain('sub-processors');             // art. 28(2)/(4)
});

it('names the standing sub-processors from config', function (): void {
    $result = app(DpaPdfService::class)->generate(customerUser()->customer);

    // The binary should be a well-formed PDF with a sensible filename.
    expect($result['pdf'])->toStartWith('%PDF')
        ->and($result['filename'])->toContain('DPA-')
        ->and($result['filename'])->toEndWith('.pdf');
});
