<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * TASK-0235: the daily settlement reconciliation runs once per provider and day. The provider was only inside `summary`; it becomes
 * a column so the database itself refuses a second run of one provider for one period start (two scheduler hosts, a manual run next
 * to the scheduled one) — no shared cache is relied on. Ad-hoc runs (a single mismatch opened outside the daily job) carry no
 * provider; NULLs are distinct in a unique index on SQLite and PostgreSQL, so they never collide. Existing runs get the provider
 * their summary names; a duplicate (provider, period start) — none is expected, nothing called the reconciliation before — keeps
 * the first and leaves the later ones without a provider.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reconciliation_runs', function (Blueprint $table): void {
            $table->string('provider', 32)->nullable();
        });
        $seen = [];
        foreach (DB::table('reconciliation_runs')->where('kind', 'payments')->orderBy('created_at')->orderBy('id')->get(['id', 'period_start', 'summary']) as $run) {
            $provider = json_decode((string) $run->summary, true)['provider'] ?? null;
            if (! is_string($provider) || $provider === '' || strlen($provider) > 32 || isset($seen[$provider.'|'.$run->period_start])) {
                continue;
            }
            $seen[$provider.'|'.$run->period_start] = true;
            DB::table('reconciliation_runs')->where('id', $run->id)->update(['provider' => $provider]);
        }
        Schema::table('reconciliation_runs', function (Blueprint $table): void {
            $table->unique(['kind', 'provider', 'period_start'], 'reconciliation_runs_provider_period_unique');
        });
    }

    public function down(): void
    {
        Schema::table('reconciliation_runs', function (Blueprint $table): void {
            $table->dropUnique('reconciliation_runs_provider_period_unique');
        });
        Schema::table('reconciliation_runs', function (Blueprint $table): void {
            $table->dropColumn('provider');
        });
    }
};
