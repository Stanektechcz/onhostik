<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A certificate authority rate-limits. Let's Encrypt refuses a duplicate certificate after five of the same set in a
 * week and an account after three hundred new orders in three hours, and it says so with HTTP 429 and a `Retry-After`.
 * The platform read that as an ordinary failure and asked again — which is exactly what a rate limit is there to stop.
 * Until when the authority does not want to hear from us about this certificate is written here, so the renewal sweep
 * and the auto-issuer step over it instead of spending the customer's operation attempts on a refusal.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('managed_certificates', function (Blueprint $table): void {
            $table->timestamp('rate_limited_until')->nullable()->after('renew_after');
        });
    }

    public function down(): void
    {
        Schema::table('managed_certificates', function (Blueprint $table): void {
            $table->dropColumn('rate_limited_until');
        });
    }
};
