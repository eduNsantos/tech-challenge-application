<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('service_orders', function (Blueprint $table) {
            $table->uuid('id')->default(DB::raw('(UUID())'))->primary();
            $table->uuid('customer_id');
            $table->uuid('vehicle_id');
            $table->string('customer_document', 14);
            $table->json('services');
            $table->json('parts')->nullable();
            $table->string('status', 40);
            $table->decimal('services_total', 10, 2)->default(0);
            $table->decimal('parts_total', 10, 2)->default(0);
            $table->decimal('total_budget', 10, 2)->default(0);
            $table->timestamp('quote_sent_at')->nullable();
            $table->timestamp('quote_approved_at')->nullable();
            $table->foreignId('created_user_id')->constrained('users');
            $table->foreignId('updated_user_id')->constrained('users');
            $table->timestamps();

            $table->foreign('customer_id')->references('id')->on('customers')->cascadeOnDelete();
            $table->foreign('vehicle_id')->references('id')->on('vehicles')->cascadeOnDelete();
            $table->index('status');
            $table->index('customer_document');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_orders');
    }
};
