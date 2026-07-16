<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('vouchers')) {
            Schema::create('vouchers', function (Blueprint $table): void {
                $table->id();
                $table->string('code', 30)->unique();
                $table->enum('type', ['credit', 'discount_percent', 'discount_fixed'])->index();
                $table->bigInteger('value');
                $table->string('currency', 3)->default('CZK');
                $table->unsignedInteger('max_uses')->nullable();
                $table->unsignedInteger('used_count')->default(0);
                $table->date('expires_at')->nullable();
                $table->boolean('is_active')->default(true)->index();
                $table->timestamps();
            });
        }

    }

    public function down(): void
    {
        Schema::dropIfExists('vouchers');
    }
};
