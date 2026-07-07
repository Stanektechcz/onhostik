<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_merges', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('primary_customer_id')->index();
            $table->unsignedBigInteger('merged_customer_id')->index();
            $table->enum('status', ['pending', 'completed', 'rolled_back'])->default('pending')->index();
            $table->json('transferred_entities')->nullable();
            $table->unsignedBigInteger('performed_by')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_merges');
    }
};
