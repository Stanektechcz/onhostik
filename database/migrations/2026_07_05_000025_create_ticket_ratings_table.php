<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_ratings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('support_ticket_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('score');              // 1–5
            $table->text('comment')->nullable();
            $table->timestamp('rated_at')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index('score');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_ratings');
    }
};
