<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('newsletter_campaigns')) {
            Schema::create('newsletter_campaigns', function (Blueprint $table): void {
                $table->id();
                $table->string('subject', 255);
                $table->longText('body_html');
                $table->text('body_text')->nullable();
                $table->string('status', 20)->default('draft'); // draft|sending|sent|failed
                $table->unsignedInteger('recipients_count')->default(0);
                $table->unsignedInteger('sent_count')->default(0);
                $table->unsignedBigInteger('created_by')->nullable();
                $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
                $table->timestamp('sent_at')->nullable();
                $table->timestamps();
            });
        }

    }

    public function down(): void
    {
        Schema::dropIfExists('newsletter_campaigns');
    }
};
