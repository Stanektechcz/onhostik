<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Access that ends on a date (Brain card H343): a lecturer, a contractor, an auditor. The policy binding carries the
 * same moment, so the permission stops at that second on its own; these columns are what the scheduled pass reads to
 * remove the membership afterwards — and with it the panel accounts and SSH keys that were the person's.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organization_memberships', function (Blueprint $table): void {
            $table->timestamp('expires_at')->nullable()->index();
        });
        Schema::table('project_memberships', function (Blueprint $table): void {
            $table->timestamp('expires_at')->nullable()->index();
        });
        Schema::table('organization_invitations', function (Blueprint $table): void {
            $table->timestamp('access_expires_at')->nullable(); // when the membership ends — not when the invitation does
        });
    }

    public function down(): void
    {
        Schema::table('organization_invitations', fn (Blueprint $table) => $table->dropColumn('access_expires_at'));
        Schema::table('project_memberships', function (Blueprint $table): void {
            $table->dropIndex(['expires_at']);
            $table->dropColumn('expires_at');
        });
        Schema::table('organization_memberships', function (Blueprint $table): void {
            $table->dropIndex(['expires_at']);
            $table->dropColumn('expires_at');
        });
    }
};
