<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Finance core (blueprint §62–§65): immutable double-entry ledger, wallets with
 * holds, top-ups, refunds, adjustments, credit lines, budgets, payments,
 * settlements/reconciliation, invoicing + tax engine, subscriptions, usage
 * metering/rating, dunning.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── Ledger ──────────────────────────────────────────────────────────
        Schema::create('ledger_accounts', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('code', 160)->unique();
            $table->string('type', 12);   // asset | liability | revenue | expense | equity
            $table->string('kind', 32);   // wallet | promo_wallet | receivable | bank | clearing | revenue | tax_payable | refund | registrar_cost | fee | write_off
            $table->string('currency', 3);
            $table->string('organization_id', 40)->nullable()->index();
            $table->string('state', 12)->default('active');
            $table->timestamps();
        });

        Schema::create('ledger_transactions', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('kind', 32)->index();
            $table->string('reference_type', 60)->nullable();
            $table->string('reference_id', 60)->nullable();
            $table->string('organization_id', 40)->nullable()->index();
            $table->string('currency', 3);
            $table->string('description', 250)->nullable();
            $table->string('idempotency_key', 200)->unique();
            $table->string('reversal_of', 40)->nullable()->index();
            $table->string('created_by', 60)->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('posted_at')->index();
            $table->timestamp('created_at')->nullable();
            $table->index(['reference_type', 'reference_id']);
        });

        Schema::create('ledger_postings', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('transaction_id', 40)->index();
            $table->string('account_id', 40)->index();
            $table->string('direction', 6); // debit | credit
            $table->bigInteger('amount_minor'); // always positive
            $table->string('currency', 3);
            $table->string('organization_id', 40)->nullable();
            $table->timestamp('created_at')->nullable();
        });

        // ── Wallets ─────────────────────────────────────────────────────────
        Schema::create('wallets', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('organization_id', 40)->index();
            $table->string('currency', 3);
            $table->string('kind', 12)->default('main'); // main | promo
            $table->string('state', 12)->default('active'); // active | frozen | closed
            $table->bigInteger('posted_balance_minor')->default(0);
            $table->bigInteger('reserved_balance_minor')->default(0);
            $table->bigInteger('accrued_unbilled_minor')->default(0);
            $table->bigInteger('low_balance_threshold_minor')->nullable();
            $table->timestamp('low_balance_notified_at')->nullable();
            $table->timestamps();
            $table->unique(['organization_id', 'currency', 'kind']);
        });

        Schema::create('wallet_holds', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('wallet_id', 40)->index();
            $table->string('organization_id', 40)->index();
            $table->bigInteger('amount_minor');
            $table->string('currency', 3);
            $table->string('purpose', 32); // order | domain_renewal | renewal | resize | metered_reserve
            $table->string('reference_type', 60)->nullable();
            $table->string('reference_id', 60)->nullable();
            $table->string('priority', 12)->default('normal'); // normal | domain
            $table->string('state', 12)->default('active'); // active | captured | released | expired
            $table->timestamp('expires_at')->nullable();
            $table->string('captured_transaction_id', 40)->nullable();
            $table->string('idempotency_key', 200)->unique();
            $table->string('created_by', 60)->nullable();
            $table->timestamps();
            $table->index(['reference_type', 'reference_id']);
        });

        Schema::create('wallet_topups', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('wallet_id', 40)->index();
            $table->string('organization_id', 40)->index();
            $table->bigInteger('amount_minor');
            $table->string('currency', 3);
            $table->string('source', 16); // card | bank | admin | promo | auto | partner
            $table->string('bucket', 12)->default('purchased'); // purchased | promo
            $table->boolean('refundable')->default(true);
            $table->string('payment_intent_id', 40)->nullable()->index();
            $table->string('state', 12)->default('pending'); // pending | completed | failed | reversed
            $table->string('transaction_id', 40)->nullable();
            $table->string('invoice_id', 40)->nullable();
            $table->string('note', 250)->nullable();
            $table->string('idempotency_key', 200)->unique();
            $table->string('created_by', 60)->nullable();
            $table->timestamps();
        });

        Schema::create('wallet_refunds', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('wallet_id', 40)->index();
            $table->string('organization_id', 40)->index();
            $table->bigInteger('amount_minor');
            $table->string('currency', 3);
            $table->string('reason', 250);
            $table->string('destination', 16)->default('source'); // source | bank | credit_note
            $table->string('payment_intent_id', 40)->nullable();
            $table->string('payment_refund_id', 40)->nullable();
            $table->string('state', 12)->default('pending'); // pending | completed | failed
            $table->string('transaction_id', 40)->nullable();
            $table->string('approval_id', 40)->nullable();
            $table->string('idempotency_key', 200)->unique();
            $table->string('created_by', 60)->nullable();
            $table->timestamps();
        });

        Schema::create('wallet_adjustments', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('wallet_id', 40)->index();
            $table->string('organization_id', 40)->index();
            $table->bigInteger('amount_minor'); // signed
            $table->string('currency', 3);
            $table->string('reason', 250);
            $table->string('ticket_ref', 80)->nullable();
            $table->string('approval_id', 40)->nullable();
            $table->string('transaction_id', 40)->nullable();
            $table->string('idempotency_key', 200)->unique();
            $table->string('created_by', 60)->nullable();
            $table->timestamps();
        });

        Schema::create('credit_lines', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('organization_id', 40)->index();
            $table->string('currency', 3);
            $table->bigInteger('limit_minor');
            $table->bigInteger('risk_hold_minor')->default(0);
            $table->string('state', 12)->default('requested'); // requested | approved | suspended | closed
            $table->string('approved_by', 40)->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('review_at')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
        });

        Schema::create('auto_topup_settings', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('wallet_id', 40)->unique();
            $table->string('organization_id', 40)->index();
            $table->boolean('enabled')->default(false);
            $table->bigInteger('threshold_minor');
            $table->bigInteger('amount_minor');
            $table->unsignedTinyInteger('max_per_day')->default(2);
            $table->bigInteger('monthly_limit_minor');
            $table->string('payment_method_id', 40)->nullable();
            $table->timestamp('last_triggered_at')->nullable();
            $table->unsignedSmallInteger('consecutive_failures')->default(0);
            $table->timestamps();
        });

        Schema::create('budgets', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('organization_id', 40)->index();
            $table->string('project_id', 40)->nullable()->index();
            $table->string('currency', 3);
            $table->bigInteger('limit_minor');
            $table->boolean('hard')->default(false);
            $table->json('alert_thresholds'); // [50,75,90,100]
            $table->bigInteger('max_single_service_minor')->nullable();
            $table->bigInteger('approval_above_minor')->nullable();
            $table->bigInteger('spent_minor')->default(0);
            $table->date('period_start');
            $table->json('notified')->nullable();
            $table->timestamps();
        });

        // ── Payments ────────────────────────────────────────────────────────
        Schema::create('payment_methods', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('organization_id', 40)->index();
            $table->string('provider', 20);
            $table->text('provider_token'); // encrypted
            $table->string('kind', 16); // card | bank | apple_pay | google_pay
            $table->string('brand', 24)->nullable();
            $table->string('last4', 4)->nullable();
            $table->string('expires', 7)->nullable(); // MM/YYYY
            $table->boolean('is_default')->default(false);
            $table->string('state', 12)->default('active');
            $table->timestamps();
        });

        Schema::create('payment_intents', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('organization_id', 40)->index();
            $table->string('provider', 20)->index();
            $table->string('provider_id', 120)->nullable()->index();
            $table->string('purpose', 16); // topup | invoice | order
            $table->string('reference_type', 60)->nullable();
            $table->string('reference_id', 60)->nullable();
            $table->bigInteger('amount_minor');
            $table->string('currency', 3);
            $table->string('state', 28)->default('CREATED');
            $table->string('method', 24)->nullable();
            $table->string('redirect_url', 500)->nullable();
            $table->json('return_urls')->nullable();
            $table->string('idempotency_key', 200)->unique();
            $table->timestamp('paid_at')->nullable();
            $table->string('failure_reason', 250)->nullable();
            $table->bigInteger('refunded_minor')->default(0);
            $table->json('raw')->nullable(); // masked provider payload
            $table->string('created_by', 60)->nullable();
            $table->timestamps();
            $table->index(['reference_type', 'reference_id']);
        });

        Schema::create('payment_events', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('provider', 20);
            $table->string('event_id', 190);
            $table->string('provider_id', 120)->nullable()->index();
            $table->string('payment_intent_id', 40)->nullable()->index();
            $table->string('type', 60)->nullable();
            $table->boolean('signature_ok')->default(false);
            $table->json('payload')->nullable(); // masked
            $table->timestamp('processed_at')->nullable();
            $table->string('result', 60)->nullable();
            $table->timestamps();
            $table->unique(['provider', 'event_id']);
        });

        Schema::create('payment_refunds', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('payment_intent_id', 40)->index();
            $table->string('provider_refund_id', 120)->nullable();
            $table->bigInteger('amount_minor');
            $table->string('currency', 3);
            $table->string('state', 16)->default('pending'); // pending | succeeded | failed
            $table->string('reason', 250)->nullable();
            $table->string('idempotency_key', 200)->unique();
            $table->string('wallet_refund_id', 40)->nullable();
            $table->string('created_by', 60)->nullable();
            $table->timestamps();
        });

        Schema::create('settlements', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('provider', 20);
            $table->date('period_start');
            $table->date('period_end');
            $table->string('currency', 3);
            $table->unsignedInteger('items_count')->default(0);
            $table->bigInteger('total_minor')->default(0);
            $table->bigInteger('fee_minor')->default(0);
            $table->string('state', 16)->default('imported'); // imported | reconciled | mismatch
            $table->timestamps();
        });

        Schema::create('settlement_items', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('settlement_id', 40)->index();
            $table->string('provider_id', 120)->index();
            $table->bigInteger('amount_minor');
            $table->bigInteger('fee_minor')->default(0);
            $table->string('currency', 3);
            $table->timestamp('settled_at')->nullable();
            $table->string('matched_payment_intent_id', 40)->nullable();
            $table->string('state', 16)->default('unmatched'); // matched | unmatched | mismatch
            $table->timestamps();
        });

        Schema::create('reconciliation_runs', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('kind', 24); // payments | ledger | invoices | bank | registrar
            $table->timestamp('period_start');
            $table->timestamp('period_end');
            $table->string('state', 16)->default('completed'); // completed | mismatch
            $table->json('summary')->nullable();
            $table->unsignedInteger('mismatches')->default(0);
            $table->string('created_by', 60)->nullable();
            $table->timestamps();
        });

        Schema::create('reconciliation_items', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('run_id', 40)->index();
            $table->string('kind', 40);
            $table->string('reference', 190)->nullable();
            $table->bigInteger('expected_minor')->nullable();
            $table->bigInteger('actual_minor')->nullable();
            $table->string('note', 500)->nullable();
            $table->string('state', 12)->default('open'); // open | resolved | ignored
            $table->string('resolved_by', 40)->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('bank_statement_lines', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('account', 64);
            $table->bigInteger('amount_minor');
            $table->string('currency', 3);
            $table->string('variable_symbol', 20)->nullable()->index();
            $table->string('counterparty', 190)->nullable();
            $table->string('message', 250)->nullable();
            $table->timestamp('booked_at');
            $table->string('external_id', 190)->unique();
            $table->string('matched_payment_intent_id', 40)->nullable();
            $table->string('state', 16)->default('unmatched');
            $table->timestamps();
        });

        // ── Invoicing & tax ─────────────────────────────────────────────────
        Schema::create('legal_entities', function (Blueprint $table): void {
            $table->string('key', 40)->primary();
            $table->string('name', 190);
            $table->string('ico', 20)->nullable();
            $table->string('dic', 20)->nullable();
            $table->string('vat_id', 20)->nullable();
            $table->json('address');
            $table->string('country', 2);
            $table->string('iban', 40)->nullable();
            $table->string('bic', 16)->nullable();
            $table->string('bank_account', 40)->nullable();
            $table->json('series'); // {"invoice":"FV","credit_note":"DK","proforma":"PF","receipt":"PP"}
            $table->boolean('vat_payer')->default(true);
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('invoice_sequences', function (Blueprint $table): void {
            $table->id();
            $table->string('legal_entity', 40);
            $table->string('series', 8);
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('next')->default(1);
            $table->unique(['legal_entity', 'series', 'year']);
        });

        Schema::create('invoices', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('number', 32)->nullable()->unique();
            $table->string('legal_entity', 40);
            $table->string('series', 8);
            $table->string('type', 16); // invoice | proforma | credit_note | receipt | correction
            $table->string('organization_id', 40)->index();
            $table->string('order_id', 40)->nullable()->index();
            $table->string('corrects_invoice_id', 40)->nullable()->index();
            $table->string('currency', 3);
            $table->string('state', 20)->default('DRAFT'); // DRAFT | ISSUED | PAID | OVERDUE | CANCELLED | CREDITED
            $table->bigInteger('subtotal_minor')->default(0);
            $table->bigInteger('discount_minor')->default(0);
            $table->bigInteger('tax_minor')->default(0);
            $table->bigInteger('total_minor')->default(0);
            $table->bigInteger('paid_minor')->default(0);
            $table->timestamp('issued_at')->nullable();
            $table->date('supply_date')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->json('buyer')->nullable();   // immutable snapshot
            $table->json('seller')->nullable();  // immutable snapshot
            $table->string('tax_calculation_id', 40)->nullable();
            $table->json('tax_summary')->nullable(); // per rate/category
            $table->string('payment_reference', 20)->nullable()->index(); // variable symbol
            $table->string('payment_method', 24)->nullable();
            $table->json('structured')->nullable(); // EN16931 mapping
            $table->string('pdf_path', 250)->nullable();
            $table->string('pdf_hash', 64)->nullable();
            $table->string('einvoice_state', 16)->default('not_required'); // not_required | pending | delivered | failed
            $table->text('note')->nullable();
            $table->json('meta')->nullable();
            $table->string('created_by', 60)->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'state']);
        });

        Schema::create('invoice_lines', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('invoice_id', 40)->index();
            $table->unsignedSmallInteger('position');
            $table->string('sku', 80)->nullable();
            $table->string('description', 250);
            $table->decimal('qty', 12, 4)->default(1);
            $table->string('unit', 16)->default('ks');
            $table->bigInteger('unit_net_minor');
            $table->bigInteger('discount_minor')->default(0);
            $table->bigInteger('net_minor');
            $table->decimal('tax_rate', 5, 2);
            $table->string('tax_category', 4)->default('S'); // UNCL5305: S | Z | E | AE | K | O
            $table->bigInteger('tax_minor');
            $table->bigInteger('total_minor');
            $table->date('period_from')->nullable();
            $table->date('period_to')->nullable();
            $table->string('service_id', 40)->nullable();
            $table->string('order_item_id', 40)->nullable();
            $table->timestamps();
        });

        Schema::create('tax_rule_versions', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->unsignedInteger('version')->unique();
            $table->timestamp('effective_from');
            $table->timestamp('effective_to')->nullable();
            $table->json('rules');
            $table->string('state', 12)->default('active');
            $table->text('note')->nullable();
            $table->string('created_by', 60)->nullable();
            $table->timestamps();
        });

        Schema::create('tax_registrations', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('legal_entity', 40)->index();
            $table->string('country', 2);
            $table->string('vat_id', 20)->nullable();
            $table->string('kind', 24); // domestic | oss | local
            $table->date('valid_from');
            $table->date('valid_to')->nullable();
            $table->timestamps();
        });

        Schema::create('vat_validations', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('organization_id', 40)->nullable()->index();
            $table->string('vat_id', 20)->index();
            $table->boolean('valid');
            $table->string('name', 250)->nullable();
            $table->string('address', 500)->nullable();
            $table->string('consultation_number', 80)->nullable();
            $table->string('source', 12)->default('vies');
            $table->json('raw')->nullable();
            $table->timestamp('checked_at');
            $table->timestamps();
        });

        Schema::create('tax_calculations', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('rule_version_id', 40)->index();
            $table->string('organization_id', 40)->nullable()->index();
            $table->json('inputs');
            $table->json('result');
            $table->bigInteger('total_tax_minor')->default(0);
            $table->string('currency', 3);
            $table->timestamps();
        });

        Schema::create('oss_periods', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('legal_entity', 40);
            $table->string('quarter', 8); // 2026-Q3
            $table->string('state', 12)->default('open'); // open | closed | filed
            $table->json('totals')->nullable(); // per country: net, tax
            $table->timestamp('filed_at')->nullable();
            $table->timestamps();
            $table->unique(['legal_entity', 'quarter']);
        });

        Schema::create('einvoice_deliveries', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('invoice_id', 40)->index();
            $table->string('provider', 20);
            $table->string('delivery_id', 120)->nullable();
            $table->string('state', 16)->default('pending'); // pending | sent | delivered | failed
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->string('last_error', 500)->nullable();
            $table->timestamp('next_attempt_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
        });

        // ── Subscriptions, metering, dunning ────────────────────────────────
        Schema::create('subscriptions', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('organization_id', 40)->index();
            $table->string('service_id', 40)->nullable()->index();
            $table->string('domain_id', 40)->nullable()->index();
            $table->string('plan_version_id', 40)->nullable();
            $table->string('price_id', 40)->nullable();
            $table->string('currency', 3);
            $table->string('period', 8); // month | year
            $table->bigInteger('amount_minor');   // net renewal amount
            $table->string('state', 16)->default('active'); // active | past_due | paused | cancelled | expired
            $table->timestamp('current_period_start');
            $table->timestamp('current_period_end');
            $table->timestamp('next_renewal_at')->index();
            $table->boolean('auto_renew')->default(true);
            $table->boolean('cancel_at_period_end')->default(false);
            $table->string('renewal_priority', 12)->default('normal'); // normal | domain
            $table->unsignedSmallInteger('renewal_failures')->default(0);
            $table->timestamp('last_renewed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('usage_events', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('service_id', 40)->index();
            $table->string('organization_id', 40)->index();
            $table->string('metric', 40); // vm_hours | gpu_seconds | tokens | traffic_gb | storage_gb_hours | ipv4_hours | game_days | build_minutes
            $table->decimal('quantity', 18, 6);
            $table->string('unit', 16);
            $table->timestamp('period_start');
            $table->timestamp('period_end');
            $table->string('source', 40)->nullable();
            $table->string('dedupe_key', 200)->unique();
            $table->boolean('rated')->default(false)->index();
            $table->timestamps();
        });

        Schema::create('rated_usage', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('usage_event_id', 40)->unique();
            $table->string('organization_id', 40)->index();
            $table->string('service_id', 40)->index();
            $table->string('price_id', 40)->nullable();
            $table->bigInteger('unit_price_minor');
            $table->bigInteger('amount_minor');
            $table->string('currency', 3);
            $table->string('billing_period_id', 40)->nullable()->index();
            $table->string('charged_transaction_id', 40)->nullable();
            $table->string('invoice_id', 40)->nullable();
            $table->timestamps();
        });

        Schema::create('billing_periods', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('organization_id', 40)->index();
            $table->string('currency', 3);
            $table->date('period_start');
            $table->date('period_end');
            $table->string('state', 12)->default('open'); // open | closed | invoiced
            $table->bigInteger('total_minor')->default(0);
            $table->json('cap_applied')->nullable();
            $table->string('invoice_id', 40)->nullable();
            $table->timestamps();
            $table->unique(['organization_id', 'currency', 'period_start']);
        });

        Schema::create('dunning_cases', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('organization_id', 40)->index();
            $table->string('invoice_id', 40)->nullable()->index();
            $table->string('service_id', 40)->nullable()->index();
            $table->string('state', 24)->default('DUE');
            $table->timestamp('due_at');
            $table->timestamp('next_action_at')->nullable()->index();
            $table->json('notices_sent')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->timestamp('termination_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('dunning_actions', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('case_id', 40)->index();
            $table->string('action', 24); // notice | limit | suspend | resume | schedule_termination | terminate | escalate
            $table->timestamp('performed_at');
            $table->string('result', 60)->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach ([
            'dunning_actions', 'dunning_cases', 'billing_periods', 'rated_usage', 'usage_events', 'subscriptions',
            'einvoice_deliveries', 'oss_periods', 'tax_calculations', 'vat_validations', 'tax_registrations', 'tax_rule_versions',
            'invoice_lines', 'invoices', 'invoice_sequences', 'legal_entities', 'bank_statement_lines', 'reconciliation_items',
            'reconciliation_runs', 'settlement_items', 'settlements', 'payment_refunds', 'payment_events', 'payment_intents',
            'payment_methods', 'budgets', 'auto_topup_settings', 'credit_lines', 'wallet_adjustments', 'wallet_refunds',
            'wallet_topups', 'wallet_holds', 'wallets', 'ledger_postings', 'ledger_transactions', 'ledger_accounts',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
