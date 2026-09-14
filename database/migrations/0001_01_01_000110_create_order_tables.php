<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Cart -> quote snapshot -> order (blueprint §78). Consents are immutable evidence (§23.7, §46.3). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('carts', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('organization_id', 40)->nullable()->index();
            $table->string('user_id', 40)->nullable()->index();
            $table->string('session_token', 80)->nullable()->index();
            $table->string('currency', 3)->default('CZK');
            $table->unsignedSmallInteger('commit_months')->default(1);
            $table->string('promo_code', 40)->nullable();
            $table->json('items'); // [{line_id, sku, product_key, plan_key, qty, config, domain?}]
            $table->string('state', 12)->default('open'); // open | converted | abandoned
            $table->string('converted_order_id', 40)->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        Schema::create('quotes', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('organization_id', 40)->nullable()->index();
            $table->string('cart_id', 40)->nullable();
            $table->string('currency', 3);
            $table->json('lines');
            $table->bigInteger('subtotal_minor');
            $table->bigInteger('discount_minor')->default(0);
            $table->bigInteger('tax_minor');
            $table->bigInteger('total_minor');
            $table->bigInteger('renewal_total_minor')->nullable(); // standard renewal shown next to promo (§49.4)
            $table->string('tax_calculation_id', 40)->nullable();
            $table->string('tax_rule_version_id', 40)->nullable();
            $table->json('versions'); // price/plan/terms versions locked into the quote
            $table->timestamp('valid_until');
            $table->string('state', 12)->default('open'); // open | accepted | expired
            $table->timestamps();
        });

        Schema::create('order_sequences', function (Blueprint $table): void {
            $table->unsignedSmallInteger('year')->primary();
            $table->unsignedInteger('next')->default(1000);
        });

        Schema::create('orders', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('number', 24)->unique();
            $table->string('organization_id', 40)->index();
            $table->string('user_id', 40)->nullable();
            $table->string('quote_id', 40)->nullable();
            $table->string('state', 20)->default('NEW');
            $table->string('currency', 3);
            $table->bigInteger('subtotal_minor');
            $table->bigInteger('discount_minor')->default(0);
            $table->bigInteger('tax_minor');
            $table->bigInteger('total_minor');
            $table->string('payment_mode', 12); // wallet | gateway | bank | postpaid
            $table->string('payment_intent_id', 40)->nullable();
            $table->string('wallet_hold_id', 40)->nullable();
            $table->string('invoice_id', 40)->nullable();
            $table->string('promo_code', 40)->nullable();
            $table->string('source', 12)->default('web'); // web | panel | admin | api | partner
            $table->unsignedSmallInteger('commit_months')->default(1);
            $table->json('consents')->nullable(); // consent ids
            $table->string('idempotency_key', 200)->unique();
            $table->timestamp('placed_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('order_items', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('order_id', 40)->index();
            $table->string('sku', 80);
            $table->string('product_key', 60);
            $table->string('plan_version_id', 40)->nullable();
            $table->string('price_id', 40)->nullable();
            $table->string('name', 190);
            $table->unsignedSmallInteger('qty')->default(1);
            $table->bigInteger('unit_net_minor');
            $table->bigInteger('discount_minor')->default(0);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->bigInteger('tax_minor')->default(0);
            $table->bigInteger('total_minor');
            $table->string('period', 8)->default('month');
            $table->json('config')->nullable();
            $table->string('service_id', 40)->nullable()->index();
            $table->string('domain_id', 40)->nullable();
            $table->string('operation_id', 40)->nullable();
            $table->string('state', 16)->default('pending'); // pending | provisioning | active | failed | cancelled | refunded
            $table->timestamps();
        });

        Schema::create('consents', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('organization_id', 40)->nullable()->index();
            $table->string('user_id', 40)->nullable();
            $table->string('order_id', 40)->nullable()->index();
            $table->string('domain_id', 40)->nullable()->index();
            $table->string('kind', 32); // terms | sla | dpa | privacy | registry_terms | registrar_terms | withdrawal_waiver | auto_renew
            $table->string('document_key', 80);
            $table->string('document_version', 40);
            $table->string('document_hash', 64)->nullable();
            $table->string('document_url', 250)->nullable();
            $table->string('language', 5)->default('cs');
            $table->string('person', 190)->nullable(); // the natural person who consented (WAPI `rules`)
            $table->string('ip', 64)->nullable();
            $table->string('user_agent', 250)->nullable();
            $table->timestamp('accepted_at');
            $table->json('evidence')->nullable();
            $table->timestamps();
        });

        Schema::create('consent_documents', function (Blueprint $table): void {
            $table->string('key', 80);
            $table->string('version', 40);
            $table->json('title');
            $table->string('url', 250)->nullable();
            $table->string('hash', 64)->nullable();
            $table->timestamp('effective_from');
            $table->timestamp('effective_to')->nullable();
            $table->boolean('required_for_checkout')->default(false);
            $table->timestamps();
            $table->primary(['key', 'version']);
        });
    }

    public function down(): void
    {
        foreach (['consent_documents', 'consents', 'order_items', 'orders', 'order_sequences', 'quotes', 'carts'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
