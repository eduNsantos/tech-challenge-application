<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_order_services', function (Blueprint $table) {
            $table->foreignId('started_user_id')->nullable()->after('started_at')->constrained('users');
            $table->foreignId('finished_user_id')->nullable()->after('finished_at')->constrained('users');
        });
    }

    public function down(): void
    {
        Schema::table('service_order_services', function (Blueprint $table) {
            $table->dropForeign(['started_user_id']);
            $table->dropForeign(['finished_user_id']);
            $table->dropColumn(['started_user_id', 'finished_user_id']);
        });
    }
};
