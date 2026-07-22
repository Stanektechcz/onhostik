<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Blind-index sidecar for the encrypted customer phone (audit 31).
 *
 * `phone` becomes encrypted at rest; `phone_bidx` holds a keyed HMAC of the
 * normalised number so admins can still find a customer by exact phone without
 * the plaintext being stored or indexed.
 *
 * The encrypted `phone` outgrows its old 32-char column, so widen it. Existing
 * rows are re-encrypted + indexed by `php artisan pii:reindex`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            if (! Schema::hasColumn('customers', 'phone_bidx')) {
                $table->string('phone_bidx', 64)->nullable()->index();
            }
        });

        // Encrypted text no longer fits in string(32).
        if (Schema::hasColumn('customers', 'phone')) {
            Schema::table('customers', function (Blueprint $table): void {
                $table->text('phone')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            if (Schema::hasColumn('customers', 'phone_bidx')) {
                $table->dropColumn('phone_bidx');
            }
        });
    }
};
