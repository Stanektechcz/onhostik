<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ecosystem block (audit §5j): the marketplace of partner services (listings fulfilled by partners, paid from credit,
 * settled through the commission engine), customer-to-customer referrals on the loyalty rails, and the referral code
 * on the organization. Missions, streaks, the status page, the sandbox flag and the green footprint live in existing
 * columns (`organizations.settings` / `feature_flags`, `nodes.tags`, `invoices.meta`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketplace_listings', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('partner_id', 40)->index();
            $table->string('key', 60)->unique();                    // slug the customer orders by, e.g. wp-care-basic
            $table->string('title', 120);
            $table->text('description')->nullable();
            $table->string('category', 30)->default('care');        // care | seo | security | backup | migration | development
            $table->bigInteger('price_minor');
            $table->string('currency', 3)->default('CZK');
            $table->string('billing', 10)->default('oneoff');       // oneoff | monthly
            $table->unsignedTinyInteger('commission_pct')->default(20); // platform share, snapshotted on the order
            $table->unsignedSmallInteger('delivery_days')->default(5);
            $table->string('state', 12)->default('draft');          // draft | published | paused | retired
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->index(['state', 'category']);
        });

        Schema::create('marketplace_orders', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('listing_id', 40)->index();
            $table->string('partner_id', 40)->index();
            $table->string('organization_id', 40)->index();
            $table->string('ordered_by', 40)->nullable();
            $table->string('invoice_id', 40)->nullable();
            $table->string('service_id', 40)->nullable();            // the customer's service the work concerns, when given
            $table->string('state', 12)->default('ordered');        // ordered | in_progress | delivered | accepted | disputed | cancelled
            $table->text('brief')->nullable();
            $table->text('delivery_note')->nullable();
            $table->text('dispute_reason')->nullable();
            $table->bigInteger('price_minor');
            $table->string('currency', 3)->default('CZK');
            $table->bigInteger('commission_minor')->default(0);      // the platform's share
            $table->bigInteger('partner_minor')->default(0);         // what the partner earns (a payable commission row)
            $table->timestamp('due_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'state']);
            $table->index(['partner_id', 'state']);
        });

        Schema::create('referrals', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('referrer_organization_id', 40)->index();
            $table->string('referred_organization_id', 40)->unique(); // one referrer per new organization, bound at registration
            $table->string('code', 16);
            $table->string('state', 12)->default('pending');        // pending | rewarded | refused
            $table->string('reason', 120)->nullable();
            $table->string('registration_ip', 45)->nullable();
            $table->timestamp('rewarded_at')->nullable();
            $table->timestamps();
        });

        Schema::table('organizations', function (Blueprint $table): void {
            $table->string('referral_code', 16)->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table): void {
            $table->dropColumn('referral_code');
        });
        Schema::dropIfExists('referrals');
        Schema::dropIfExists('marketplace_orders');
        Schema::dropIfExists('marketplace_listings');
    }
};
