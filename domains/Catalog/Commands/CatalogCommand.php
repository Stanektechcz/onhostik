<?php

declare(strict_types=1);

namespace Onhost\Domain\Catalog\Commands;

use Onhost\Domain\Identity\Authorization\PermissionCatalog;
use Onhost\Domain\Identity\Authorization\RiskAwareCommand;
use Onhost\Platform\Commands\GlobalCommand;

/**
 * Staff pricing controls (Nastavení systému → Slevy a doplňky, Tarify, Životní cyklus), dispatched by `op`:
 *  pricing.commit_discounts.set{config,base?,reason?} · pricing.regions.set{regions,base?,reason?} · pricing.domain_discount.set{tld,discount,reason?} ·
 *  pricing.domain_discount.delete{tld} · pricing.addon_products.set{product_key,addon_products} · promo.upsert{promo,reason?} · promo.delete{code} ·
 *  option.upsert{product_key,option,reason?} · option.delete{product_key,key,reason?} · product.state{state: active|draft, products: list} ·
 *  plan.publish{product_key,plan_key,base_version?,entitlements?,limits?,features?,prices?,reason,confirm_large_change?,keep_promos? (CLI revisions only; the console API does not accept it)} ·
 *  plan.activate_version{product_key,plan_key,version,base_version?,reason} · lifecycle.set{config,base?,reason?} · panel_nav.set{config} ·
 *  product.describe{product_key,description{cs,en},base?} · product.create{product_key,reason?} (only a product CatalogRevisions::PRODUCTS defines)
 *
 * Who it takes (owner decision 13, 2026-09-25; docs/runbooks/approvals.md): HIGH is a fresh step-up and nothing more, but every
 * change of a price or a plan takes a second person as well, although catalog.manage itself is only HIGH. Withdrawing an offer
 * (a discount, a promo code, a product off sale) is one person with a step-up: it can only return to a list price somebody
 * already approved, and the emergency brake must not wait for a second person. The panel sidebar is an ordinary edit. An
 * operation this list does not know is a price change: a new operation is four-eyes until somebody classifies it here.
 * With ONHOST_FOUR_EYES=false (one operator) the authorizer waives the second person and the audit says so.
 */
final class CatalogCommand extends GlobalCommand implements RiskAwareCommand
{
    public const OPS = ['pricing.commit_discounts.set', 'pricing.regions.set', 'pricing.domain_discount.set', 'pricing.domain_discount.delete', 'pricing.addon_products.set', 'promo.upsert', 'promo.delete', 'option.upsert', 'option.delete', 'panel_nav.set', 'product.state', 'plan.publish', 'plan.activate_version', 'lifecycle.set', 'product.describe', 'product.create'];

    /**
     * Classified four-eyes on purpose (not by the fail-closed default): a new product on sale is a new offer at a price, even when
     * its price comes from elsewhere (TASK-0022 limit-raise: the parent's option price).
     */
    public const APPROVAL_OPS = ['product.create'];

    /**
     * Withdrawals and the composition of an offer from products already on sale at approved prices: one person, a step-up.
     * A product's description is customer-facing copy, neither a price nor a plan (TASK-0022 catalog-versions): a step-up.
     */
    public const STEP_UP_OPS = ['pricing.domain_discount.delete', 'promo.delete', 'pricing.addon_products.set', 'product.describe'];

    /** Neither a price nor an offer. */
    public const ORDINARY_OPS = ['panel_nav.set'];

    protected const AUDIT_STRIP = [];

    public function op(): string
    {
        return (string) $this->get('op');
    }

    public function permission(): ?string
    {
        return 'catalog.manage';
    }

    public function name(): string
    {
        return 'catalog.'.$this->op();
    }

    public function riskLevel(): string
    {
        return match (true) {
            in_array($this->op(), self::ORDINARY_OPS, true) => PermissionCatalog::NORMAL,
            in_array($this->op(), self::APPROVAL_OPS, true) => PermissionCatalog::CRITICAL,
            in_array($this->op(), self::STEP_UP_OPS, true), $this->isWithdrawal() => PermissionCatalog::HIGH,
            default => PermissionCatalog::CRITICAL, // prices, plans, unknown operations
        };
    }

    public function requiresStepUp(): bool
    {
        return $this->riskLevel() !== PermissionCatalog::NORMAL;
    }

    /** The explicit second person: catalog.manage is HIGH, so the authorizer would not ask for one by the permission alone. */
    public function requiresApproval(): bool
    {
        return $this->riskLevel() === PermissionCatalog::CRITICAL;
    }

    /**
     * Taking something off sale: a promo code paused or retired (only an active code is redeemed, and making it active again
     * is an upsert of the whole code that needs the second person), a product put back to draft.
     */
    private function isWithdrawal(): bool
    {
        return match ($this->op()) {
            'promo.upsert' => in_array($this->get('promo.state'), ['paused', 'retired'], true),
            'product.state' => $this->get('state') === 'draft',
            default => false,
        };
    }
}
