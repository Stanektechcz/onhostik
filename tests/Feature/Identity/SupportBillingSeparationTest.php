<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Onhost\Domain\Billing\ChargebackService;
use Onhost\Domain\Identity\Authorization\RoleCatalog;
use Onhost\Domain\Identity\StepUp\StepUpService;
use Onhost\Domain\Orders\Models\Order;
use Onhost\Domain\Organizations\Commands\OrganizationCommand;
use Onhost\Domain\Organizations\Models\OrganizationMembership;
use Onhost\Domain\WalletLedger\WalletService;
use Onhost\Platform\Commands\CommandBus;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Money\Money;

/*
 * Servicing a customer and moving a customer's money are two permissions (Brain card H348). The roles that repair a
 * web site, a game server or a VM — support and the technical admins — must not reach the payer, the credit or the
 * ownership of the contract through any service path; finance reaches the money only behind a fresh step-up.
 */

beforeEach(function () {
    $this->seed([CatalogSeeder::class, TaxRuleSeeder::class, LegalEntitySeeder::class]);
});

it('gives no role that services customers a permission over their money, their members or the ownership of the contract', function () {
    $finance = ['platform_owner', 'billing_finance_admin', 'billing_operator'];
    $servicing = array_filter(RoleCatalog::all(), fn (array $role, string $key) => $role['staff'] && ! in_array($key, $finance, true) && array_intersect($role['permissions'], ['staff.service.manage', 'staff.order.manage', 'support.ticket.manage', 'staff.console']) !== [], ARRAY_FILTER_USE_BOTH);
    expect(array_keys($servicing))->toContain('support_l1', 'support_l2', 'support_l3', 'support_manager', 'game_admin', 'shared_hosting_admin', 'sales');

    $forbidden = fn (string $permission) => str_starts_with($permission, 'billing.') && ! in_array($permission, ['billing.invoice.read', 'billing.wallet.read'], true)
        || in_array($permission, ['organization.close', 'organization.manage', 'organization.members.manage', 'partner.manage'], true);
    foreach ($servicing as $key => $role) {
        expect(array_values(array_filter($role['permissions'], $forbidden)))->toBe([], "role {$key} reaches money or ownership");
    }
});

it('lets support place an order the customer pays themselves, and keeps the credit, the invoice account and the refunded share with finance', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $wallets = app(WalletService::class);
    $wallets->topup($org, Money::decimal('5000', 'CZK'), 'card', 'h348-topup', $this->contextFor($owner, $org), bankProvider: 'comgate');
    $before = $wallets->spendable($org, 'CZK')->minor;
    $items = [['product_key' => 'web-hosting', 'plan_key' => 'start', 'config' => ['domain' => 'podpora-h348.cz']]];

    $support = $this->staff('support_l2');
    $this->actingAs($support, 'sanctum');
    app(StepUpService::class)->grant($support, 'totp', null, '127.0.0.1'); // even freshly verified: the permission is what is missing, not the second factor

    // the console is told, so it does not offer what would end in 403
    expect($this->getJson("/v1/staff/customers/{$org->id}")->assertOk()->json('data.can'))->toBe(['place_order' => true, 'move_money' => false]);
    expect($this->getJson('/v1/staff/chargebacks')->assertOk()->json('data.can_set_share'))->toBeFalse();

    foreach (['wallet', 'postpaid'] as $i => $mode) {
        $this->withHeader('Idempotency-Key', "h348-{$mode}")->postJson("/v1/staff/customers/{$org->id}/orders", ['items' => $items, 'payment' => $mode, 'note' => 'tiket #348'])->assertForbidden();
    }
    $this->withHeader('Idempotency-Key', 'h348-credit')->postJson("/v1/staff/customers/{$org->id}/wallet/credit", ['amount' => 100, 'note' => 'tiket #348'])->assertForbidden();
    $this->withHeader('Idempotency-Key', 'h348-share')->putJson('/v1/staff/chargebacks/settings', ['percent' => 100])->assertForbidden();
    expect($wallets->spendable($org, 'CZK')->minor)->toBe($before)
        ->and(Order::query()->where('organization_id', $org->id)->count())->toBe(0)
        ->and(app(ChargebackService::class)->percent())->toBe((int) config('onhost.chargeback.percent', 70));

    // what support is for: the order is placed, the customer pays the proforma themselves
    $bank = $this->withHeader('Idempotency-Key', 'h348-bank')->postJson("/v1/staff/customers/{$org->id}/orders", ['items' => $items, 'payment' => 'bank', 'note' => 'tiket #348'])->assertCreated();
    expect($bank->json('state'))->toBe('PENDING_PAYMENT')->and($wallets->spendable($org, 'CZK')->minor)->toBe($before);

    // the ownership of the contract and the members are the customer's own: a global staff role carries no such right
    $stranger = $this->customer();
    $bus = app(CommandBus::class);
    $context = $this->contextFor($support, $org, 'totp');
    expect(fn () => $bus->dispatch(new OrganizationCommand($org->id, 'h348-owner', ['op' => 'transfer_ownership', 'user_id' => $stranger->id]), $context))
        ->toThrow(fn (DomainError $e) => expect($e->status)->toBe(403));
    expect(fn () => $bus->dispatch(new OrganizationCommand($org->id, 'h348-member', ['op' => 'change_role', 'user_id' => $owner->id, 'role' => 'viewer']), $context))
        ->toThrow(fn (DomainError $e) => expect($e->status)->toBe(403));
    expect(OrganizationMembership::query()->where('organization_id', $org->id)->where('user_id', $owner->id)->value('role_key'))->toBe('owner');
});

it('lets finance move the money, and only behind a fresh step-up', function () {
    [, $org] = $this->customerWithOrganization();
    $finance = $this->staff('billing_finance_admin');
    $this->actingAs($finance, 'sanctum');

    expect($this->getJson("/v1/staff/customers/{$org->id}")->assertOk()->json('data.can.move_money'))->toBeTrue();
    // finance reads the queue to set the share, and is not offered the decision that belongs to support
    expect($this->getJson('/v1/staff/chargebacks')->assertOk()->json('data'))->toMatchArray(['can_set_share' => true, 'can_decide' => false]);
    $this->getJson('/v1/staff/chargebacks/settings')->assertOk();
    $this->putJson('/v1/staff/chargebacks/settings', ['percent' => 60])->assertForbidden()->assertJsonPath('error', 'step_up_required');
    expect(app(ChargebackService::class)->percent())->toBe((int) config('onhost.chargeback.percent', 70));

    app(StepUpService::class)->grant($finance, 'totp', null, '127.0.0.1');
    $this->withHeader('Idempotency-Key', 'h348-share-ok')->putJson('/v1/staff/chargebacks/settings', ['percent' => 60])->assertOk()->assertJsonPath('percent', 60);
    expect(app(ChargebackService::class)->percent())->toBe(60);
});
