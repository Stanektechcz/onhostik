<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Customer-connected registrar accounts (bring your own WEDOS API): the credentials live in the secret store under
 * `db://registrar-connections/<id>`, the connection owns two customer-scoped provider instances (registrar + DNS zones)
 * and mirrors the account's domains into `domains` so renewals, DNS and hosting pairing are automated from the panel.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registrar_connections', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('organization_id', 40)->index();
            $table->string('provider', 24)->default('wedos');      // wedos (Subreg later)
            $table->string('label', 120);
            $table->string('login', 190);                          // the account login (e-mail); never the password
            $table->string('customer_number', 40)->nullable();
            $table->string('secret_ref', 200);                     // db://registrar-connections/<id>
            $table->string('registrar_instance_id', 40)->nullable();
            $table->string('dns_instance_id', 40)->nullable();
            $table->string('state', 16)->default('pending');       // pending | active | error | disabled
            $table->json('settings')->nullable();                  // auto_sync, sync_dns, notices, credit_threshold_minor, pair_service_id
            $table->json('stats')->nullable();                     // domains, zones, credit, last_probe
            $table->json('runs')->nullable();                      // last sync runs (history shown in the panel)
            $table->text('last_error')->nullable();
            $table->timestamp('last_probed_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->string('created_by', 40)->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'provider']); // one live connection per login is enforced by the service (disconnected rows stay for history)
        });

        Schema::table('provider_instances', function (Blueprint $table): void {
            $table->string('organization_id', 40)->nullable()->index()->after('key'); // customer-owned instances (registrar connections) never serve platform work
        });
    }

    public function down(): void
    {
        Schema::table('provider_instances', function (Blueprint $table): void {
            $table->dropColumn('organization_id');
        });
        Schema::dropIfExists('registrar_connections');
    }
};
