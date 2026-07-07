<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('snapshot_restore_requests', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('service_id')->index();
            $table->unsignedBigInteger('user_id')->index();
            $table->unsignedBigInteger('snapshot_id')->nullable();
            $table->string('restore_point')->nullable();
            $table->enum('status', ['pending', 'approved', 'processing', 'completed', 'rejected'])->default('pending')->index();
            $table->text('customer_note')->nullable();
            $table->text('admin_note')->nullable();
            $table->unsignedBigInteger('handled_by')->nullable();
            $table->timestamps();

            $table->foreign('service_id')->references('id')->on('services')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('snapshot_restore_requests');
    }
};
