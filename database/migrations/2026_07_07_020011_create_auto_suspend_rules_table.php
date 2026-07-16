<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('auto_suspend_rules')) {
            Schema::create('auto_suspend_rules', function (Blueprint $table): void {
                $table->id();
                $table->string('name', 100);
                $table->enum('trigger', ['overdue_days', 'usage_percent', 'failed_payments'])->index();
                $table->unsignedSmallInteger('threshold_value');
                $table->boolean('is_active')->default(true)->index();
                $table->text('description')->nullable();
                $table->timestamps();
            });
        }

    }

    public function down(): void
    {
        Schema::dropIfExists('auto_suspend_rules');
    }
};
