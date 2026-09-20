<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A service handed to another person to look after (the customer's freelancer, agency, colleague): ONE service, named
 * capabilities, optionally until a date. The permission itself lives in resource-scoped policy bindings; this table is
 * the record the owner sees and revokes — who, what, since when, until when, by whom.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_access_grants', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('organization_id', 40)->index();
            $table->string('service_id', 40)->index();
            $table->string('email', 190)->index();               // who was named; the account may not exist yet
            $table->string('user_id', 40)->nullable()->index();  // set when the access becomes active
            $table->json('capabilities');                        // view | manage | console | backups | restore | assistant
            $table->string('state', 12)->default('pending')->index(); // pending (invitation sent) | active | revoked | expired
            $table->string('invitation_id', 40)->nullable();     // the organization invitation that carries the accept link
            $table->string('granted_by', 40)->nullable();
            $table->string('revoked_by', 40)->nullable();
            $table->string('note', 250)->nullable();             // the owner's own note: "agentura, redesign do konce října"
            $table->timestamp('expires_at')->nullable();         // the access ends by itself at this moment
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_access_grants');
    }
};
