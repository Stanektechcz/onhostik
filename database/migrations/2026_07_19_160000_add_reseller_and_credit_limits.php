<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * K109 reseller sub-customer cap + D41 corporate payment terms.
 *
 * K109: a reseller could attach unlimited sub-customers. That is a commercial
 * term ("your plan covers 50 customers"), and without a cap there was no way
 * to sell tiers or to stop one reseller consuming the fleet.
 *
 * D41: every invoice used the same proforma validity window. A corporate
 * customer on net-30 was therefore "overdue" from day 11 — dunning chased
 * them, and late fees applied, for an invoice that was contractually fine.
 * Nullable everywhere: absent means "use the default", which keeps every
 * existing customer behaving exactly as before.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('reseller_profiles', 'max_customers')) {
            Schema::table('reseller_profiles', function (Blueprint $table): void {
                // NULL = unlimited. A hard 0 would silently lock an existing
                // reseller out of their own customers on deploy.
                $table->unsignedInteger('max_customers')->nullable();
            });
        }

        Schema::table('customers', function (Blueprint $table): void {
            if (! Schema::hasColumn('customers', 'payment_terms_days')) {
                // NULL = fall back to billing.proforma_validity_days.
                $table->unsignedSmallInteger('payment_terms_days')->nullable();
            }

            if (! Schema::hasColumn('customers', 'credit_limit')) {
                // Minor units, like every other money column. NULL = no credit
                // facility (the current behaviour: pay before provisioning).
                $table->unsignedBigInteger('credit_limit')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('reseller_profiles', 'max_customers')) {
            Schema::table('reseller_profiles', function (Blueprint $table): void {
                $table->dropColumn('max_customers');
            });
        }

        Schema::table('customers', function (Blueprint $table): void {
            foreach (['payment_terms_days', 'credit_limit'] as $column) {
                if (Schema::hasColumn('customers', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
