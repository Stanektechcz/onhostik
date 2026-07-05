<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('monitors', function (Blueprint $table): void {
            $table->string('label')->nullable()->after('name');
        });

        Schema::create('status_page_components', function (Blueprint $table): void {
            $table->id();
            $table->string('group_name', 100)->nullable();
            $table->string('name', 100);
            $table->string('description', 500)->nullable();
            $table->foreignId('monitor_id')->nullable()->constrained('monitors')->nullOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_visible')->default(true);
            $table->timestamps();

            $table->index(['is_visible', 'sort_order']);
        });

        Schema::create('status_page_maintenances', function (Blueprint $table): void {
            $table->id();
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->timestamp('scheduled_start_at');
            $table->timestamp('scheduled_end_at');
            $table->string('status', 20)->default('scheduled');  // scheduled|in_progress|completed
            $table->timestamps();

            $table->index(['status', 'scheduled_start_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('status_page_maintenances');
        Schema::dropIfExists('status_page_components');
        Schema::table('monitors', function (Blueprint $table): void {
            $table->dropColumn('label');
        });
    }
};
