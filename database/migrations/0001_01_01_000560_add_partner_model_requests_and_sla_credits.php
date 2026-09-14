<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** §5m: partner commission-model change requests (finance decides, effective next month), late-delivery credits on marketplace orders. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partner_change_requests', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('partner_id', 40)->index();
            $table->string('kind', 20)->default('model');            // model
            $table->string('from_value', 20)->nullable();
            $table->string('to_value', 20);
            $table->string('state', 12)->default('requested');      // requested | approved | rejected
            $table->text('note')->nullable();
            $table->text('decision_note')->nullable();
            $table->string('requested_by', 40)->nullable();
            $table->string('decided_by', 40)->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->date('effective_from')->nullable();
            $table->timestamps();
            $table->index(['partner_id', 'state']);
        });
        Schema::table('partners', function (Blueprint $table): void {
            $table->string('pending_model', 8)->nullable();
            $table->date('model_effective_from')->nullable();
        });
        Schema::table('marketplace_orders', function (Blueprint $table): void {
            $table->bigInteger('late_credit_minor')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('marketplace_orders', function (Blueprint $table): void {
            $table->dropColumn('late_credit_minor');
        });
        Schema::table('partners', function (Blueprint $table): void {
            $table->dropColumn(['pending_model', 'model_effective_from']);
        });
        Schema::dropIfExists('partner_change_requests');
    }
};
