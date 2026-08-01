<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Customer sub-accounts: pending invitations. The owner invites an email to
 * their account; the invitee follows a single-use, expiring link to accept and
 * (if new) set a password. Only the SHA-256 hash of the token is stored.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('customer_invitations')) {
            return;
        }

        Schema::create('customer_invitations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('invited_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('email');
            $table->string('role', 20)->default('member');
            $table->string('token_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();

            $table->index(['customer_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_invitations');
    }
};
