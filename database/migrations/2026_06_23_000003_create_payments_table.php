<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->string('status')->default('pending')->index();
            $table->string('method')->index();
            $table->decimal('amount', 12, 2);
            $table->string('gateway_reference')->nullable();
            $table->json('gateway_response')->nullable();
            // Client-supplied Idempotency-Key; unique per order so a retried or
            // double-submitted request can never create a second charge.
            $table->string('idempotency_key')->nullable();
            $table->timestamps();

            // Both list paths order a single order's payments by recency.
            $table->index(['order_id', 'created_at']);
            $table->unique(['order_id', 'idempotency_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
