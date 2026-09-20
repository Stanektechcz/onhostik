<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A conversation of the assistant is kept under `<who>:<the id the client sends>`: `staff:<user>:<organization>:` (68
 * characters) for a member of staff, `<user>:` (31) for a customer — and the client's own id may be 80 characters. The column
 * took 80 in all. The staff console names a conversation `admin-<timestamp>` (14 characters), which makes 82: SQLite did not
 * mind, on PostgreSQL every question a support agent asked the assistant answered 500.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_ai_runs', function (Blueprint $table): void {
            $table->string('session_id', 160)->nullable()->change();
        });
    }

    public function down(): void
    {
        // narrowing would cut conversation ids that are already stored: the column stays wide
    }
};
