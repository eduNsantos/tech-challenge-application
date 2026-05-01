<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('service_order_services', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('service_order_id');
            $table->uuid('service_id');
            $table->foreign('service_id')->references('id')->on('services');
            $table->foreign('service_order_id')->references('id')->on('service_orders');
            $table->integer('quantity');
            $table->decimal('price');
            $table->datetime('started_at')->nullable();
            $table->datetime('finished_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_order_services');
    }
};
