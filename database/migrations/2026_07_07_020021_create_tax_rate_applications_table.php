<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('tax_rate_applications')) {
            Schema::create('tax_rate_applications', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('tax_rate_id')->index();
                $table->unsignedBigInteger('invoice_id')->index();
                $table->unsignedBigInteger('customer_id')->index();
                $table->decimal('rate_applied', 5, 2);
                $table->bigInteger('tax_amount');
                $table->string('currency', 3)->default('CZK');
                $table->timestamps();

                $table->foreign('tax_rate_id')->references('id')->on('tax_rates');
                $table->foreign('invoice_id')->references('id')->on('invoices')->cascadeOnDelete();
            });
        }

    }

    public function down(): void
    {
        Schema::dropIfExists('tax_rate_applications');
    }
};
