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
        Schema::table('service_orders', function (Blueprint $table): void {
            $table->timestamp('status_started_at')->nullable()->after('status');
            $table->index('status_started_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('service_orders', function (Blueprint $table): void {
            $table->dropIndex(['status_started_at']);
            $table->dropColumn('status_started_at');
        });
    }
};
