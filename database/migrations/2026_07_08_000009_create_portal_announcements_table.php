<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('portal_announcements', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->text('body');
            $table->enum('type', ['info', 'warning', 'success', 'danger'])->default('info')->index();
            $table->enum('target_audience', ['all', 'customers', 'resellers'])->default('all');
            $table->boolean('is_published')->default(false)->index();
            $table->datetime('published_at')->nullable();
            $table->datetime('expires_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portal_announcements');
    }
};
