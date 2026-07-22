<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Web push subscriptions (audit 92).
 *
 * One row per browser/device a user has granted push permission to. The
 * endpoint is the push service URL; p256dh/auth are the client's public key
 * material used to encrypt the payload (aes128gcm) so only that device can read
 * it. No message content is stored here.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('push_subscriptions')) {
            return;
        }

        Schema::create('push_subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->text('endpoint');
            $table->string('endpoint_hash', 64); // sha256(endpoint) — TEXT can't be uniquely indexed
            $table->string('public_key');   // p256dh
            $table->string('auth_token');    // auth secret
            $table->timestamps();

            // One subscription per endpoint per user; a re-subscribe updates it.
            $table->unique(['user_id', 'endpoint_hash'], 'push_sub_user_endpoint_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_subscriptions');
    }
};
