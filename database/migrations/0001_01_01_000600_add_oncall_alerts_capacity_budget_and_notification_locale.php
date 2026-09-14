<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit §5q: on-call alerts with acknowledgement and escalation (§5q-1), the vendor cost of a capacity request behind
 * the monthly budget cap (§5q-5), the language an in-app notification was written in (§5q-7).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('oncall_alerts', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('event', 80)->index();
            $table->string('dedup_key', 190)->index();       // one open alert per event + aggregate
            $table->string('severity', 8)->default('warn');   // info | warn | hot → provider severity
            $table->string('state', 12)->default('open');     // open | acked | escalated | resolved
            $table->string('title', 250);
            $table->text('body')->nullable();
            $table->string('surface', 250)->nullable();
            $table->string('provider', 20)->nullable();       // pagerduty | opsgenie | webhook
            $table->string('provider_ref', 190)->nullable();  // the vendor's incident/alert id
            $table->unsignedSmallInteger('escalations')->default(0);
            $table->timestamp('escalated_at')->nullable();
            $table->timestamp('escalate_after')->nullable();  // the next escalation deadline while unacknowledged
            $table->string('acked_by', 80)->nullable();       // user id, or `provider:<name>` for an inbound acknowledgement
            $table->timestamp('acked_at')->nullable();
            $table->string('resolved_by', 80)->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->index(['state', 'escalate_after']);
        });
        Schema::table('capacity_requests', function (Blueprint $table): void {
            $table->integer('cost_minor')->nullable()->after('remote_id');   // the vendor's monthly price of the ordered type (§5q-5)
            $table->string('cost_currency', 3)->nullable()->after('cost_minor');
        });
        Schema::table('notifications', function (Blueprint $table): void {
            $table->string('locale', 5)->default('cs')->after('severity'); // the language the title and body are in (§5q-7)
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table): void {
            $table->dropColumn('locale');
        });
        Schema::table('capacity_requests', function (Blueprint $table): void {
            $table->dropColumn(['cost_minor', 'cost_currency']);
        });
        Schema::dropIfExists('oncall_alerts');
    }
};
