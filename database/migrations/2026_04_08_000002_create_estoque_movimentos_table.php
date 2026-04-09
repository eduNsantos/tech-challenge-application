<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->uuid('id')->default(DB::raw('(UUID())'))->primary();
            $table->uuid('item_id');
            $table->foreign('item_id')->references('id')->on('items');
            $table->enum('movement_type', ['entry', 'withdrawal']);
            $table->decimal('quantity', 10, 3);
            $table->decimal('previous_quantity', 10, 3);
            $table->decimal('current_quantity', 10, 3);
            $table->string('reason');
            $table->text('notes')->nullable();
            $table->foreignId('created_user_id')->constrained('users');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
