<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * G7 (TASK-0115): rotating a webhook signing secret took effect at once, so every delivery between the rotation and the moment
 * the receiver had the new secret failed its signature check. The secret a rotation replaces is kept, encrypted like the secret
 * itself, until `previous_secret_expires_at` and signs X-ONhost-Signature-Previous meanwhile (WebhookSigner::headers).
 *
 * Two nullable columns, no existing row written, no lock beyond the ALTER of a small table; an old release ignores them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('webhook_endpoints', function (Blueprint $table): void {
            $table->text('previous_secret')->nullable();                   // encrypted; the secret the last rotation replaced
            $table->timestamp('previous_secret_expires_at')->nullable();   // until when it still signs (then ignored)
        });
    }

    public function down(): void
    {
        Schema::table('webhook_endpoints', function (Blueprint $table): void {
            $table->dropColumn(['previous_secret', 'previous_secret_expires_at']);
        });
    }
};
