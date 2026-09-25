<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Illuminate\Support\Str;
use Onhost\Domain\Domains\DomainService;
use Onhost\Domain\Domains\DomainStateMachine;
use Onhost\Domain\Identity\Authorization\Authorizer;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Identity\StepUp\StepUpService;
use Onhost\Domain\Invoicing\InvoiceService;
use Onhost\Domain\Invoicing\Models\Invoice;
use Onhost\Domain\Marketplace\MarketplaceService;
use Onhost\Domain\Marketplace\Models\MarketplaceListing;
use Onhost\Domain\Organizations\Commands\OrganizationCommand;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Organizations\OrganizationService;
use Onhost\Domain\Services\Models\Backup;
use Onhost\Domain\Services\ServiceArchiveService;
use Onhost\Domain\Support\Models\WorkOffer;
use Onhost\Domain\Support\WorkOfferService;
use Onhost\Domain\WalletLedger\WalletService;
use Onhost\Platform\Commands\CommandBus;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Commands\CommandScope;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Money\Money;

/*
 * Owner decision 20 (TASK-0021): account credit is spent by the organization owner or its billing admin. What is paid from
 * credit at once — an invoice paid from credit, a domain renewal, a marketplace order, the approval of paid support work,
 * the fee for an archive download — is refused for anybody else with a message that says whom to ask. Nothing is charged.
 */

beforeEach(function () {
    $this->seed([CatalogSeeder::class, TaxRuleSeeder::class, LegalEntitySeeder::class]);
    config(['onhost.orders.credit_approval.enabled' => true]);
});

function creditGateMember(Organization $org, string $role): User
{
    $member = User::factory()->create();
    app(OrganizationService::class)->attachMember($org, $member, $role, CommandContext::system('test'), true);

    return $member;
}

function creditGateInvoice(Organization $org): Invoice
{
    $service = app(InvoiceService::class);
    $ctx = CommandContext::system('test')->withScope($org->id);
    $draft = $service->draft($org, 'invoice', 'CZK', [
        ['sku' => 'web-hosting-start', 'description' => 'Webhosting Start — září 2026', 'qty' => 1, 'unit_net' => 8900, 'discount' => 0, 'net' => 8900, 'tax_rate' => '21', 'tax_category' => 'S', 'tax' => 1869, 'total' => 10769, 'period_from' => '2026-09-01', 'period_to' => '2026-09-30'],
    ], $ctx, null, ['postpaid' => true, 'payment_method' => 'bank']);

    return $service->issue($draft, $ctx);
}

/** Runs the call and returns the DomainError it threw (or null). */
function creditGateRefusal(callable $call): ?DomainError
{
    try {
        $call();
    } catch (DomainError $e) {
        return $e;
    }

    return null;
}

it('refuses paying an invoice from credit to an org_admin and lets the owner and the billing admin do it', function () {
    [$owner, $org] = $this->customerWithOrganization();
    app(WalletService::class)->topup($org, Money::decimal('5000', 'CZK'), 'card', 'seed', CommandContext::system('test'), bankProvider: 'comgate');
    $admin = creditGateMember($org, 'org_admin');
    $billing = creditGateMember($org, 'billing_admin');
    $spendable = app(WalletService::class)->spendable($org, 'CZK')->minor;

    $invoice = creditGateInvoice($org);
    $this->actingAs($admin, 'sanctum');
    $refused = $this->postJson("/v1/invoices/{$invoice->id}/pay", ['method' => 'wallet'], ['Idempotency-Key' => (string) Str::ulid()])->assertForbidden();
    expect($refused->json('error'))->toBe('credit_spend_not_allowed')->and($refused->json('message'))->toContain('vlastník')->toContain('fakturační správce')
        ->and($invoice->refresh()->state)->toBe(Invoice::ISSUED)
        ->and(app(WalletService::class)->spendable($org, 'CZK')->minor)->toBe($spendable);
    // a card or a bank transfer stays open to the same member
    $this->postJson("/v1/invoices/{$invoice->id}/pay", ['method' => 'bank'], ['Idempotency-Key' => (string) Str::ulid()])->assertOk();

    $this->actingAs($billing, 'sanctum');
    $this->postJson("/v1/invoices/{$invoice->id}/pay", ['method' => 'wallet'], ['Idempotency-Key' => (string) Str::ulid()])->assertOk()->assertJsonPath('state', Invoice::PAID);
    $second = creditGateInvoice($org);
    $this->actingAs($owner, 'sanctum');
    $this->postJson("/v1/invoices/{$second->id}/pay", ['method' => 'wallet'], ['Idempotency-Key' => (string) Str::ulid()])->assertOk()->assertJsonPath('state', Invoice::PAID);
});

