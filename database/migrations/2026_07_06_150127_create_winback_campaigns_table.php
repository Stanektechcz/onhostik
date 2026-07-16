<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('winback_campaigns')) {
            Schema::create('winback_campaigns', function (Blueprint $table): void {
                $table->id();
                $table->string('name', 150);
                $table->string('target_segment', 30)->default('churned');
                $table->text('message');
                $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
                $table->timestamp('sent_at')->nullable();
                $table->integer('sent_count')->default(0);
                $table->timestamps();
            });
        }

    }

    public function down(): void
    {
        Schema::dropIfExists('winback_campaigns');
    }
};
