<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Onhost\Domain\Incidents\Models\SlaCreditPolicy;

/**
 * L-05 (TASK-0145): the SLA credit policies follow `onhost.sla.credit_policies` — now the SLA in force (2026-09): Standard 5 % and
 * Business 10 % of the monthly price per started hour over the monthly limit, capped at 50 % / 100 %. A deploy runs migrations but
 * not seeders, so the new versions are written here (the previous ones are superseded, credits keep referencing them). On a fresh
 * database there is nothing to supersede and the seeder finds the versions already in place.
 */
return new class extends Migration
{
    public function up(): void
    {
        SlaCreditPolicy::syncFromConfig();
    }

    public function down(): void
    {
        // a policy version is a record credits point at: it is never deleted; reverting the config and seeding writes a new version
    }
};
