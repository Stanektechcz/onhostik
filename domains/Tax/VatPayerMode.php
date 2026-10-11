<?php

declare(strict_types=1);

namespace Onhost\Domain\Tax;

use Onhost\Domain\Invoicing\Models\LegalEntity;
use Onhost\Domain\Tax\Models\TaxRuleVersion;

/**
 * Whether the seller is a VAT payer (G2, owner decision G-R1) — one answer for the tax engine, the documents and the reports.
 *
 * The mode is declared in configuration (`vat.payer`, ONHOST_VAT_PAYER) and switched on the legal entity (`legal_entities.vat_payer`)
 * by finance through the staff API with a step-up and a second person (SetVatPayerModeCommand, CRITICAL); the legal
 * entity is what documents are issued in, and the active tax rules can only narrow it (`supplier.vat_payer: false`). A seller who
 * is no VAT payer charges no VAT (the tax engine decides 0 % "E") and issues no tax document (§ 29). A document keeps the seller
 * it was frozen with: nothing here is read for a document that was already issued.
 */
final class VatPayerMode
{
    /** The mode documents are issued in now: the legal entity and the tax rules in force both say "payer". */
    public function isPayer(): bool
    {
        return self::legalEntityIsPayer() && $this->rulesSayPayer();
    }

    /** The container key of the per-request memo of the legal entity's mode (scoped: a web request, a queue job). */
    private const MEMO = 'onhost.tax.vat_payer_mode';

    /**
     * The legal entity's mode (a platform without its legal entity yet — a fresh install, a narrow test — counts as what
     * ONHOST_VAT_PAYER declares: H0, owner decision H-R0 — it used to count as a payer whatever the declaration said). Read once per request or queue job (the tax engine asks for every line it decides): the memo is a scoped
     * instance, so the next request and the next job read the row again, and a change of the legal entity drops it at once
     * (LegalEntity's saved event, the mode switch).
     */
    public static function legalEntityIsPayer(): bool
    {
        $app = app();
        if (! $app->bound(self::MEMO)) {
            $app->scoped(self::MEMO, fn () => new \ArrayObject);
        }
        /** @var \ArrayObject<string, bool> $memo */
        $memo = $app->make(self::MEMO);
        $key = (string) config('onhost.billing.legal_entity', 'onhost-cz');
        if (! $memo->offsetExists($key)) {
            $entity = self::legalEntity();
            $memo[$key] = $entity === null ? (bool) config('vat.payer', false) : (bool) $entity->vat_payer;
        }

        return (bool) $memo[$key];
    }

    /** Drops the memo: the legal entity changed. */
    public static function forget(): void
    {
        if (app()->bound(self::MEMO)) {
            app()->forgetInstance(self::MEMO);
        }
    }

    public static function legalEntity(): ?LegalEntity
    {
        return LegalEntity::query()->find((string) config('onhost.billing.legal_entity', 'onhost-cz'));
    }

    /**
     * What the doctor and `onhost:vat:payer-mode` show: the declared mode, the legal entity's, the tax rules', the mode in force,
     * and the way out when they disagree.
     *
     * @return array{in_force:bool, declared:bool, legal_entity:?bool, rules:bool, consistent:bool, detail:string, remedy:string}
     */
    public function report(): array
    {
        $entity = self::legalEntity();
        $declared = (bool) config('vat.payer', false);
        $rules = $this->rulesSayPayer();
        $inForce = $this->isPayer();
        $remedy = '';
        if ($entity === null) {
            $remedy = 'run LegalEntitySeeder (php artisan onhost:production:prepare --legal)';
        } elseif ((bool) $entity->vat_payer !== $declared) {
            $remedy = 'ONHOST_VAT_PAYER='.($declared ? 'true' : 'false').' is not the legal entity\'s mode yet — a member of finance switches it with a step-up and a second person: POST /v1/staff/tax/vat-payer-mode {payer, reason} (docs/runbooks/vat-payer-mode.md)';
        } elseif ($declared && ! $rules) {
            $remedy = 'the active tax rules say supplier.vat_payer=false — publish a tax rule version that agrees with ONHOST_VAT_PAYER';
        }
        $mode = $inForce ? 'VAT payer: tax documents with VAT' : 'not a VAT payer: no VAT, no tax documents';
        $detail = $mode.' · legal entity '.($entity === null ? 'missing' : ($entity->key.' '.($entity->vat_payer ? 'payer' : 'non-payer'))).' · ONHOST_VAT_PAYER '.($declared ? 'true' : 'false').' · tax rules '.($rules ? 'payer' : 'non-payer');

        return ['in_force' => $inForce, 'declared' => $declared, 'legal_entity' => $entity === null ? null : (bool) $entity->vat_payer, 'rules' => $rules, 'consistent' => $remedy === '', 'detail' => $detail, 'remedy' => $remedy];
    }

    private function rulesSayPayer(): bool
    {
        $version = TaxRuleVersion::query()->where('state', 'active')->where('effective_from', '<=', now())
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>', now()))->orderByDesc('version')->first();

        return $version === null || (bool) data_get($version->rules, 'supplier.vat_payer', true);
    }
}
