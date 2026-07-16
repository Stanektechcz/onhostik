<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('service_cancellations')) {
            Schema::create('service_cancellations', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('service_id')->constrained('services')->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('reason', 50);
                $table->text('feedback')->nullable();
                $table->timestamps();

                $table->index(['reason', 'created_at']);
            });
        }

    }

    public function down(): void
    {
        Schema::dropIfExists('service_cancellations');
    }
};
