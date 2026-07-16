<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('invoice_reminder_rules')) {
            Schema::create('invoice_reminder_rules', function (Blueprint $table): void {
                $table->id();
                $table->tinyInteger('days_after_due')->unsigned(); // 7, 14, 30 …
                $table->string('channel', 20)->default('mail');   // mail | database
                $table->string('template_key', 100)->default('invoice.overdue');
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->unique(['days_after_due', 'channel']);
            });
        }

    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_reminder_rules');
    }
};
