<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('gdpr_requests')) {
            Schema::create('gdpr_requests', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
                $table->string('type', 20);     // export | deletion
                $table->string('status', 20)->default('pending'); // pending | processing | completed | rejected
                $table->text('admin_note')->nullable();
                $table->string('file_path', 500)->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();

                $table->index(['customer_id', 'type']);
                $table->index('status');
            });
        }

    }

    public function down(): void
    {
        Schema::dropIfExists('gdpr_requests');
    }
};
