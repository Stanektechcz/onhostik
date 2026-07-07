<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenance_windows', function (Blueprint $table): void {
            $table->text('description')->nullable()->after('title');
            $table->unsignedBigInteger('server_id')->nullable()->index()->after('description');
            $table->boolean('notify_customers')->default(true)->after('server_id');
            $table->enum('status', ['scheduled', 'in_progress', 'completed', 'cancelled'])->default('scheduled')->index()->after('notify_customers');
            $table->unsignedBigInteger('created_by')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_windows', function (Blueprint $table): void {
            $table->dropColumn(['description', 'server_id', 'notify_customers', 'status', 'created_by']);
        });
    }
};
