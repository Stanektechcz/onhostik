<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One-click install needs to know HOW to install each app. Until now the
 * marketplace only described apps (name, icon, price) and the "installation"
 * was fabricated, so an app row carried no recipe at all.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketplace_apps', function (Blueprint $table): void {
            // How the payload arrives: a tarball/zip URL, or a git repository.
            $table->string('install_source', 20)->default('archive')->after('description');
            $table->string('install_url', 500)->nullable()->after('install_source');
            // Some archives unpack into a versioned folder we have to flatten.
            $table->string('archive_subdir', 100)->nullable()->after('install_url');
            // Subfolder of the site root, empty = install at the root.
            $table->string('install_path', 100)->nullable()->after('archive_subdir');
            $table->boolean('requires_database')->default(false)->after('install_path');
            // Whitelisted build commands run after unpacking, one per line.
            $table->text('post_install_commands')->nullable()->after('requires_database');
            $table->string('docs_url', 300)->nullable()->after('post_install_commands');
        });

        Schema::table('app_installations', function (Blueprint $table): void {
            // Where it actually landed and what it needs — shown to the customer
            // after install so they aren't left guessing the admin URL.
            $table->string('install_path', 200)->nullable()->after('version');
            $table->string('database_name', 100)->nullable()->after('install_path');
            $table->text('last_output')->nullable()->after('database_name');
            $table->text('last_error')->nullable()->after('last_output');
            $table->boolean('was_dry_run')->default(false)->after('last_error');
        });
    }

    public function down(): void
    {
        Schema::table('marketplace_apps', function (Blueprint $table): void {
            $table->dropColumn([
                'install_source', 'install_url', 'archive_subdir', 'install_path',
                'requires_database', 'post_install_commands', 'docs_url',
            ]);
        });

        Schema::table('app_installations', function (Blueprint $table): void {
            $table->dropColumn(['install_path', 'database_name', 'last_output', 'last_error', 'was_dry_run']);
        });
    }
};
