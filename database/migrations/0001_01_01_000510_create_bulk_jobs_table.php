<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bulk staff actions (audit §5e-7): one job = one action applied to every service a filter selects (a node, an
 * instance, a customer, a product). The job keeps the per-service outcome (operation id or refusal) and staff follow
 * its progress from the operations the workflows create.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bulk_jobs', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('action', 60);
            $table->json('params')->nullable();
            $table->json('filter')->nullable();                    // provider_instance_id, node_id, organization_id, product_key, family, service_ids
            $table->string('state', 16)->default('running');       // running | finished
            $table->unsignedInteger('total')->default(0);
            $table->unsignedInteger('refused')->default(0);         // services that refused the action up front (feature off, state)
            $table->json('items')->nullable();                     // [{service_id, operation_id|null, error|null}]
            $table->string('actor_id', 40)->nullable();
            $table->text('reason')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
            $table->index(['state', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bulk_jobs');
    }
};
