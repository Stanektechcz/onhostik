<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('service_firewall_rules')) {
            Schema::create('service_firewall_rules', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('service_id')->index();
                $table->enum('direction', ['in', 'out', 'both'])->default('in');
                $table->enum('protocol', ['tcp', 'udp', 'icmp', 'any'])->default('tcp');
                $table->unsignedSmallInteger('port_from')->nullable();
                $table->unsignedSmallInteger('port_to')->nullable();
                $table->string('ip_cidr', 50)->default('0.0.0.0/0');
                $table->enum('action', ['allow', 'deny'])->default('allow');
                $table->boolean('is_active')->default(true)->index();
                $table->text('description')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();

                $table->foreign('service_id')->references('id')->on('services')->cascadeOnDelete();
            });
        }

    }

    public function down(): void
    {
        Schema::dropIfExists('service_firewall_rules');
    }
};
