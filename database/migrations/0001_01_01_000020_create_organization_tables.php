<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('slug', 80)->unique();
            $table->string('name', 190);
            $table->string('type', 12)->default('person'); // person | company
            $table->string('state', 20)->default('active'); // active | suspended | closing | closed
            $table->string('owner_user_id', 40)->index();
            $table->string('partner_organization_id', 40)->nullable()->index(); // reseller parent
            $table->string('ico', 20)->nullable();
            $table->string('dic', 20)->nullable();
            $table->string('vat_id', 20)->nullable();
            $table->string('vat_status', 20)->default('unknown'); // unknown | not_registered | valid | invalid
            $table->timestamp('vat_validated_at')->nullable();
            $table->string('billing_email', 190)->nullable();
            $table->string('street', 190)->nullable();
            $table->string('city', 120)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->string('country', 2)->default('CZ');
            $table->string('currency', 3)->default('CZK');
            $table->string('locale', 5)->default('cs');
            $table->string('billing_mode', 20)->default('prepaid'); // prepaid | postpaid
            $table->string('customer_class', 20)->default('b2c');   // b2c | b2b
            $table->boolean('auto_renew_default')->default(true);
            $table->unsignedInteger('domain_renewal_reserve_days')->default(30);
            $table->string('ui_mode', 20)->default('simple');       // simple | advanced | agency | enterprise
            $table->string('data_region', 10)->default('cz1');
            $table->json('settings')->nullable();
            $table->json('feature_flags')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('organization_memberships', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('organization_id', 40)->index();
            $table->string('user_id', 40)->index();
            $table->string('role_key', 80);
            $table->string('state', 20)->default('active'); // active | invited | suspended
            $table->string('invited_by', 40)->nullable();
            $table->timestamp('joined_at')->nullable();
            $table->timestamps();
            $table->unique(['organization_id', 'user_id']);
        });

        Schema::create('organization_invitations', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('organization_id', 40)->index();
            $table->string('email', 190);
            $table->string('role_key', 80);
            $table->string('token_hash', 64)->unique();
            $table->string('invited_by', 40)->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('projects', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('organization_id', 40)->index();
            $table->string('slug', 80);
            $table->string('name', 190);
            $table->text('description')->nullable();
            $table->string('cost_center', 80)->nullable();
            $table->json('tags')->nullable();
            $table->string('state', 20)->default('active');
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['organization_id', 'slug']);
        });

        Schema::create('project_memberships', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('project_id', 40)->index();
            $table->string('user_id', 40)->index();
            $table->string('role_key', 80);
            $table->timestamps();
            $table->unique(['project_id', 'user_id']);
        });
    }

    public function down(): void
    {
        foreach (['project_memberships', 'projects', 'organization_invitations', 'organization_memberships', 'organizations'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
