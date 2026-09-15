<?php

declare(strict_types=1);

namespace Onhost\Domain\Catalog\Commands;

use Onhost\Domain\Identity\Authorization\PermissionCatalog;
use Onhost\Domain\Identity\Authorization\RiskAwareCommand;
use Onhost\Platform\Commands\GlobalCommand;

/**
 * Staff pricing controls (Nastavení systému → Slevy a doplňky), dispatched by `op`:
 *  pricing.commit_discounts.set{config} · pricing.domain_discount.set{tld,discount} · pricing.domain_discount.delete{tld} ·
 *  pricing.addon_products.set{product_key,addon_products} · promo.upsert{promo} · promo.delete{code} ·
 *  option.upsert{product_key,option} · option.delete{product_key,key} · product.state{state: active|draft, products: list}
 */
final class CatalogCommand extends GlobalCommand implements RiskAwareCommand
{
    public const OPS = ['pricing.commit_discounts.set', 'pricing.domain_discount.set', 'pricing.domain_discount.delete', 'pricing.addon_products.set', 'promo.upsert', 'promo.delete', 'option.upsert', 'option.delete', 'panel_nav.set', 'product.state'];

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
        return PermissionCatalog::NORMAL;
    }

    public function requiresStepUp(): bool
    {
        return false;
    }

    public function requiresApproval(): bool
    {
        return false;
    }
}
