<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit §5r-1: the on-call rota — who carries the pager from when to when, so an alert names its person and the daily
 * digest shows the hand-over.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('oncall_shifts', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('user_id', 40)->index();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->string('note', 250)->nullable();
            $table->string('created_by', 80)->nullable();
            $table->timestamps();
            $table->index(['starts_at', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('oncall_shifts');
    }
};
