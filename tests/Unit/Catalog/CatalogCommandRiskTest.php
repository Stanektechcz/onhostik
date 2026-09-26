<?php

declare(strict_types=1);

use Onhost\Domain\Catalog\Commands\CatalogCommand;
use Onhost\Domain\Identity\Authorization\PermissionCatalog;
use Onhost\Domain\Provisioning\Commands\ProvisioningCommand;

/*
 * Owner decision 13 (2026-09-25): HIGH is a fresh step-up and nothing more; CRITICAL is a step-up and a second person.
 * Every change of a price or a plan in the admin configuration takes a second person, although catalog.manage itself is
 * only HIGH. Withdrawing an offer (a discount, a promo code, a product off sale) stays with one person and a step-up: the
 * emergency brake must not wait for somebody else. An operation the command does not know is a price change.
 */

/** @return array{0:string,1:bool,2:bool} [riskLevel, requiresStepUp, requiresApproval] */
function catalogRiskOf(array $payload): array
{
    $command = new CatalogCommand('catalog-risk-test', $payload);

    return [$command->riskLevel(), $command->requiresStepUp(), $command->requiresApproval()];
}

it('classifies every catalogue operation: price and plan changes take a second person, withdrawals a step-up, navigation nothing', function () {
    $fourEyes = [PermissionCatalog::CRITICAL, true, true];
    $stepUp = [PermissionCatalog::HIGH, true, false];
    $ordinary = [PermissionCatalog::NORMAL, false, false];
    $table = [
        [['op' => 'pricing.commit_discounts.set'], $fourEyes],
        [['op' => 'pricing.regions.set'], $fourEyes],
        [['op' => 'pricing.domain_discount.set'], $fourEyes],
        [['op' => 'promo.upsert', 'promo' => ['code' => 'JARO', 'state' => 'active']], $fourEyes],
        [['op' => 'promo.upsert', 'promo' => ['code' => 'JARO']], $fourEyes], // no state is an active code
        [['op' => 'promo.upsert', 'promo' => ['code' => 'JARO', 'state' => 'nonsense']], $fourEyes], // the handler would store it as active
        [['op' => 'option.upsert'], $fourEyes],
        [['op' => 'option.delete'], $fourEyes],
        [['op' => 'plan.publish'], $fourEyes],
        [['op' => 'plan.activate_version'], $fourEyes],
        [['op' => 'lifecycle.set'], $fourEyes], // the archive download fee is a price
        [['op' => 'product.state', 'state' => 'active'], $fourEyes], // putting a product on sale is offering its prices
        [['op' => 'product.create', 'product_key' => 'limit-raise'], $fourEyes], // a product the code defines; a new offer (TASK-0022 limit-raise)
        [['op' => 'pricing.domain_discount.delete'], $stepUp],
        [['op' => 'promo.delete'], $stepUp],
        [['op' => 'promo.upsert', 'promo' => ['code' => 'JARO', 'state' => 'paused']], $stepUp],
        [['op' => 'promo.upsert', 'promo' => ['code' => 'JARO', 'state' => 'retired']], $stepUp],
        [['op' => 'product.state', 'state' => 'draft'], $stepUp], // the one-person emergency stop
        [['op' => 'pricing.addon_products.set'], $stepUp],
        [['op' => 'product.describe'], $stepUp], // customer-facing copy, neither a price nor a plan (TASK-0022 catalog-versions)
        [['op' => 'panel_nav.set'], $ordinary],
    ];
    foreach ($table as [$payload, $expected]) {
        expect(catalogRiskOf($payload))->toBe($expected, json_encode($payload));
    }

    // the list of operations is complete: every one the handler serves is classified above
    $classified = array_values(array_unique(array_map(fn (array $row) => $row[0]['op'], $table)));
    expect(CatalogCommand::OPS)->toEqualCanonicalizing($classified);
});

it('treats an operation it does not know as a price change', function () {
    expect(catalogRiskOf(['op' => 'pricing.something_new.set']))->toBe([PermissionCatalog::CRITICAL, true, true])
        ->and(catalogRiskOf([]))->toBe([PermissionCatalog::CRITICAL, true, true]);
});

it('asks for a step-up before an automation rule is switched: switching on a default-off rule reaches every service at once', function () {
    $toggle = new ProvisioningCommand('automation-toggle-test', ['op' => 'automation.toggle', 'key' => 'usage.watch', 'enabled' => true]);
    expect($toggle->riskLevel())->toBe(PermissionCatalog::HIGH)->and($toggle->requiresStepUp())->toBeTrue()->and($toggle->requiresApproval())->toBeFalse();
});