it('leaves paying an invoice from credit as it was while the switch is off', function () {
    config(['onhost.orders.credit_approval.enabled' => false]);
    [, $org] = $this->customerWithOrganization();
    app(WalletService::class)->topup($org, Money::decimal('5000', 'CZK'), 'card', 'seed', CommandContext::system('test'), bankProvider: 'comgate');
    $admin = creditGateMember($org, 'org_admin');
    $invoice = creditGateInvoice($org);

    $this->actingAs($admin, 'sanctum');
    $this->postJson("/v1/invoices/{$invoice->id}/pay", ['method' => 'wallet'], ['Idempotency-Key' => (string) Str::ulid()])->assertOk()->assertJsonPath('state', Invoice::PAID);
});

it('refuses a manual domain renewal from credit to a member without the right, never the scheduler', function () {
    [, $org] = $this->customerWithOrganization();
    $domain = graceDomain($org, 'firma-gate.cz', DomainStateMachine::ACTIVE, now()->addMonth());
    $domainManager = creditGateMember($org, 'domain_manager');

    $refused = creditGateRefusal(fn () => app(DomainService::class)->renew($domain, 1, $this->contextFor($domainManager, $org), 'renew:'.Str::ulid()));
    expect($refused?->error)->toBe('credit_spend_not_allowed')->and($refused?->status)->toBe(403)->and($refused?->getMessage())->toContain('vlastník');

    // the scheduler renews with a system context: the gate is not what stops it (it goes on to the registrar)
    $system = creditGateRefusal(fn () => app(DomainService::class)->renew($domain, 1, CommandContext::system('renewal')->withScope($org->id), 'renew:'.Str::ulid()));
    expect($system?->error)->not->toBe('credit_spend_not_allowed');
});

it('refuses a marketplace order and the approval of paid support work to an org_admin', function () {
    [, $org] = $this->customerWithOrganization();
    $admin = creditGateMember($org, 'org_admin');
    $ctx = $this->contextFor($admin, $org);

    $listing = new MarketplaceListing(['state' => MarketplaceListing::PUBLISHED]);
    $refused = creditGateRefusal(fn () => app(MarketplaceService::class)->order($org, $admin, $listing, ['brief' => 'Potřebujeme péči o WordPress.'], $ctx));
    expect($refused?->error)->toBe('credit_spend_not_allowed')->and($refused?->status)->toBe(403);

    $offer = new WorkOffer(['organization_id' => $org->id, 'state' => WorkOffer::PROPOSED]);
    $refused = creditGateRefusal(fn () => app(WorkOfferService::class)->decide($offer, true, $ctx));
    expect($refused?->error)->toBe('credit_spend_not_allowed')->and($refused?->status)->toBe(403);
});

it('refuses the paid download of an archive to a member without the right', function () {
    [, $org] = $this->customerWithOrganization();
    $operator = creditGateMember($org, 'cloud_operator');
    $backup = Backup::query()->create([
        'service_id' => 'srv_gate', 'organization_id' => $org->id, 'kind' => 'final', 'state' => 'completed', 'protected' => true,
        'started_at' => now()->subDay(), 'finished_at' => now()->subDay(), 'size_bytes' => 1024, 'retention_until' => now()->addDays(60), 'meta' => ['set' => 'x', 'family' => 'web'],
    ]);

    $refused = creditGateRefusal(fn () => app(ServiceArchiveService::class)->download($backup, $this->contextFor($operator, $org)));
    expect($refused?->error)->toBe('credit_spend_not_allowed')->and($refused?->status)->toBe(403)
        ->and((bool) data_get($backup->refresh()->meta, 'download.paid', false))->toBeFalse();
});

it('does not let an org_admin hand the right to spend the credit to anybody, themselves included', function () {
    [, $org] = $this->customerWithOrganization();
    $admin = creditGateMember($org, 'org_admin');
    $colleague = creditGateMember($org, 'viewer');
    $bus = app(CommandBus::class);
    app(StepUpService::class)->grant($admin, 'totp', 'test-session', '127.0.0.1'); // a member change is HIGH: the refusal must come from the role rule, not from a missing step-up
    $ctx = $this->contextFor($admin, $org, 'totp');

    foreach ([$colleague, $admin] as $target) {
        $refused = creditGateRefusal(fn () => $bus->dispatch(new OrganizationCommand($org->id, 'role:'.Str::ulid(), ['op' => 'change_role', 'user_id' => $target->id, 'role' => 'billing_admin']), $ctx));
        expect($refused?->error)->toBeIn(['role_above_own', 'self_membership_locked']);
    }
    expect(app(Authorizer::class)->can($colleague->refresh(), 'billing.wallet.spend', CommandScope::organization($org->id)))->toBeFalse();
});
