<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit §5n: contract changes beyond the model (payout terms, white-label scope, rate locks — applied from the request
 * table), missed monthly deliverables on marketplace subscriptions, and capacity requests the forecast proposes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partner_change_requests', function (Blueprint $table): void {
            $table->timestamp('applied_at')->nullable()->after('effective_from');
        });
        Schema::table('partners', function (Blueprint $table): void {
            $table->string('payout_terms', 12)->default('on_request')->after('model_effective_from'); // on_request | monthly | quarterly
            $table->string('whitelabel_scope', 8)->default('basic')->after('payout_terms');           // basic | full
        });
        Schema::table('marketplace_orders', function (Blueprint $table): void {
            $table->timestamp('period_delivered_at')->nullable()->after('late_credit_minor'); // the monthly deliverable of the running period (§5n-2)
            $table->timestamp('period_warned_at')->nullable()->after('period_delivered_at');
            $table->unsignedSmallInteger('missed_periods')->default(0)->after('period_warned_at');
        });
        Schema::create('capacity_requests', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('role', 12)->index();
            $table->string('region_code', 16)->nullable();
            $table->string('provider_instance_id', 40)->nullable()->index();
            $table->string('state', 12)->default('proposed'); // proposed | approved | ordered | delivered | cancelled | failed
            $table->integer('days_left')->nullable();
            $table->integer('headroom_mb')->default(0);
            $table->integer('wanted_ram_mb')->default(0);
            $table->integer('wanted_cpu_cores')->default(0);
            $table->integer('wanted_disk_gb')->default(0);
            $table->string('node_name', 80)->nullable();
            $table->string('node_id', 40)->nullable();
            $table->string('remote_id', 80)->nullable();
            $table->string('note', 500)->nullable();
            $table->string('decided_by', 40)->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamp('ordered_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->index(['role', 'region_code', 'state']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('capacity_requests');
        Schema::table('marketplace_orders', function (Blueprint $table): void {
            $table->dropColumn(['period_delivered_at', 'period_warned_at', 'missed_periods']);
        });
        Schema::table('partners', function (Blueprint $table): void {
            $table->dropColumn(['payout_terms', 'whitelabel_scope']);
        });
        Schema::table('partner_change_requests', function (Blueprint $table): void {
            $table->dropColumn('applied_at');
        });
    }
};
