<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('invoice_number_sequences')) {
            Schema::create('invoice_number_sequences', function (Blueprint $table): void {
                $table->id();
                $table->string('series', 8);                          // CZ | EU | INT
                $table->unsignedSmallInteger('year');
                $table->unsignedInteger('last_number')->default(0);
                $table->timestamps();

                $table->unique(['series', 'year']);
            });
        }


        if (!Schema::hasTable('invoices')) {
            Schema::create('invoices', function (Blueprint $table): void {
                $table->id();
                $table->uuid()->unique();
                $table->foreignId('customer_id')->constrained()->restrictOnDelete();
                $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('parent_invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
                $table->string('type', 16)->default('proforma');      // proforma|invoice|credit_note
                $table->string('series', 8);
                $table->string('number', 24)->unique();               // CZ-2026-000001
                $table->string('status', 16)->default('draft')->index();
                $table->string('vat_scenario', 16);
                $table->char('currency', 3);
                $table->bigInteger('subtotal');
                $table->bigInteger('tax_amount');
                $table->bigInteger('total');
                $table->string('variable_symbol', 10)->nullable()->index();
                $table->date('issue_date')->nullable();
                $table->date('taxable_supply_date')->nullable();      // DUZP
                $table->date('due_date')->nullable()->index();
                $table->timestamp('paid_at')->nullable();
                $table->string('pdf_path')->nullable();
                $table->text('notes')->nullable();
                // --- immutable billing snapshot ---
                $table->string('snapshot_name');
                $table->string('snapshot_company')->nullable();
                $table->string('snapshot_street');
                $table->string('snapshot_city');
                $table->string('snapshot_zip', 16);
                $table->char('snapshot_country_code', 2);
                $table->string('snapshot_vat_number', 20)->nullable();
                $table->string('snapshot_registration_number', 20)->nullable();
                $table->timestamps();

                $table->index(['customer_id', 'status']);
                $table->index(['status', 'due_date']);
                $table->index('created_at');
            });
        }


        if (!Schema::hasTable('invoice_items')) {
            Schema::create('invoice_items', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
                $table->string('description');
                $table->unsignedSmallInteger('quantity')->default(1);
                $table->char('currency', 3);
                $table->bigInteger('unit_price');
                $table->decimal('vat_rate', 5, 2)->default(0);
                $table->bigInteger('total');
                $table->timestamp('created_at')->nullable();
            });
        }

    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('invoice_number_sequences');
    }
};
