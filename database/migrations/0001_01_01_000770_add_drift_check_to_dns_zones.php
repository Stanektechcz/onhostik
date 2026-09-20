<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What a DNS provider serves is compared with what the platform holds, every night (`onhost:dns:drift`). `DnsService::drift()`
 * existed and nobody called it: a record changed at the provider by hand, a commit that reached the provider only in part
 * (the WEDOS zone API takes rows one by one), a zone somebody deleted there — none of it was ever noticed. The zone keeps
 * when it was last compared, what differed, and why it could not be compared when the provider did not answer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dns_zones', function (Blueprint $table): void {
            $table->timestamp('drift_checked_at')->nullable()->index();
            $table->json('drift')->nullable();
            $table->string('drift_error', 32)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('dns_zones', function (Blueprint $table): void {
            $table->dropIndex(['drift_checked_at']);
            $table->dropColumn(['drift_checked_at', 'drift', 'drift_error']);
        });
    }
};
