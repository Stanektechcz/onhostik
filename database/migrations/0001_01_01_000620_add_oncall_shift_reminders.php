<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Audit §5t-4: the reminder an hour before an on-call shift is sent once; imported shifts remember their iCal UID. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('oncall_shifts', function (Blueprint $table): void {
            $table->timestamp('reminded_at')->nullable()->after('note');
            $table->string('ical_uid', 190)->nullable()->after('reminded_at')->index();
        });
    }

    public function down(): void
    {
        Schema::table('oncall_shifts', function (Blueprint $table): void {
            $table->dropColumn(['reminded_at', 'ical_uid']);
        });
    }
};
