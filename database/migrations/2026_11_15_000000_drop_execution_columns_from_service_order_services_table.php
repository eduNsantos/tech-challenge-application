<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// A execução dos serviços (início/fim) agora é responsabilidade do Services MS
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_order_services', function (Blueprint $table) {
            $table->dropForeign(['started_user_id']);
            $table->dropForeign(['finished_user_id']);
        });

        Schema::table('service_order_services', function (Blueprint $table) {
            $table->dropColumn(['started_at', 'finished_at', 'started_user_id', 'finished_user_id']);
        });
    }

    public function down(): void
    {
        Schema::table('service_order_services', function (Blueprint $table) {
            $table->datetime('started_at')->nullable();
            $table->foreignId('started_user_id')->nullable()->constrained('users');
            $table->datetime('finished_at')->nullable();
            $table->foreignId('finished_user_id')->nullable()->constrained('users');
        });
    }
};
