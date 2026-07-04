<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('email_drip_sequences', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 150);
            $table->string('trigger_event', 50)->default('manual'); // manual | signup | service_created
            $table->boolean('is_active')->default(false);
            $table->text('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('email_drip_steps', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('drip_sequence_id')->constrained('email_drip_sequences')->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->unsignedSmallInteger('delay_days')->default(0);
            $table->string('subject', 255);
            $table->longText('body_html');
            $table->text('body_text')->nullable();
            $table->timestamps();

            $table->index(['drip_sequence_id', 'sort_order']);
        });

        Schema::create('email_drip_enrollments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('drip_sequence_id')->constrained('email_drip_sequences')->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('email', 254);
            $table->string('name', 150)->nullable();
            $table->timestamp('enrolled_at')->useCurrent();
            $table->unsignedSmallInteger('next_step_index')->default(0);
            $table->timestamp('next_send_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('unsubscribed_at')->nullable();
            $table->timestamps();

            $table->index(['drip_sequence_id', 'next_send_at', 'completed_at']);
            $table->unique(['drip_sequence_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_drip_enrollments');
        Schema::dropIfExists('email_drip_steps');
        Schema::dropIfExists('email_drip_sequences');
    }
};
