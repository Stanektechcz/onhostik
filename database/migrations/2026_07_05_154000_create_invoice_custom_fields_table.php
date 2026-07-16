<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Self-heal: a previous run failed on MySQL mid-way (auto-generated
        // unique-index name exceeded 64 chars) leaving the first table behind
        // without the migration being recorded. Drop the partial leftovers.
        Schema::dropIfExists('invoice_custom_field_values');
        Schema::dropIfExists('invoice_field_definitions');

        // Global field definitions managed by admin
        if (!Schema::hasTable('invoice_field_definitions')) {
            Schema::create('invoice_field_definitions', function (Blueprint $table): void {
                $table->id();
                $table->string('key', 60)->unique();   // machine key, e.g. 'po_number'
                $table->string('label', 120);          // human label, e.g. 'PO číslo'
                $table->string('type')->default('text'); // text | number | date
                $table->boolean('is_required')->default(false);
                $table->boolean('show_on_invoice')->default(true);
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }


        // Per-invoice values for custom fields
        if (!Schema::hasTable('invoice_custom_field_values')) {
            Schema::create('invoice_custom_field_values', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
                $table->foreignId('invoice_field_definition_id')->constrained('invoice_field_definitions')->cascadeOnDelete();
                $table->text('value')->nullable();
                $table->timestamps();

                // Explicit name: the auto-generated one exceeds MySQL's 64-char limit.
                $table->unique(['invoice_id', 'invoice_field_definition_id'], 'icfv_invoice_definition_unique');
            });
        }

    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_custom_field_values');
        Schema::dropIfExists('invoice_field_definitions');
    }
};
