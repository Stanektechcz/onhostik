<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Data-driven, versioned product catalog (blueprint §79): products, plans, plan
 * versions (immutable entitlement snapshots), prices with promo/renewal side by
 * side (§49.4), configurator options, TLD policies and domain prices.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('key', 60)->unique();
            $table->string('family', 24)->index(); // web | managed | apps | apps_isolated | cloud | data | game | mail | dns | domain | ai | infra | partner | addon
            $table->json('name');
            $table->json('description')->nullable();
            $table->string('executor', 24)->nullable(); // ispconfig | aapanel | proxmox | pterodactyl | kubernetes | powerdns | wedos | manual
            $table->string('billing_model', 20)->default('subscription'); // subscription | metered | hourly | daily | one_time | domain
            $table->string('state', 16)->default('active'); // draft | active | sunset | retired
            $table->unsignedSmallInteger('sort')->default(100);
            $table->json('meta')->nullable(); // marketing chips, configurator coefficients, sla class defaults
            $table->timestamps();
        });

        Schema::create('plans', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('product_id', 40)->index();
            $table->string('key', 60);
            $table->json('name');
            $table->json('description')->nullable();
            $table->string('sla_class', 16)->default('standard');
            $table->boolean('highlighted')->default(false);
            $table->string('state', 16)->default('active');
            $table->unsignedSmallInteger('sort')->default(100);
            $table->unsignedInteger('current_version')->default(1);
            $table->timestamps();
            $table->unique(['product_id', 'key']);
        });

        Schema::create('plan_versions', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('plan_id', 40)->index();
            $table->unsignedInteger('version');
            $table->json('entitlements'); // real resource limits shown to the customer (§43.7)
            $table->json('limits')->nullable(); // abuse/fair-use controls
            $table->json('features')->nullable();
            $table->timestamp('effective_from');
            $table->timestamp('effective_to')->nullable();
            $table->string('created_by', 40)->nullable();
            $table->timestamps();
            $table->unique(['plan_id', 'version']);
        });

        Schema::create('prices', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('plan_version_id', 40)->index();
            $table->string('currency', 3);
            $table->string('period', 8); // month | year | hour | day | once
            $table->bigInteger('amount_minor');                 // net, first period
            $table->bigInteger('renewal_amount_minor')->nullable(); // net, standard renewal (null = same)
            $table->bigInteger('setup_minor')->default(0);
            $table->bigInteger('promo_amount_minor')->nullable();
            $table->unsignedSmallInteger('promo_periods')->nullable();
            $table->bigInteger('monthly_cap_minor')->nullable(); // for hourly/daily: bill = min(sum, cap)
            $table->json('included')->nullable(); // included traffic, ipv4, backups
            $table->timestamp('effective_from');
            $table->timestamp('effective_to')->nullable();
            $table->string('state', 16)->default('active');
            $table->timestamps();
            $table->unique(['plan_version_id', 'currency', 'period']);
        });

        Schema::create('product_options', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('product_id', 40)->index();
            $table->string('key', 60);
            $table->string('kind', 12); // slider | addon | select
            $table->json('label');
            $table->string('unit', 24)->nullable();
            $table->decimal('min', 12, 2)->nullable();
            $table->decimal('max', 12, 2)->nullable();
            $table->decimal('step', 12, 2)->nullable();
            $table->decimal('default_value', 12, 2)->nullable();
            $table->json('price_per_unit_minor'); // {"CZK": 4500, "EUR": 180}
            $table->json('choices')->nullable();
            $table->json('meta')->nullable();
            $table->unsignedSmallInteger('sort')->default(100);
            $table->timestamps();
            $table->unique(['product_id', 'key']);
        });

        Schema::create('tld_policies', function (Blueprint $table): void {
            $table->string('tld', 32)->primary();
            $table->string('registrar_provider', 24)->default('wedos');
            $table->boolean('registrable')->default(true);
            $table->json('periods'); // [1,2,3,5,10]
            $table->unsignedTinyInteger('default_period')->default(1);
            $table->string('transfer_mode', 8)->default('async'); // sync | async
            $table->string('contact_schema', 16)->default('generic'); // cz | eu | sk | pl | generic
            $table->boolean('nsset_required')->default(false);
            $table->boolean('dnssec_supported')->default(true);
            $table->boolean('idn')->default(false);
            $table->unsignedSmallInteger('grace_days')->default(0);
            $table->unsignedSmallInteger('redemption_days')->default(0);
            $table->unsignedSmallInteger('async_sla_hours')->default(72);
            $table->string('registry_terms_url', 250)->nullable();
            $table->string('registrar_terms_url', 250)->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('domain_prices', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('tld', 32)->index();
            $table->string('currency', 3);
            $table->bigInteger('register_minor');
            $table->bigInteger('renew_minor');
            $table->bigInteger('transfer_minor');
            $table->bigInteger('restore_minor')->nullable();
            $table->bigInteger('cost_minor')->nullable(); // registrar cost for margin ledger
            $table->string('cost_currency', 3)->nullable();
            $table->timestamp('effective_from');
            $table->timestamp('effective_to')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->index(['tld', 'currency', 'effective_from']);
        });

        Schema::create('promo_codes', function (Blueprint $table): void {
            $table->string('code', 40)->primary();
            $table->string('kind', 12); // percent | fixed
            $table->decimal('value', 12, 2);
            $table->string('currency', 3)->nullable();
            $table->timestamp('valid_from')->nullable();
            $table->timestamp('valid_to')->nullable();
            $table->unsignedInteger('max_uses')->nullable();
            $table->unsignedInteger('uses')->default(0);
            $table->json('applies_to')->nullable(); // product families / keys
            $table->boolean('first_period_only')->default(true);
            $table->string('state', 12)->default('active');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['promo_codes', 'domain_prices', 'tld_policies', 'product_options', 'prices', 'plan_versions', 'plans', 'products'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
