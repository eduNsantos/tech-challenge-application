<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('items', function (Blueprint $table) {
            $table->uuid('id')->default(DB::raw('(UUID())'))->primary();
            $table->string('name');
            $table->string('code', 30)->unique();
            $table->enum('type', ['part', 'supply']);
            $table->text('description')->nullable();
            $table->string('measure_unit', 10);
            $table->decimal('stock_quantity', 10, 3)->default(0);
            $table->decimal('minimum_quantity', 10, 3)->default(0);
            $table->decimal('unit_price', 10, 2)->nullable();
            $table->foreignId('created_user_id')->constrained('users');
            $table->foreignId('updated_user_id')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('items');
    }
};
