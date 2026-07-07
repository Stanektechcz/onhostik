<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kb_articles', function (Blueprint $table): void {
            $table->unsignedInteger('views_count')->default(0)->after('slug');
        });
    }

    public function down(): void
    {
        Schema::table('kb_articles', function (Blueprint $table): void {
            $table->dropColumn('views_count');
        });
    }
};
