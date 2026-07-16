<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('service_customer_notes')) {
            Schema::create('service_customer_notes', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('service_id')->index();
                $table->unsignedBigInteger('user_id')->index();
                $table->text('content');
                $table->timestamps();

                $table->foreign('service_id')->references('id')->on('services')->cascadeOnDelete();
            });
        }

    }

    public function down(): void
    {
        Schema::dropIfExists('service_customer_notes');
    }
};
