<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Wholesale (cost) prices per registrar and TLD, refreshed from registrar price APIs
 * or maintained by staff; the cheapest registrar wins new registrations and transfers.
 * `registrar_contacts.source_contact_id` links the per-registrar copies of one contact.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registrar_tld_costs', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('registrar_provider', 24);
            $table->string('provider_instance_id', 40)->nullable()->index();
            $table->string('tld', 32);
            $table->string('currency', 3);
            $table->bigInteger('register_minor')->nullable();
            $table->bigInteger('renew_minor')->nullable();
            $table->bigInteger('transfer_minor')->nullable();
            $table->bigInteger('restore_minor')->nullable();
            $table->string('source', 12)->default('api'); // api | manual | seed
            $table->timestamp('fetched_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->unique(['registrar_provider', 'tld']);
        });
        Schema::table('registrar_contacts', function (Blueprint $table): void {
            $table->string('source_contact_id', 40)->nullable()->index();
        });
        Schema::table('registrar_operations', function (Blueprint $table): void {
            $table->string('registrar_provider', 24)->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('registrar_operations', fn (Blueprint $table) => $table->dropColumn('registrar_provider'));
        Schema::table('registrar_contacts', fn (Blueprint $table) => $table->dropColumn('source_contact_id'));
        Schema::dropIfExists('registrar_tld_costs');
    }
};
