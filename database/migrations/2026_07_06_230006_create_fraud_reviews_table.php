<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('fraud_reviews')) {
            Schema::create('fraud_reviews', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
                $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
                $table->unsignedTinyInteger('score');        // 0-100
                $table->json('signals');                     // ['new_customer', 'high_value', 'vpn_ip', …]
                $table->string('status', 20)->default('pending'); // pending | cleared | blocked
                $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->text('reviewer_note')->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->timestamps();

                $table->index(['status', 'created_at']);
            });
        }

    }

    public function down(): void
    {
        Schema::dropIfExists('fraud_reviews');
    }
};
