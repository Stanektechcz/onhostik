<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('affiliate_commissions')) {
            Schema::create('affiliate_commissions', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
                $table->string('affiliate_code', 50);
                $table->string('source', 100)->nullable();
                $table->unsignedInteger('commission_haler')->default(0);
                $table->enum('status', ['pending', 'approved', 'paid', 'cancelled'])->default('pending');
                $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
                $table->timestamp('paid_at')->nullable();
                $table->timestamps();
            });
        }

    }

    public function down(): void
    {
        Schema::dropIfExists('affiliate_commissions');
    }
};
