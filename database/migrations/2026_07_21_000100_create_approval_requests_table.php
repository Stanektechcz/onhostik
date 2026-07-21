<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Four-eyes approval for risky operations (audit 74).
 *
 * A staged action: one admin requests it, a DIFFERENT admin approves, and only
 * then does it execute. This does not remove any admin capability (the standing
 * rule that an admin may do anything still holds) — it adds a second-person
 * checkpoint to the specific operations configured as high-risk, and is opt-in
 * per action so nothing changes until it is switched on.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('approval_requests')) {
            return;
        }

        Schema::create('approval_requests', function (Blueprint $table): void {
            $table->id();
            $table->string('action');                 // slug in config/approvals.php
            $table->json('payload')->nullable();       // params needed to execute
            $table->nullableMorphs('subject');         // optional entity the action targets
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('pending'); // pending|approved|rejected|executed|failed
            $table->text('review_note')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('executed_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamps();

            $table->index(['status', 'action']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_requests');
    }
};
