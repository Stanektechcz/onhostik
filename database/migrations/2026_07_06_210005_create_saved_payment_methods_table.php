<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('saved_payment_methods')) {
            Schema::create('saved_payment_methods', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
                $table->string('provider', 30);
                $table->string('label', 100);
                $table->string('token', 200)->nullable();
                $table->string('last4', 4)->nullable();
                $table->string('card_brand', 30)->nullable();
                $table->string('expires_at', 7)->nullable();
                $table->boolean('is_default')->default(false);
                $table->timestamps();
            });
        }

    }

    public function down(): void
    {
        Schema::dropIfExists('saved_payment_methods');
    }
};
