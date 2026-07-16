<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('domain_transfer_requests')) {
            Schema::create('domain_transfer_requests', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('customer_id')->index();
                $table->string('domain_name');
                $table->string('auth_code', 100)->nullable();
                $table->enum('status', ['pending', 'processing', 'completed', 'failed', 'cancelled'])->default('pending')->index();
                $table->text('admin_note')->nullable();
                $table->unsignedBigInteger('handled_by')->nullable();
                $table->timestamps();

                $table->foreign('customer_id')->references('id')->on('customers')->cascadeOnDelete();
            });
        }

    }

    public function down(): void
    {
        Schema::dropIfExists('domain_transfer_requests');
    }
};
