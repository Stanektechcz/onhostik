<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            // B2B purchase order number (customer-supplied, printed on invoice)
            $table->string('purchase_order_number', 100)->nullable()->after('notes');
            // Free-text reference for the customer's own accounting system
            $table->string('custom_reference', 255)->nullable()->after('purchase_order_number');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropColumn(['purchase_order_number', 'custom_reference']);
        });
    }
};
