<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('blog_posts', function (Blueprint $table): void {
            $table->string('locale', 2)->default('cs')->after('slug')->index();
        });

        Schema::table('kb_articles', function (Blueprint $table): void {
            $table->string('locale', 2)->default('cs')->after('slug')->index();
        });

        // Set existing rows to Czech
        \DB::table('blog_posts')->whereNull('locale')->update(['locale' => 'cs']);
        \DB::table('kb_articles')->whereNull('locale')->update(['locale' => 'cs']);
    }

    public function down(): void
    {
        Schema::table('blog_posts', function (Blueprint $table): void {
            $table->dropColumn('locale');
        });

        Schema::table('kb_articles', function (Blueprint $table): void {
            $table->dropColumn('locale');
        });
    }
};
