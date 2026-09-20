<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An operation forgets the secrets it carried once it has acted (OperationSecrets). The column says that it did — and
 * its absence is how the sweep finds the rows written before this rule: every finished operation of the past still
 * holds the passwords it was given, in plain JSON. `onhost:operations:forget-secrets` works them off in batches.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('operations', function (Blueprint $table): void {
            $table->timestamp('secrets_scrubbed_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('operations', function (Blueprint $table): void {
            $table->dropIndex(['secrets_scrubbed_at']);
            $table->dropColumn('secrets_scrubbed_at');
        });
    }
};
