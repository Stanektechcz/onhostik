<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('email_deliveries')) {
            Schema::create('email_deliveries', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->string('recipient');
                $table->string('subject');
                $table->string('mailable_class')->nullable();
                $table->enum('status', ['queued', 'sent', 'delivered', 'bounced', 'failed'])->default('queued')->index();
                $table->string('message_id')->nullable()->unique();
                $table->text('error_message')->nullable();
                $table->timestamp('sent_at')->nullable();
                $table->timestamp('delivered_at')->nullable();
                $table->timestamp('bounced_at')->nullable();
                $table->timestamps();
            });
        }

    }

    public function down(): void
    {
        Schema::dropIfExists('email_deliveries');
    }
};
