<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_retry_schedules', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('invoice_id')->index();
            $table->unsignedBigInteger('customer_id')->index();
            $table->timestamp('retry_at');
            $table->enum('status', ['pending', 'attempted', 'succeeded', 'cancelled'])->default('pending')->index();
            $table->unsignedTinyInteger('attempt_number')->default(1);
            $table->text('note')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('invoice_id')->references('id')->on('invoices')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_retry_schedules');
    }
};
