<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Notifications & Communication Center (blueprint §72): versioned templates, in-app feed, mail outbox, webhooks, preferences. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_templates', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('key', 60);            // welcome | ticket-ack | invoice | incident | domain-renewal …
            $table->string('channel', 12);        // mail | inapp | sms | push
            $table->string('locale', 5);
            $table->unsignedInteger('version')->default(1);
            $table->string('subject', 250)->nullable();
            $table->text('body');                 // plain text with {{placeholders}}; mail bodies may use simple markdown
            $table->json('variables')->nullable(); // documented placeholders
            $table->boolean('mandatory')->default(false); // legal/security notices ignore preferences
            $table->string('state', 12)->default('active');
            $table->timestamps();
            $table->unique(['key', 'channel', 'locale', 'version']);
        });

        Schema::create('notifications', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('organization_id', 40)->nullable()->index();
            $table->string('user_id', 40)->nullable()->index();
            $table->string('audience', 12)->index();  // customer | internal
            $table->string('kind', 40)->index();      // order | invoice | incident | ticket | security | domain | service | dunning
            $table->string('event', 80)->nullable();  // originating outbox event name
            $table->string('ref_type', 40)->nullable();
            $table->string('ref_id', 80)->nullable();
            $table->string('title', 250);
            $table->text('body')->nullable();
            $table->string('surface', 250)->nullable(); // deep link the surfaces open
            $table->string('severity', 8)->default('info'); // info | warn | hot
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->index(['audience', 'read_at']);
        });

        Schema::create('mail_outbox', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('organization_id', 40)->nullable()->index();
            $table->string('template_key', 60);
            $table->string('locale', 5)->default('cs');
            $table->string('to', 190);
            $table->string('subject', 250);
            $table->json('vars')->nullable();
            $table->string('ref_type', 40)->nullable();
            $table->string('ref_id', 80)->nullable();
            $table->string('state', 12)->default('queued'); // queued | sent | failed | skipped
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->string('last_error', 250)->nullable();
            $table->string('message_id', 190)->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->index(['state', 'scheduled_at']);
        });

        Schema::create('notification_preferences', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('user_id', 40)->index();
            $table->string('kind', 40);
            $table->string('channel', 12);
            $table->boolean('enabled')->default(true);
            $table->timestamps();
            $table->unique(['user_id', 'kind', 'channel']);
        });

        Schema::create('webhook_endpoints', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('organization_id', 40)->index();
            $table->string('url', 500);
            $table->text('secret');                     // encrypted; HMAC-SHA256 signing key shown once
            $table->json('events');                     // subscribed event names or ['*']
            $table->string('state', 12)->default('active'); // active | paused | disabled
            $table->unsignedSmallInteger('failures')->default(0);
            $table->timestamp('last_delivered_at')->nullable();
            $table->string('created_by', 40)->nullable();
            $table->timestamps();
        });

        Schema::create('webhook_deliveries', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('endpoint_id', 40)->index();
            $table->string('outbox_message_id', 40)->nullable()->index();
            $table->string('event', 80);
            $table->json('payload');
            $table->string('state', 12)->default('pending'); // pending | delivered | failed | dead
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->string('last_error', 250)->nullable();
            $table->timestamp('next_attempt_at')->nullable()->index();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
            $table->unique(['endpoint_id', 'outbox_message_id']);
        });
    }

    public function down(): void
    {
        foreach (['webhook_deliveries', 'webhook_endpoints', 'notification_preferences', 'mail_outbox', 'notifications', 'notification_templates'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
