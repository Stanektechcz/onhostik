<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Customer service reviews (finishes the declared "Recenze" admin module).
 *
 * The admin.reviews route, sidebar link and page already existed but rendered
 * a "coming soon" placeholder. A customer rates a service they actually own;
 * an admin moderates before the review counts. Nothing is public until an admin
 * approves it — a hosting review page that auto-publishes is a spam magnet.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('service_reviews')) {
            return;
        }

        Schema::create('service_reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            // The service is the proof the reviewer is a real customer; the
            // product lets us aggregate a rating per product.
            $table->foreignId('service_id')->nullable()->constrained('services')->nullOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->unsignedTinyInteger('rating'); // 1..5
            $table->string('title', 150)->nullable();
            $table->text('body');
            $table->string('status', 20)->default('pending'); // pending|approved|rejected
            $table->foreignId('moderated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('moderated_at')->nullable();
            $table->timestamps();

            // One review per customer per service — resubmitting edits it.
            $table->unique(['customer_id', 'service_id']);
            $table->index(['product_id', 'status']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_reviews');
    }
};
